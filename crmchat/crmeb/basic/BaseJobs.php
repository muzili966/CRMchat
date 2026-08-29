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

namespace crmeb\basic;

use crmeb\interfaces\JobInterface;
use crmeb\services\tenant\TenantContext;
use think\facade\Log;
use think\queue\Job;

/**
 * 消息队列基类
 * Class BaseJobs
 * @package crmeb\basic
 */
class BaseJobs implements JobInterface
{

    /**
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments)
    {
        $this->fire(...$arguments);
    }

    /**
     * 运行消息队列
     * @param Job $job
     * @param $data
     */
    public function fire(Job $job, $data): void
    {
        try {
            $action = $data['do'] ?? 'doJob';//任务名
            $infoData = $data['data'] ?? [];//执行数据
            $errorCount = $data['errorCount'] ?? 0;//最大错误次数
            $tenantId = $data['tenant_id'] ?? null;//投递时捕获的租户上下文
            if (!is_null($tenantId)) {
                TenantContext::runAs((int)$tenantId, function () use ($action, $job, $infoData, $errorCount) {
                    $this->runJob($action, $job, $infoData, $errorCount);
                });
            } else {
                $this->runJob($action, $job, $infoData, $errorCount);
            }
        } catch (\Throwable $e) {
            Log::error('队列任务执行失败：' . get_class($this) . '，原因：' . $e->getMessage());
            $job->delete();
        }
    }

    /**
     * 执行队列
     * @param string $action
     * @param Job $job
     * @param array $infoData
     * @param int $errorCount
     */
    protected function runJob(string $action, Job $job, array $infoData, int $errorCount = 3)
    {

        $action = method_exists($this, $action) ? $action : 'handle';
        if (!method_exists($this, $action)) {
            $job->delete();
        }
        if ($this->{$action}(...$infoData)) {
            //删除任务
            $job->delete();
        } else {
            if ($job->attempts() >= $errorCount && $errorCount) {
                //删除任务
                $job->delete();
            } else {
                //从新放入队列
                $job->release();
            }
        }
    }

}
