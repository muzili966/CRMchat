<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\command;

use app\services\export\ExportRunnerServices;
use app\services\export\ExportTaskServices;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 执行导出任务
 *
 * 常驻服务每 10 秒自动扫一次（见 SwooleWorkerStart），
 * 此命令供未跑常驻服务的环境（如本机开发）与排障时手动触发。
 * Class ExportRun
 * @package crmeb\command
 */
class ExportRun extends Command
{
    protected function configure()
    {
        $this->setName('export:run')
            ->addOption('batch', null, Option::VALUE_REQUIRED, '单次处理任务数', ExportRunnerServices::BATCH)
            ->addOption('gc', null, Option::VALUE_NONE, '同时清理过期导出文件')
            ->setDescription('执行下载中心的待处理导出任务');
    }

    protected function execute(Input $input, Output $output)
    {
        $batch = max(1, (int)$input->getOption('batch'));
        [$ok, $failed] = app()->make(ExportRunnerServices::class)->run($batch);
        $output->writeln("成功 {$ok}，失败 {$failed}");
        if ($input->getOption('gc')) {
            $output->writeln('清理过期任务 ' . app()->make(ExportTaskServices::class)->gc() . ' 条');
        }
        return 0;
    }
}
