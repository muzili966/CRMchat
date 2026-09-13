<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\dao;

use app\models\PaymentOrder;
use crmeb\basic\BaseDao;
use crmeb\services\payment\PaymentStatus;

/**
 * 支付单dao
 * Class PaymentOrderDao
 * @package app\dao
 */
class PaymentOrderDao extends BaseDao
{
    /**
     * @return string
     */
    protected function setModel(): string
    {
        return PaymentOrder::class;
    }

    /**
     * @param string $payNo
     * @return array|null
     */
    public function findByPayNo(string $payNo): ?array
    {
        $row = $this->getModel()->where('pay_no', $payNo)->find();
        return $row ? $row->toArray() : null;
    }

    /**
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->getModel()->where('id', $id)->find();
        return $row ? $row->toArray() : null;
    }

    /**
     * 按「当前状态」条件更新
     *
     * 回调、查单、过期关单可能同时处理同一张单，只有条件更新能保证状态只被推进一次；
     * 先查后改的写法在并发下会把一笔钱开通两次。
     * @param int $id
     * @param int $from 期望的当前状态
     * @param array $data
     * @return bool 是否由本次调用完成了更新
     */
    public function updateWhenStatus(int $id, int $from, array $data): bool
    {
        return $this->getModel()->where('id', $id)->where('status', $from)->update($data) > 0;
    }

    /**
     * 标记业务已开通，仅首次生效
     * @param int $id
     * @param int $time
     * @return bool
     */
    public function markFulfilled(int $id, int $time): bool
    {
        return $this->getModel()->where('id', $id)->where('fulfilled_time', 0)->update([
            'fulfilled_time' => $time,
            'abnormal' => 0,
            'abnormal_reason' => '',
            'update_time' => $time,
        ]) > 0;
    }

    /**
     * 已过期仍待支付的单
     * @param int $now
     * @param int $limit
     * @return array
     */
    public function expiredPending(int $now, int $limit): array
    {
        return $this->getModel()->where('status', PaymentStatus::PENDING)
            ->where('expire_time', '<', $now)
            ->order('id', 'asc')->limit($limit)->select()->toArray();
    }

    /**
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getPaymentList(array $where, int $page, int $limit): array
    {
        return $this->search($where)->when($page && $limit, function ($query) use ($page, $limit) {
            $query->page($page, $limit);
        })->order('id DESC')->select()->toArray();
    }
}
