<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment\contract;

use crmeb\services\payment\dto\PaymentOrder;

/**
 * 投递方式：把支付单交到付款人手里
 *
 * 链接、二维码、聊天卡片指向的都是同一个收银台，由付款人在收银台上选渠道。
 * 投递与渠道因此完全解耦：新增一种渠道不用改任何投递方式，新增一种投递方式
 * （短信、邮件、企业微信消息）也不用改任何渠道。
 */
interface DeliveryInterface
{
    /**
     * 投递方式编码，必须与配置里的注册键一致
     * @return string
     */
    public function code(): string;

    /**
     * 投递
     * @param PaymentOrder $order 待支付的单子
     * @param array $context 投递所需上下文，如聊天卡片需要会话双方
     * @return array 投递结果，如链接地址、二维码图片地址、聊天消息记录
     */
    public function deliver(PaymentOrder $order, array $context): array;
}
