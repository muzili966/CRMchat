<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment\dto;

use crmeb\services\payment\Money;
use crmeb\services\payment\PaymentException;
use crmeb\services\payment\PaymentStatus;

/**
 * 支付单（只读视图）
 *
 * 交给业务履约与投递方式使用。它们只读不写：支付单状态只能由状态机翻转。
 */
final class PaymentOrder
{
    private $id;
    private $payNo;
    private $tenantId;
    private $bizType;
    private $payload;
    private $subject;
    private $amount;
    private $status;
    private $expireAt;

    private function __construct()
    {
    }

    /**
     * @param array $row 支付单表的一行，payload 可以是 JSON 字符串
     * @return self
     */
    public static function fromArray(array $row): self
    {
        $order = new self();
        $order->id = (int)($row['id'] ?? 0);
        $order->payNo = (string)($row['pay_no'] ?? '');
        $order->tenantId = (int)($row['tenant_id'] ?? 0);
        $order->bizType = (string)($row['biz_type'] ?? '');
        $order->payload = self::decodePayload($row['payload'] ?? []);
        $order->subject = (string)($row['subject'] ?? '');
        $order->amount = Money::normalize((string)($row['amount'] ?? ''));
        $order->status = (int)($row['status'] ?? PaymentStatus::PENDING);
        $order->expireAt = (int)($row['expire_at'] ?? 0);
        if ($order->payNo === '' || $order->bizType === '') {
            throw new PaymentException('支付单数据不完整');
        }
        return $order;
    }

    /**
     * @param mixed $payload
     * @return array
     */
    private static function decodePayload($payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }
        $decoded = json_decode((string)$payload, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 还能不能付：待支付且没过期
     * @param int $now
     * @return bool
     */
    public function isPayable(int $now): bool
    {
        return $this->status === PaymentStatus::PENDING && $now < $this->expireAt;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function payNo(): string
    {
        return $this->payNo;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function bizType(): string
    {
        return $this->bizType;
    }

    /**
     * 下单时的业务快照，如套餐与月数；履约以快照为准，不读下单后才改的套餐价
     * @return array
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function amount(): string
    {
        return $this->amount;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function expireAt(): int
    {
        return $this->expireAt;
    }
}
