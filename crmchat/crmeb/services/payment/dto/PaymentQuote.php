<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment\dto;

use crmeb\services\payment\Money;
use crmeb\services\payment\PaymentException;

/**
 * 业务报价
 *
 * payload 会原样存进支付单作为快照，履约时以它为准：下单后平台调了价，
 * 已经发出去的链接仍按下单时的价格与权益开通，不会多收也不会少给。
 */
final class PaymentQuote
{
    private $subject;
    private $amount;
    private $payload;

    /**
     * @param string $subject 标题，如「旗舰版续费 3 个月」
     * @param string $amount 金额（元）
     * @param array $payload 履约所需的业务快照
     */
    public function __construct(string $subject, string $amount, array $payload)
    {
        if (trim($subject) === '') {
            throw new PaymentException('报价缺少标题');
        }
        $this->subject = trim($subject);
        $this->amount = Money::normalize($amount);
        $this->payload = $payload;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function amount(): string
    {
        return $this->amount;
    }

    public function payload(): array
    {
        return $this->payload;
    }
}
