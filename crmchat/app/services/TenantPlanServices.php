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

namespace app\services;


use app\dao\ApplicationDao;
use app\dao\chat\ChatServiceDao;
use app\dao\system\attachment\SystemAttachmentDao;
use app\dao\TenantDao;
use app\dao\TenantPlanDao;
use app\models\TenantPlan;
use crmeb\basic\BaseServices;
use crmeb\exceptions\AdminException;
use crmeb\services\CacheService;
use crmeb\services\tenant\TenantContext;

/**
 * 租户套餐service
 * Class TenantPlanServices
 * @package app\services
 */
class TenantPlanServices extends BaseServices
{

    /**
     * 租户当前套餐的缓存key前缀与TTL（套餐/订购变更最迟5分钟生效）
     */
    const PLAN_CACHE_PREFIX = 'tenant_plan:';
    const PLAN_CACHE_TTL = 300;

    /**
     * 每日消息量计数key前缀（按天计数，保留2天）
     */
    const MSG_COUNT_PREFIX = 'tenant_msg_count:';
    const MSG_COUNT_TTL = 172800;

    /**
     * TenantPlanServices constructor.
     * @param TenantPlanDao $dao
     */
    public function __construct(TenantPlanDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 套餐列表（含使用中的租户数）
     * @param array $where
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getPlanList(array $where)
    {
        $where['is_delete'] = 0;
        $list = $this->dao->getPlanList($where);
        /** @var TenantDao $tenantDao */
        $tenantDao = app()->make(TenantDao::class);
        foreach ($list as &$item) {
            $item['tenant_count'] = $tenantDao->getCount(['plan_id' => $item['id'], 'is_delete' => 0]);
        }
        return ['list' => $list, 'count' => count($list)];
    }

    /**
     * 套餐下拉选项（在售）
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getOptions()
    {
        return $this->dao->getPlanList(['is_delete' => 0, 'status' => TenantPlan::STATUS_ON]);
    }

    /**
     * 创建套餐
     * @param array $data
     * @return \crmeb\basic\BaseModel|\think\Model
     */
    public function create(array $data)
    {
        $this->validatePlanData($data);
        if ($this->dao->getCount(['name' => $data['name'], 'is_delete' => 0])) {
            throw new AdminException('套餐名称已存在');
        }
        $data['create_time'] = time();
        $data['update_time'] = time();
        return $this->dao->save($data);
    }

    /**
     * 修改套餐
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
        $planInfo = $this->dao->get($id);
        if (!$planInfo || $planInfo->is_delete) {
            throw new AdminException('套餐不存在');
        }
        $this->validatePlanData($data);
        if ($data['name'] != $planInfo->name && $this->dao->getCount(['name' => $data['name'], 'is_delete' => 0])) {
            throw new AdminException('套餐名称已存在');
        }
        $data['update_time'] = time();
        return false !== $this->dao->update($id, $data);
    }

    /**
     * 套餐字段基础校验
     * @param array $data
     * @return void
     */
    protected function validatePlanData(array $data)
    {
        if (!$data['name']) {
            throw new AdminException('请填写套餐名称');
        }
        if ($data['price'] < 0) {
            throw new AdminException('套餐价格不能为负数');
        }
        foreach (TenantPlan::QUOTA_FIELDS as $field) {
            if (isset($data[$field]) && $data[$field] < 0) {
                throw new AdminException('配额不能为负数，0表示不限');
            }
        }
    }

    /**
     * 停售/上架套餐
     * @param int $id
     * @param int $status
     * @return bool
     */
    public function setStatus(int $id, int $status)
    {
        $planInfo = $this->dao->get($id);
        if (!$planInfo || $planInfo->is_delete) {
            throw new AdminException('套餐不存在');
        }
        return false !== $this->dao->update($id, ['status' => $status, 'update_time' => time()]);
    }

    /**
     * 删除套餐（仍有租户使用时禁止删除）
     * @param int $id
     * @return bool
     */
    public function remove(int $id)
    {
        $planInfo = $this->dao->get($id);
        if (!$planInfo || $planInfo->is_delete) {
            throw new AdminException('套餐不存在');
        }
        /** @var TenantDao $tenantDao */
        $tenantDao = app()->make(TenantDao::class);
        if ($tenantDao->getCount(['plan_id' => $id, 'is_delete' => 0])) {
            throw new AdminException('该套餐仍有租户在使用，无法删除');
        }
        return false !== $this->dao->update($id, ['is_delete' => 1, 'update_time' => time()]);
    }

    /**
     * 新租户默认套餐ID（价格最低的在售套餐，通常为免费版）
     * @return int
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getDefaultPlanId(): int
    {
        $plan = $this->dao->getDefaultPlan();
        return $plan ? (int)$plan->id : 0;
    }

    /**
     * 获取租户当前套餐（带缓存），空数组表示未绑定套餐=不做限制
     * @param int $tenantId
     * @return array
     */
    public function getTenantPlan(int $tenantId): array
    {
        if ($tenantId <= 0) {
            return [];
        }
        try {
            return CacheService::redisHandler()->remember(self::PLAN_CACHE_PREFIX . $tenantId, function () use ($tenantId) {
                return TenantContext::withoutTenant(function () use ($tenantId) {
                    /** @var TenantDao $tenantDao */
                    $tenantDao = app()->make(TenantDao::class);
                    $planId = (int)$tenantDao->value(['id' => $tenantId], 'plan_id');
                    if (!$planId) {
                        return [];
                    }
                    $plan = $this->dao->get(['id' => $planId, 'is_delete' => 0]);
                    return $plan ? $plan->toArray() : [];
                });
            }, self::PLAN_CACHE_TTL) ?: [];
        } catch (\Throwable $e) {
            \think\facade\Log::error('读取租户套餐失败：' . $e->getMessage());
            return [];
        }
    }

    /**
     * 清除租户套餐缓存（订购/续费后调用）
     * @param int $tenantId
     * @return void
     */
    public function clearTenantPlanCache(int $tenantId)
    {
        try {
            CacheService::redisHandler()->delete(self::PLAN_CACHE_PREFIX . $tenantId);
        } catch (\Throwable $e) {
            \think\facade\Log::error('清除租户套餐缓存失败：' . $e->getMessage());
        }
    }

    /**
     * 用量是否超出配额（limit为0表示不限）
     * @param int $used 已用量（判定新增1个是否允许时传当前用量）
     * @param int $limit
     * @return bool
     */
    public static function isOverLimit(int $used, int $limit): bool
    {
        if ($limit <= TenantPlan::LIMIT_UNLIMITED) {
            return false;
        }
        return $used >= $limit;
    }

    /**
     * 校验接入应用数配额
     * @param int $tenantId
     * @return void
     */
    public function checkAppQuota(int $tenantId)
    {
        $plan = $this->getTenantPlan($tenantId);
        if (!$plan) {
            return;
        }
        $used = TenantContext::withoutTenant(function () use ($tenantId) {
            /** @var ApplicationDao $applicationDao */
            $applicationDao = app()->make(ApplicationDao::class);
            return $applicationDao->getCount(['tenant_id' => $tenantId, 'is_delete' => 0]);
        });
        if (self::isOverLimit((int)$used, (int)$plan['app_limit'])) {
            throw new AdminException('接入应用数已达套餐上限(' . $plan['app_limit'] . '个)，请升级套餐');
        }
    }

    /**
     * 校验客服坐席数配额
     * @param int $tenantId
     * @return void
     */
    public function checkSeatQuota(int $tenantId)
    {
        $plan = $this->getTenantPlan($tenantId);
        if (!$plan) {
            return;
        }
        $used = TenantContext::withoutTenant(function () use ($tenantId) {
            /** @var ChatServiceDao $serviceDao */
            $serviceDao = app()->make(ChatServiceDao::class);
            return $serviceDao->getCount(['tenant_id' => $tenantId]);
        });
        if (self::isOverLimit((int)$used, (int)$plan['seat_limit'])) {
            throw new AdminException('客服坐席数已达套餐上限(' . $plan['seat_limit'] . '个)，请升级套餐');
        }
    }

    /**
     * 是否具备某项功能
     * @param int $tenantId
     * @param string $feature TenantPlan::FEATURE_FIELDS 之一
     * @return bool
     */
    public function hasFeature(int $tenantId, string $feature): bool
    {
        $plan = $this->getTenantPlan($tenantId);
        if (!$plan) {
            return true;
        }
        return !empty($plan[$feature]);
    }

    /**
     * 断言具备某项功能，否则抛出异常
     * @param int $tenantId
     * @param string $feature
     * @param string $message
     * @return void
     */
    public function assertFeature(int $tenantId, string $feature, string $message)
    {
        if (!$this->hasFeature($tenantId, $feature)) {
            throw new AdminException($message);
        }
    }

    /**
     * 校验附件存储配额
     * @param int $tenantId
     * @param int $addBytes 本次新增字节数
     * @return void
     */
    public function checkStorage(int $tenantId, int $addBytes)
    {
        $plan = $this->getTenantPlan($tenantId);
        if (!$plan || (int)$plan['storage_limit_mb'] <= TenantPlan::LIMIT_UNLIMITED) {
            return;
        }
        $usedBytes = (float)TenantContext::withoutTenant(function () use ($tenantId) {
            /** @var SystemAttachmentDao $attachmentDao */
            $attachmentDao = app()->make(SystemAttachmentDao::class);
            return $attachmentDao->sum(['tenant_id' => $tenantId], 'att_size', false);
        });
        $limitBytes = (int)$plan['storage_limit_mb'] * 1024 * 1024;
        if ($usedBytes + $addBytes > $limitBytes) {
            throw new AdminException('附件存储已达套餐上限(' . $plan['storage_limit_mb'] . 'MB)，请升级套餐');
        }
    }

    /**
     * 每日消息量配额（热路径，Redis自增计数），true=允许发送
     * @param int $tenantId
     * @return bool
     */
    public function checkDailyMessage(int $tenantId): bool
    {
        $plan = $this->getTenantPlan($tenantId);
        $limit = (int)($plan['daily_msg_limit'] ?? 0);
        if ($limit <= TenantPlan::LIMIT_UNLIMITED) {
            return true;
        }
        try {
            $cache = CacheService::redisHandler();
            $key = self::MSG_COUNT_PREFIX . $tenantId . ':' . date('Ymd');
            $count = (int)$cache->incr($key);
            if ($count == 1) {
                $cache->expire($key, self::MSG_COUNT_TTL);
            }
            return $count <= $limit;
        } catch (\Throwable $e) {
            \think\facade\Log::error('消息量计数失败：' . $e->getMessage());
            return true;
        }
    }

    /**
     * 按套餐保留天数清理各租户历史聊天记录（由定时任务每日触发）
     * @return void
     */
    public function cleanExpiredRecords()
    {
        $tenantPlans = TenantContext::withoutTenant(function () {
            /** @var TenantDao $tenantDao */
            $tenantDao = app()->make(TenantDao::class);
            return $tenantDao->getColumn(['is_delete' => 0], 'plan_id', 'id');
        });
        foreach ($tenantPlans as $tenantId => $planId) {
            $plan = $this->getTenantPlan((int)$tenantId);
            $keepDays = (int)($plan['record_keep_days'] ?? 0);
            if ($keepDays <= 0) {
                continue;
            }
            TenantContext::runAs((int)$tenantId, function () use ($keepDays) {
                /** @var \app\dao\chat\ChatServiceDialogueRecordDao $recordDao */
                $recordDao = app()->make(\app\dao\chat\ChatServiceDialogueRecordDao::class);
                $recordDao->deleteBeforeTime(time() - $keepDays * 86400);
            });
        }
    }

    /**
     * 租户端订阅概览：租户信息 + 当前套餐 + 配额用量
     * @param int $tenantId
     * @return array
     */
    public function getMySubscription(int $tenantId): array
    {
        $tenant = TenantContext::withoutTenant(function () use ($tenantId) {
            /** @var TenantDao $tenantDao */
            $tenantDao = app()->make(TenantDao::class);
            $row = $tenantDao->get(['id' => $tenantId, 'is_delete' => 0]);
            return $row ? $row->toArray() : [];
        });
        if (!$tenant) {
            throw new AdminException('租户不存在');
        }
        $plan = $this->getTenantPlan($tenantId);
        [$appCount, $seatCount] = TenantContext::withoutTenant(function () use ($tenantId) {
            /** @var ApplicationDao $applicationDao */
            $applicationDao = app()->make(ApplicationDao::class);
            /** @var ChatServiceDao $serviceDao */
            $serviceDao = app()->make(ChatServiceDao::class);
            return [
                $applicationDao->getCount(['tenant_id' => $tenantId, 'is_delete' => 0]),
                $serviceDao->getCount(['tenant_id' => $tenantId]),
            ];
        });
        return self::buildSubscriptionSummary($tenant, $plan, (int)$appCount, (int)$seatCount);
    }

    /**
     * 组装订阅概览（纯函数，便于测试）
     * @param array $tenant
     * @param array $plan
     * @param int $appCount
     * @param int $seatCount
     * @return array
     */
    public static function buildSubscriptionSummary(array $tenant, array $plan, int $appCount, int $seatCount): array
    {
        $expireTime = (int)($tenant['expire_time'] ?? 0);
        return [
            'tenant' => [
                'id' => (int)($tenant['id'] ?? 0),
                'name' => $tenant['name'] ?? '',
                'status' => (int)($tenant['status'] ?? 0),
                'expire_time' => $expireTime,
                '_expire_time' => $expireTime ? date('Y-m-d H:i:s', $expireTime) : '永久',
                'is_expired' => $expireTime > 0 && $expireTime < time(),
            ],
            'plan' => $plan ?: null,
            'usage' => [
                'app_count' => $appCount,
                'seat_count' => $seatCount,
            ],
        ];
    }
}
