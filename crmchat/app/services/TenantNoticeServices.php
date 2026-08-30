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


use app\dao\system\admin\SystemAdminDao;
use app\dao\TenantDao;
use app\dao\TenantNoticeDao;
use app\models\TenantNotice;
use crmeb\basic\BaseServices;
use crmeb\services\CacheService;
use crmeb\services\SwooleTaskService;
use crmeb\services\tenant\TenantContext;
use think\facade\Log;

/**
 * 租户通知service（到期自动通知）
 * Class TenantNoticeServices
 * @package app\services
 */
class TenantNoticeServices extends BaseServices
{

    /**
     * 到期前提醒天数
     */
    const WARN_DAYS = 7;

    /**
     * 通知去重key前缀与TTL（每租户每类型每天一条）
     */
    const DEDUP_PREFIX = 'tenant_expire_notice:';
    const DEDUP_TTL = 172800;

    /**
     * TenantNoticeServices constructor.
     * @param TenantNoticeDao $dao
     */
    public function __construct(TenantNoticeDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 扫描临期/已到期租户并发送通知（定时任务触发，天级去重）
     * @return void
     */
    public function checkExpireNotice()
    {
        $tenants = TenantContext::withoutTenant(function () {
            /** @var TenantDao $tenantDao */
            $tenantDao = app()->make(TenantDao::class);
            return $tenantDao->getExpiringList(time() + self::WARN_DAYS * 86400);
        });
        $today = date('Ymd');
        foreach ($tenants as $tenant) {
            $notice = self::buildExpireNotice((int)$tenant['expire_time'], time());
            if (!$notice) {
                continue;
            }
            $dedupKey = self::DEDUP_PREFIX . $today . ':' . $tenant['id'] . ':' . $notice['type'];
            try {
                if (CacheService::has($dedupKey)) {
                    continue;
                }
                CacheService::set($dedupKey, 1, self::DEDUP_TTL);
            } catch (\Throwable $e) {
                Log::error('到期通知去重失败：' . $e->getMessage());
                continue;
            }
            $this->addNotice((int)$tenant['id'], $notice['type'], $notice['content']);
        }
    }

    /**
     * 根据到期时间构建通知内容；无需通知返回null
     * @param int $expireTime
     * @param int $now
     * @return array|null ['type' => .., 'content' => ..]
     */
    public static function buildExpireNotice(int $expireTime, int $now)
    {
        if ($expireTime <= 0) {
            return null;
        }
        if ($expireTime <= $now) {
            return [
                'type' => TenantNotice::TYPE_EXPIRED,
                'content' => sprintf('套餐已于%s到期，服务已暂停，请联系平台续费', date('Y-m-d', $expireTime)),
            ];
        }
        $days = (int)ceil(($expireTime - $now) / 86400);
        if ($days > self::WARN_DAYS) {
            return null;
        }
        return [
            'type' => TenantNotice::TYPE_EXPIRE_WARN,
            'content' => sprintf('套餐将于%d天后(%s)到期，请及时续费以免影响使用', $days, date('Y-m-d', $expireTime)),
        ];
    }

    /**
     * 平台向租户批量发送公告通知
     * @param array $tenantIds 目标租户ID，空数组表示全部正常租户
     * @param string $content
     * @return int 发送条数
     */
    public function sendNotice(array $tenantIds, string $content): int
    {
        $content = trim($content);
        if ($content === '') {
            throw new \crmeb\exceptions\AdminException('请输入通知内容');
        }
        /** @var \app\dao\TenantDao $tenantDao */
        $tenantDao = app()->make(\app\dao\TenantDao::class);
        $validIds = array_map('intval', $tenantDao->getColumn([
            'status' => \app\models\Tenant::STATUS_NORMAL,
            'is_delete' => 0,
        ], 'id'));
        $targets = $tenantIds ? array_values(array_intersect(array_map('intval', $tenantIds), $validIds)) : $validIds;
        if (!$targets) {
            throw new \crmeb\exceptions\AdminException('没有可发送的租户');
        }
        foreach ($targets as $tenantId) {
            $this->addNotice($tenantId, TenantNotice::TYPE_ANNOUNCE, $content);
        }
        return count($targets);
    }

    /**
     * 写入租户通知并向该租户在线管理员推送
     * @param int $tenantId
     * @param string $type
     * @param string $content
     * @return void
     */
    public function addNotice(int $tenantId, string $type, string $content)
    {
        TenantContext::runAs($tenantId, function () use ($tenantId, $type, $content) {
            $this->dao->save([
                'tenant_id' => $tenantId,
                'type' => $type,
                'content' => $content,
                'is_read' => TenantNotice::UNREAD,
                'create_time' => time(),
            ]);
            $this->pushToTenantAdmins($content, $type);
        });
    }

    /**
     * 向当前租户的管理员推送websocket通知（推送失败不影响通知落库）
     * @param string $content
     * @param string $type
     * @return void
     */
    protected function pushToTenantAdmins(string $content, string $type)
    {
        try {
            /** @var SystemAdminDao $adminDao */
            $adminDao = app()->make(SystemAdminDao::class);
            $adminIds = $adminDao->getColumn(['is_del' => 0, 'status' => 1], 'id');
            if (!$adminIds) {
                return;
            }
            SwooleTaskService::admin()->type('TENANT_NOTICE')->to(array_values($adminIds))->data([
                'notice_type' => $type,
                'content' => $content,
            ])->push();
        } catch (\Throwable $e) {
            Log::error('租户通知推送失败：' . $e->getMessage());
        }
    }

    /**
     * 通知列表（当前租户视角）
     * @param array $where
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getNoticeList(array $where)
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getNoticeList($where, $page, $limit);
        $count = $this->dao->count($where);
        foreach ($list as &$item) {
            $item['_create_time'] = date('Y-m-d H:i:s', $item['create_time']);
        }
        return compact('list', 'count');
    }

    /**
     * 标记已读
     * @param int $id
     * @return bool
     */
    public function markRead(int $id)
    {
        return false !== $this->dao->update($id, ['is_read' => TenantNotice::READ]);
    }
}
