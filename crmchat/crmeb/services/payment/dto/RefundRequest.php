<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment\dto;

use crmeb\services\payment\Money;
use crmeb\services\payment\PaymentException;

/**
 * 退款请求
 *
 * 带上原支付金额：微信退款接口要求同时给出原单总额，支付宝不需要但也不妨碍。
 * 原金额取自本平台的支付单，不去渠道查，退款时少一次网络往返也少一个失败点。
 */
final class RefundRequest
{
    /**
     * 退款单号：微信、支付宝上限都是 64 位
     */
    const REFUND_NO_PATTERN = '/^[A-Za-z0-9_-]{6,64}$/';

    /**
     * 退款原因字数上限：微信 80 字，取最严的
     */
    const REASON_MAX_CHARS = 80;

    private $payNo;
    private $refundNo;
    private $amount;
    private $totalAmount;
    private $reason;

    private function __construct()
    {
    }

    /**
     * @param array $data pay_no/refund_no/amount/total_amount/reason
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $req = new self();
        $req->payNo = (string)($data['pay_no'] ?? '');
        $req->refundNo = (string)($data['refund_no'] ?? '');
        if (!preg_match(PaymentRequest::PAY_NO_PATTERN, $req->payNo) || !preg_match(self::REFUND_NO_PATTERN, $req->refundNo)) {
            throw new PaymentException('支付单号或退款单号不合法');
        }
        $req->amount = Money::normalize($data['amount'] ?? '');
        $req->totalAmount = Money::normalize($data['total_amount'] ?? '');
        //退得比收的多是资金事故，必须在进渠道之前拦住
        if (bccomp($req->amount, $req->totalAmount, Money::SCALE) > 0) {
            throw new PaymentException('退款金额不能超过原支付金额');
        }
        $req->reason = mb_substr(trim((string)($data['reason'] ?? '')), 0, self::REASON_MAX_CHARS);
        return $req;
    }

    public function payNo(): string
    {
        return $this->payNo;
    }

    public function refundNo(): string
    {
        return $this->refundNo;
    }

    public function amount(): string
    {
        return $this->amount;
    }

    public function totalAmount(): string
    {
        return $this->totalAmount;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
