<?php

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2020 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------


namespace crmeb\listeners;

use app\webscoket\Manager;
use crmeb\interfaces\ListenerInterface;
use Swoole\Server;
use Swoole\Server\Task;
use think\facade\Log;

class SwooleTaskListen implements ListenerInterface
{
    /**
     * @var Task
     */
    protected $task;

    public function handle($task): void
    {
        $this->task = $task;
        if (method_exists($this, $task->data['type'])) {
            $this->{$task->data['type']}($task->data['data']);
        } else {
            Log::error('任务执行失败,' . $task->data['type'] . '方法不存在');
        }
//        异步事件执行回调
//        $task->finish($task->data);
        return;
    }

    /**
     * AI回复任务：LLM调用耗时秒级，放在task进程执行避免阻塞ws worker事件循环
     *
     * 投递方 SwooleTaskService::instance('aiReply')->data($payload)->push()，
     * 故业务载荷位于 $data['data']['data']，租户随任务数据传递（task进程无协程上下文）
     * @param array $data
     * @return void
     */
    public function aiReply(array $data)
    {
        $payload = $data['data']['data'] ?? [];
        $tenantId = isset($data['tenant_id']) && !is_null($data['tenant_id']) ? (int)$data['tenant_id'] : 0;
        if (!$payload || !$tenantId) {
            Log::error('AI回复任务参数缺失');
            return;
        }
        try {
            \crmeb\services\tenant\TenantContext::runAs($tenantId, function () use ($payload) {
                /** @var \app\services\ai\AiReplyServices $services */
                $services = app()->make(\app\services\ai\AiReplyServices::class);
                $services->handle($payload);
            });
        } catch (\Throwable $e) {
            Log::error('AI回复任务执行失败：' . $e->getMessage());
        }
    }

    public function message(array $data)
    {
        /** @var Server $server */
        $server = app()->make(Server::class);
        $userId = is_array($data['user_id']) ? $data['user_id'] : [$data['user_id']];
        $except = $data['except'] ?? [];
        //task进程没有投递方协程上下文，租户从任务数据中恢复
        $tenantId = isset($data['tenant_id']) && !is_null($data['tenant_id']) ? (int)$data['tenant_id'] : null;
        if (!count($userId) && $data['type'] != 'user') {
            $fds = Manager::userFd(0, '', $tenantId);
            foreach ($fds as $fd) {
                if (!in_array($fd, $except) && $server->isEstablished($fd))
                    $server->push((int)$fd, json_encode($data['data']));
            }
        } else {
            foreach ($userId as $id) {
                $fds = Manager::userFd($data['type'], $id, $tenantId);
                foreach ($fds as $fd) {
                    if (!in_array($fd, $except) && $server->isEstablished($fd))
                        $server->push((int)$fd, json_encode($data['data']));
                }
            }
        }
    }

}
