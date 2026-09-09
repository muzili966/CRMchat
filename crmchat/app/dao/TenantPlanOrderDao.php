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

namespace app\dao;


use app\models\TenantPlanOrder;
use crmeb\basic\BaseDao;

/**
 * 租户套餐订购对账dao
 * Class TenantPlanOrderDao
 * @package app\dao
 */
class TenantPlanOrderDao extends BaseDao
{

    /**
     * @return string
     */
    protected function setModel(): string
    {
        return TenantPlanOrder::class;
    }

    /**
     * 某租户最后一次付费订阅的到期时间
     *
     * 用于降级宽限期：租户当前套餐只说明「现在买的是什么」，说明不了
     * 「曾经付过费、数据该被优待多久」。amount>0 才算付费，赠送与试用
     * 开的单不算；作废的单同样不算。
     * @param int $tenantId
     * @return int 时间戳，从未付费返回 0
     */
    public function lastPaidExpireAt(int $tenantId): int
    {
        return (int)$this->getModel()->where('tenant_id', $tenantId)
            ->where('status', TenantPlanOrder::STATUS_EFFECTIVE)
            ->where('amount', '>', 0)
            ->max('expire_after');
    }

    /**
     * 订购记录列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getOrderList(array $where, int $page = 0, int $limit = 0)
    {
        return $this->search($where)->when($page && $limit, function ($query) use ($page, $limit) {
            $query->page($page, $limit);
        })->order('id DESC')->select()->toArray();
    }
}
