<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\payment;

use app\dao\TenantDao;
use app\dao\TenantPlanDao;
use app\dao\TenantPlanOrderDao;
use app\models\Tenant;
use app\models\TenantPlan;
use app\models\TenantPlanOrder;
use app\services\TenantPlanOrderServices;
use crmeb\services\payment\contract\PayableInterface;
use crmeb\services\payment\dto\PaymentNotice;
use crmeb\services\payment\dto\PaymentOrder;
use crmeb\services\payment\dto\PaymentQuote;
use crmeb\services\payment\Money;
use crmeb\services\payment\PaymentException;
use crmeb\services\tenant\TenantContext;

/**
 * 租户套餐续费
 *
 * 下单时锁定套餐、月数与金额，到账后按锁定的内容开通。
 * 付款期间套餐被调价或停售，已经付的钱照原价开通，不做多退少补。
 * Class TenantPlanPayable
 * @package app\services\payment
 */
class TenantPlanPayable implements PayableInterface
{
    const BIZ_TYPE = 'tenant_plan';

    const MONTHS_MIN = 1;

    /**
     * 与后台开通的可选月数上限一致，再长的周期走线下合同
     */
    const MONTHS_MAX = 36;

    /**
     * @return string
     */
    public function bizType(): string
    {
        return self::BIZ_TYPE;
    }

    /**
     * @param int $tenantId
     * @param array $params plan_id/months
     * @return PaymentQuote
     */
    public function quote(int $tenantId, array $params): PaymentQuote
    {
        $tenant = $this->tenant($tenantId);
        $months = (int)($params['months'] ?? 0);
        $plan = app()->make(TenantPlanDao::class)->get((int)($params['plan_id'] ?? 0));
        $plan = $plan ? $plan->toArray() : null;
        self::checkQuote($plan, $months);
        return new PaymentQuote(
            sprintf('「%s」套餐 %d个月', $plan['name'], $months),
            Money::multiply((string)$plan['price'], $months),
            [
                'plan_id' => (int)$plan['id'],
                'plan_name' => (string)$plan['name'],
                'price' => Money::normalize((string)$plan['price']),
                'months' => $months,
                'tenant_name' => (string)$tenant['name'],
            ]
        );
    }

    /**
     * 报价的业务校验，纯函数便于单测
     * @param array|null $plan
     * @param int $months
     * @return void
     */
    public static function checkQuote(?array $plan, int $months): void
    {
        if ($months < self::MONTHS_MIN || $months > self::MONTHS_MAX) {
            throw new PaymentException(sprintf('订购月数需在 %d 到 %d 之间', self::MONTHS_MIN, self::MONTHS_MAX));
        }
        if (!$plan || (int)($plan['is_delete'] ?? 0)) {
            throw new PaymentException('套餐不存在');
        }
        if ((int)($plan['status'] ?? 0) !== TenantPlan::STATUS_ON) {
            throw new PaymentException('套餐已停售');
        }
        if (Money::toCents((string)($plan['price'] ?? '0')) <= 0) {
            throw new PaymentException('免费套餐无需付款');
        }
    }

    /**
     * 到账开通
     *
     * 同一张支付单只开通一次：回调重发、查单与回调撞车、平台手工补开通都可能重入，
     * 以订购记录上的 pay_no 判重。
     * @param PaymentOrder $order
     * @param PaymentNotice $notice
     * @return void
     */
    public function fulfill(PaymentOrder $order, PaymentNotice $notice): void
    {
        if ($this->fulfilled($order)) {
            return;
        }
        $payload = $order->payload();
        app()->make(TenantPlanOrderServices::class)->subscribe([
            'tenant_id' => $order->tenantId(),
            'plan_id' => (int)($payload['plan_id'] ?? 0),
            'months' => (int)($payload['months'] ?? 0),
            'amount' => $order->amount(),
            'pay_no' => $order->payNo(),
            'pay_type' => TenantPlanOrder::PAY_TYPE_ONLINE,
            'remark' => '在线支付，交易号 ' . $notice->tradeNo(),
        ]);
    }

    /**
     * @param PaymentOrder $order
     * @return bool
     */
    protected function fulfilled(PaymentOrder $order): bool
    {
        return (bool)TenantContext::runAs($order->tenantId(), function () use ($order) {
            return app()->make(TenantPlanOrderDao::class)->getCount(['pay_no' => $order->payNo()]);
        });
    }

    /**
     * @param int $tenantId
     * @return array
     */
    protected function tenant(int $tenantId): array
    {
        if ($tenantId <= Tenant::DEFAULT_TENANT_ID) {
            throw new PaymentException('平台自营租户无需付款');
        }
        $tenant = app()->make(TenantDao::class)->get($tenantId);
        if (!$tenant || $tenant['is_delete']) {
            throw new PaymentException('租户不存在');
        }
        return $tenant->toArray();
    }
}
