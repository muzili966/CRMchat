<?php

namespace tests\unit;

use PHPUnit\Framework\TestCase;

/**
 * 全量安装脚本与增量升级脚本的一致性
 *
 * 增量脚本给老库补东西，全量脚本给新库装同样的东西，两边必须等价。
 * 实际已经漏过两次：菜单 1325 只加在增量里、export_task 建表只加在增量里，
 * 结果新装的环境功能直接缺失。这类疏漏靠人眼比对不可靠，钉在测试里。
 */
class InstallSqlConsistencyTest extends TestCase
{
    /**
     * @var string
     */
    protected $installDir;

    protected function setUp(): void
    {
        $this->installDir = dirname(__DIR__, 2) . '/public/install/';
    }

    /**
     * 增量脚本里出现的菜单ID，全量脚本必须都有
     */
    public function testEveryUpgradeMenuExistsInFullScript()
    {
        $full = $this->menus($this->fullSql());
        $missing = [];
        foreach ($this->upgradeFiles() as $name => $sql) {
            foreach (array_keys($this->menus($sql)) as $id) {
                if (!isset($full[$id])) {
                    $missing[] = $name . ' 的菜单 ' . $id;
                }
            }
        }
        $this->assertSame([], $missing, '全量脚本缺少菜单：' . implode('、', $missing));
    }

    /**
     * 增量脚本推演出的最终状态，与全量脚本必须一致
     *
     * 增量脚本是历史流水，后来的脚本会覆盖先前的（如接口从 GET 改成 POST），
     * 所以要按版本顺序推演出终态再比，逐个文件比会把正常的演进当成差异。
     */
    public function testMenuFinalStateMatchesFullScript()
    {
        $expected = $this->upgradeFinalMenus();
        $full = $this->menus($this->fullSql());
        $diff = [];
        foreach ($expected as $id => $row) {
            if (!isset($full[$id])) {
                continue;
            }
            foreach (['menu_name', 'api_url', 'methods'] as $field) {
                $a = $row[$field] ?? null;
                $b = $full[$id][$field] ?? null;
                if ($a !== null && $b !== null && $a !== $b) {
                    $diff[] = "菜单{$id} 的 {$field}：增量终态是「{$a}」，全量是「{$b}」";
                }
            }
        }
        $this->assertSame([], $diff, implode('；', $diff));
    }

    /**
     * 按版本顺序推演全部增量脚本后的菜单终态
     * @return array
     */
    protected function upgradeFinalMenus(): array
    {
        $menus = [];
        //文件名以版本号开头，字典序即执行顺序
        $files = $this->upgradeFiles();
        ksort($files);
        foreach ($files as $sql) {
            foreach ($this->menus($sql) as $id => $row) {
                $menus[$id] = array_merge($menus[$id] ?? [], $row);
            }
            foreach ($this->menuUpdates($sql) as $id => $changes) {
                if (isset($menus[$id])) {
                    $menus[$id] = array_merge($menus[$id], $changes);
                }
            }
        }
        return $menus;
    }

    /**
     * 解析按主键更新菜单的 UPDATE 语句，返回 id => 被改字段
     *
     * 只认 WHERE id = N 这种单行更新；补权限用的 FIND_IN_SET 批量更新不涉及菜单定义。
     * @param string $sql
     * @return array
     */
    protected function menuUpdates(string $sql): array
    {
        $pattern = '/UPDATE\s+`?eb_system_menus`?\s+SET\s+(.*?)\s+WHERE\s+`?id`?\s*=\s*(\d+)/is';
        if (!preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
            return [];
        }
        $updates = [];
        foreach ($matches as $match) {
            $changes = [];
            //复用值切分：包一层括号即可按单引号状态安全拆开各赋值项
            foreach ($this->valueTuples('(' . $match[1] . ')')[0] ?? [] as $assignment) {
                $parts = explode('=', $assignment, 2);
                if (count($parts) !== 2) {
                    continue;
                }
                $changes[trim($parts[0], " \t`")] = trim($parts[1]);
            }
            $updates[(int)$match[2]] = $changes;
        }
        return $updates;
    }

    /**
     * 增量脚本建的表，全量脚本也要建
     */
    public function testEveryUpgradeTableExistsInFullScript()
    {
        $full = $this->createdTables($this->fullSql());
        $missing = [];
        foreach ($this->upgradeFiles() as $name => $sql) {
            foreach ($this->createdTables($sql) as $table) {
                if (!in_array($table, $full, true)) {
                    $missing[] = $name . ' 的表 ' . $table;
                }
            }
        }
        $this->assertSame([], $missing, '全量脚本缺少建表：' . implode('、', $missing));
    }

    /**
     * 菜单ID在全量脚本里不能重复定义
     */
    public function testFullScriptHasNoDuplicateMenuIds()
    {
        $ids = [];
        foreach ($this->menuRows($this->fullSql()) as $row) {
            $ids[] = $row[0];
        }
        $dupes = array_keys(array_filter(array_count_values($ids), function ($n) {
            return $n > 1;
        }));
        $this->assertSame([], $dupes, '全量脚本重复定义菜单：' . implode('、', $dupes));
    }

    /**
     * @return string
     */
    protected function fullSql(): string
    {
        return (string)file_get_contents($this->installDir . 'crmeb.sql');
    }

    /**
     * 全部增量脚本，文件名 => 内容
     * @return array
     */
    protected function upgradeFiles(): array
    {
        $files = glob($this->installDir . 'upgrade/V*.sql') ?: [];
        $out = [];
        foreach ($files as $path) {
            $out[basename($path)] = (string)file_get_contents($path);
        }
        return $out;
    }

    /**
     * 解析 eb_system_menus 的 INSERT，返回 id => 字段映射
     * @param string $sql
     * @return array
     */
    protected function menus(string $sql): array
    {
        $menus = [];
        foreach ($this->menuInserts($sql) as [$columns, $rows]) {
            foreach ($rows as $values) {
                if (count($values) !== count($columns)) {
                    continue;
                }
                $row = array_combine($columns, $values);
                $menus[(int)$row['id']] = $row;
            }
        }
        return $menus;
    }

    /**
     * 只要值数组，用于查重
     * @param string $sql
     * @return array
     */
    protected function menuRows(string $sql): array
    {
        $rows = [];
        foreach ($this->menuInserts($sql) as [, $values]) {
            $rows = array_merge($rows, $values);
        }
        return $rows;
    }

    /**
     * 提取所有 eb_system_menus 的 INSERT 语句，拆成 [列名, 各行值]
     * @param string $sql
     * @return array
     */
    protected function menuInserts(string $sql): array
    {
        $pattern = '/INSERT\s+INTO\s+`?eb_system_menus`?\s*\(([^)]+)\)\s*VALUES(.*?);/is';
        if (!preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
            return [];
        }
        $result = [];
        foreach ($matches as $match) {
            $columns = array_map(function ($col) {
                return trim($col, " \t\n\r`");
            }, explode(',', $match[1]));
            //ON DUPLICATE KEY UPDATE 段不是数据行，截掉
            $body = preg_split('/ON\s+DUPLICATE\s+KEY/i', $match[2])[0];
            $result[] = [$columns, $this->valueTuples($body)];
        }
        return $result;
    }

    /**
     * 把 VALUES 段拆成一行行的值数组
     *
     * 不能简单按逗号切：字符串里带逗号（如中文名、JSON 的 '[]'）会被拆断，
     * 故按单引号状态逐字扫描。
     * @param string $body
     * @return array
     */
    protected function valueTuples(string $body): array
    {
        $tuples = [];
        $current = [];
        $buffer = '';
        $inString = $inTuple = false;
        $length = strlen($body);
        for ($i = 0; $i < $length; $i++) {
            $char = $body[$i];
            if ($inString) {
                if ($char === '\\' && $i + 1 < $length) {
                    $buffer .= $body[++$i];
                    continue;
                }
                //连续两个单引号是转义后的引号本身
                if ($char === "'" && ($body[$i + 1] ?? '') === "'") {
                    $buffer .= "'";
                    $i++;
                    continue;
                }
                if ($char === "'") {
                    $inString = false;
                    continue;
                }
                $buffer .= $char;
                continue;
            }
            if ($char === "'") {
                $inString = true;
                continue;
            }
            if (!$inTuple) {
                if ($char === '(') {
                    $inTuple = true;
                    $current = [];
                    $buffer = '';
                }
                continue;
            }
            if ($char === ',') {
                $current[] = trim($buffer);
                $buffer = '';
                continue;
            }
            if ($char === ')') {
                $current[] = trim($buffer);
                $tuples[] = $current;
                $inTuple = false;
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        return $tuples;
    }

    /**
     * 提取 CREATE TABLE 的表名
     * @param string $sql
     * @return array
     */
    protected function createdTables(string $sql): array
    {
        preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(eb_[a-z0-9_]+)`?/i', $sql, $m);
        return array_values(array_unique($m[1] ?? []));
    }
}
