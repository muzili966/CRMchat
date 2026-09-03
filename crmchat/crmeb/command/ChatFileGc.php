<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\command;

use app\services\chat\ChatFileGcServices;
use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * 聊天文件回收
 *
 * 常驻服务每天自动跑一次（见 SwooleWorkerStart），此命令供手动触发与排障。
 * Class ChatFileGc
 * @package crmeb\command
 */
class ChatFileGc extends Command
{
    protected function configure()
    {
        $this->setName('chat:gc')
            ->setDescription('回收过期与孤儿聊天文件');
    }

    protected function execute(Input $input, Output $output)
    {
        $result = app()->make(ChatFileGcServices::class)->run();
        foreach ($result as $tenantId => $counts) {
            $output->writeln("租户 {$tenantId}：过期记录 {$counts['expired']}，孤儿文件 {$counts['orphan']}");
        }
        $output->writeln('<info>清理完成</info>');
        return 0;
    }
}
