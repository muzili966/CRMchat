<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 增量升级脚本执行器
 *
 * 全量基线在 public/install/crmeb.sql，只用于新环境安装；
 * 已有环境靠 public/install/upgrade/ 下的版本脚本逐个推进，
 * 执行过的版本记在升级账本表，不会重复执行。
 * Class Upgrade
 * @package crmeb\command
 */
class Upgrade extends Command
{
    /**
     * 版本脚本目录
     */
    const SCRIPT_DIR = 'upgrade';

    /**
     * 账本表名去掉默认前缀后的部分
     */
    const LEDGER_TABLE = 'system_upgrade';

    /**
     * 文件名格式：V<8位日期>_<2位序号>__<描述>.sql
     */
    const FILE_PATTERN = '/^V([0-9]{8}_[0-9]{2})__([A-Za-z0-9_\-]+)\.sql$/';

    protected function configure()
    {
        $this->setName('upgrade')
            ->addOption('baseline', null, Option::VALUE_NONE, '只登记版本不执行,用于已手工升级过的存量库')
            ->addOption('dry-run', null, Option::VALUE_NONE, '只列出待执行版本')
            ->setDescription('执行未落库的增量升级脚本');
    }

    protected function execute(Input $input, Output $output)
    {
        $this->ensureLedger();
        $applied = $this->appliedVersions();
        $pending = array_values(array_filter($this->scripts(), function ($script) use ($applied) {
            return !in_array($script['version'], $applied, true);
        }));

        if (!$pending) {
            $output->writeln('<info>数据库已是最新，无待执行版本</info>');
            return 0;
        }
        foreach ($pending as $script) {
            $output->writeln('  ' . $script['version'] . '  ' . $script['name']);
        }
        if ($input->getOption('dry-run')) {
            return 0;
        }

        $baseline = (bool)$input->getOption('baseline');
        foreach ($pending as $script) {
            $baseline ? $this->markApplied($script, 0) : $this->apply($script, $output);
        }
        $output->writeln('<info>' . ($baseline ? '已登记 ' : '已执行 ') . count($pending) . ' 个版本</info>');
        return 0;
    }

    /**
     * 执行单个版本脚本
     *
     * MySQL 的 DDL 不受事务保护，中途失败无法回滚，只能靠脚本自身可重复执行兜底：
     * 失败即抛出且不写账本，修好后重跑
     * @param array $script
     * @param Output $output
     */
    protected function apply(array $script, Output $output)
    {
        $start = microtime(true);
        $statements = self::split((string)file_get_contents($script['path']));
        foreach ($statements as $sql) {
            $this->app->db->execute($this->replacePrefix($sql));
        }
        $cost = (int)round((microtime(true) - $start) * 1000);
        $this->markApplied($script, $cost);
        $output->writeln('<info>' . $script['version'] . ' 完成，' . count($statements) . ' 条语句 ' . $cost . 'ms</info>');
    }

    /**
     * 表名一律写成 eb_xxx，按配置前缀替换
     *
     * 反引号包裹的是标识符，单引号包裹的是 information_schema 判断里的表名字面量
     * @param string $sql
     * @return string
     */
    protected function replacePrefix(string $sql): string
    {
        $prefix = $this->prefix();
        if ($prefix === 'eb_') {
            return $sql;
        }
        return str_replace(['`eb_', "'eb_"], ['`' . $prefix, "'" . $prefix], $sql);
    }

    /**
     * 目录下的版本脚本，按版本号升序
     * @return array
     */
    protected function scripts(): array
    {
        $dir = root_path('public' . DIRECTORY_SEPARATOR . 'install') . self::SCRIPT_DIR;
        if (!is_dir($dir)) {
            return [];
        }
        $scripts = [];
        foreach ((array)scandir($dir) as $file) {
            if (!preg_match(self::FILE_PATTERN, (string)$file, $matches)) {
                continue;
            }
            $scripts[] = [
                'version' => $matches[1],
                'name' => $matches[2],
                'path' => $dir . DIRECTORY_SEPARATOR . $file,
            ];
        }
        usort($scripts, function ($a, $b) {
            return strcmp($a['version'], $b['version']);
        });
        return $scripts;
    }

    /**
     * @return array
     */
    protected function appliedVersions(): array
    {
        $rows = $this->app->db->query('SELECT `version` FROM `' . $this->ledger() . '`');
        return array_map('strval', array_column((array)$rows, 'version'));
    }

    /**
     * @param array $script
     * @param int $cost
     */
    protected function markApplied(array $script, int $cost)
    {
        $this->app->db->execute(
            'INSERT INTO `' . $this->ledger() . '` (`version`,`name`,`checksum`,`cost_ms`,`create_time`) VALUES (?,?,?,?,?)',
            [$script['version'], $script['name'], (string)md5_file($script['path']), $cost, time()]
        );
    }

    /**
     * 账本表自身不走版本脚本，否则先有鸡还是先有蛋
     */
    protected function ensureLedger()
    {
        $this->app->db->execute("CREATE TABLE IF NOT EXISTS `" . $this->ledger() . "` (
            `version` varchar(20) NOT NULL COMMENT '版本号',
            `name` varchar(100) NOT NULL DEFAULT '' COMMENT '脚本描述',
            `checksum` varchar(32) NOT NULL DEFAULT '' COMMENT '文件md5,用于核对脚本是否被改动',
            `cost_ms` int NOT NULL DEFAULT 0 COMMENT '执行耗时',
            `create_time` int NOT NULL DEFAULT 0,
            PRIMARY KEY (`version`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='升级版本账本'");
    }

    /**
     * @return string
     */
    protected function ledger(): string
    {
        return $this->prefix() . self::LEDGER_TABLE;
    }

    /**
     * @return string
     */
    protected function prefix(): string
    {
        return (string)env('database.prefix', 'eb_');
    }

    /**
     * 按分号切分语句
     *
     * 不能简单 explode(';')：注释与字符串里都可能出现分号，
     * 而 PREPARE/EXECUTE 必须作为独立语句下发
     * @param string $sql
     * @return array
     */
    public static function split(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                $buffer .= $char;
                if ($char === '\\' && $quote !== '`' && $i + 1 < $len) {
                    $buffer .= $sql[++$i];
                } elseif ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === '-' && substr($sql, $i, 2) === '--') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $len : $end;
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === ';') {
                $statements[] = $buffer;
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        $statements[] = $buffer;
        return array_values(array_filter(array_map('trim', $statements), 'strlen'));
    }
}
