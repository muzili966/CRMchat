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
namespace crmeb\subscribes;


use think\facade\Log;

/**
 * 定时任务类
 * Class TaskSubscribe
 * @package crmeb\subscribes
 */
class TaskSubscribe
{
    public function handle()
    {

    }

    /**
     * 2秒钟执行的方法
     */
    public function onTask_2()
    {
    }

    /**
     * 6秒钟执行的方法
     */
    public function onTask_6()
    {
    }

    /**
     * 10秒钟执行的方法
     */
    public function onTask_10()
    {
    }

    /**
     * 30秒钟执行的方法
     */
    public function onTask_30()
    {

    }

    /**
     * 60秒钟执行的方法
     */
    public function onTask_60()
    {

    }

    /**
     * 180秒钟执行的方法
     */
    public function onTask_180()
    {

    }

    /**
     * 300秒钟执行的方法：租户到期通知扫描 + 每日按套餐清理历史聊天记录
     */
    public function onTask_300()
    {
        try {
            /** @var \app\services\TenantNoticeServices $noticeServices */
            $noticeServices = app()->make(\app\services\TenantNoticeServices::class);
            $noticeServices->checkExpireNotice();
        } catch (\Throwable $e) {
            Log::error('租户到期通知扫描失败：' . $e->getMessage());
        }
        try {
            //记录清理为天级任务，用缓存去重避免每5分钟全量执行
            $dedupKey = 'tenant_record_clean:' . date('Ymd');
            if (!\crmeb\services\CacheService::has($dedupKey)) {
                \crmeb\services\CacheService::set($dedupKey, 1, 172800);
                /** @var \app\services\TenantPlanServices $planServices */
                $planServices = app()->make(\app\services\TenantPlanServices::class);
                $planServices->cleanExpiredRecords();
            }
        } catch (\Throwable $e) {
            Log::error('套餐保留天数清理失败：' . $e->getMessage());
        }
    }
}
