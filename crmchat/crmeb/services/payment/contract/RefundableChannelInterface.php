<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment\contract;

use crmeb\services\payment\dto\RefundRequest;

/**
 * 可退款的支付渠道
 *
 * 退款单独成接口：人工收款这类渠道根本没有原路退回的能力，不该被迫实现一个
 * 什么都不做的空方法。调用方用 instanceof 判断能力，而不是调用后才发现不支持。
 */
interface RefundableChannelInterface extends PaymentChannelInterface
{
    /**
     * 原路退款
     *
     * 同一个退款单号重复提交，渠道只会退一次，失败重试不会多退。
     * @param RefundRequest $request
     * @return string 退款单号：渠道有独立退款单号时返回它，否则返回请求里的退款单号
     */
    public function refund(RefundRequest $request): string;
}
