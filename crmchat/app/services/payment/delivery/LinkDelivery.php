<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\payment\delivery;

use crmeb\services\payment\contract\DeliveryInterface;
use crmeb\services\payment\dto\PaymentOrder;
use crmeb\services\payment\PaymentException;

/**
 * 支付链接：复制出去发微信、邮件都行
 * Class LinkDelivery
 * @package app\services\payment\delivery
 */
class LinkDelivery implements DeliveryInterface
{
    const CODE = 'link';

    /**
     * @return string
     */
    public function code(): string
    {
        return self::CODE;
    }

    /**
     * @param PaymentOrder $order
     * @param array $context cashier_url
     * @return array url
     */
    public function deliver(PaymentOrder $order, array $context): array
    {
        return ['url' => self::requireUrl($context)];
    }

    /**
     * 各投递方式都以收银台地址为落点，由支付服务统一签发后放进上下文
     * @param array $context
     * @return string
     */
    public static function requireUrl(array $context): string
    {
        $url = (string)($context['cashier_url'] ?? '');
        if ($url === '') {
            throw new PaymentException('缺少收银台地址');
        }
        return $url;
    }
}
