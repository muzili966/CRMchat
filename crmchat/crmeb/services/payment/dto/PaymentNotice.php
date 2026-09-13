<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment\dto;

use crmeb\services\payment\Money;
use crmeb\services\payment\PaymentException;

/**
 * 到账通知（渠道回调与主动查单共用）
 *
 * 各渠道的回调字段五花八门，渠道实现负责验签并翻译成这个统一形状，
 * 下游的状态机与履约只认它。
 */
final class PaymentNotice
{
    private $payNo;
    private $tradeNo;
    private $amount;
    private $paidAt;
    private $raw;

    private function __construct()
    {
    }

    /**
     * @param array $data pay_no/trade_no/amount/paid_at/raw
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $notice = new self();
        $notice->payNo = (string)($data['pay_no'] ?? '');
        $notice->tradeNo = (string)($data['trade_no'] ?? '');
        if ($notice->payNo === '' || $notice->tradeNo === '') {
            throw new PaymentException('到账通知缺少单号');
        }
        $notice->amount = Money::normalize($data['amount'] ?? '');
        $notice->paidAt = (int)($data['paid_at'] ?? 0);
        if ($notice->paidAt <= 0) {
            throw new PaymentException('到账通知缺少支付时间');
        }
        //原始报文留存备查：出现争议时要能拿出渠道当时发来的原文
        $notice->raw = (array)($data['raw'] ?? []);
        return $notice;
    }

    /**
     * 到账金额是否与下单金额一致
     *
     * 验签只证明消息确实来自渠道，不证明这笔钱付够了。金额不符一律不履约。
     * @param string $expected
     * @return bool
     */
    public function matchesAmount(string $expected): bool
    {
        return Money::equals($this->amount, $expected);
    }

    public function payNo(): string
    {
        return $this->payNo;
    }

    public function tradeNo(): string
    {
        return $this->tradeNo;
    }

    public function amount(): string
    {
        return $this->amount;
    }

    public function paidAt(): int
    {
        return $this->paidAt;
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
