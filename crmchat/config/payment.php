<?php
// +----------------------------------------------------------------------
// | 支付组件注册
// +----------------------------------------------------------------------
// 三类组件按「编码 => 类名」注册，编码必须与组件自报的一致（PaymentManager 会校验）。
// 扩展一种能力 = 实现对应接口 + 在这里加一行，支付单、状态机、回调分发都不用改。
//
// 渠道凭据不写在这里：它们随环境走且属于敏感信息，由各渠道实现自行从 .env 读取。

return [
    // 支付单有效期（秒）：过期后链接、二维码、卡片一并失效，渠道侧同步关单。
    // 续费常要走内部审批或对公转账，给足三天
    'pay_ttl' => 259200,

    // 支付渠道：实现 crmeb\services\payment\contract\PaymentChannelInterface
    // 凭据没配齐的渠道仍可注册，只是不会出现在收银台上；排列顺序即收银台上的展示顺序
    'channels' => [
        'alipay' => \crmeb\services\payment\channel\AlipayChannel::class,
        'wechat' => \crmeb\services\payment\channel\WechatPayChannel::class,
        'manual' => \crmeb\services\payment\channel\ManualChannel::class,
    ],

    // 可付费业务：实现 crmeb\services\payment\contract\PayableInterface
    'payables' => [
        'tenant_plan' => \app\services\payment\TenantPlanPayable::class,
    ],

    // 投递方式：实现 crmeb\services\payment\contract\DeliveryInterface
    'deliveries' => [
        'link' => \app\services\payment\delivery\LinkDelivery::class,
        'qrcode' => \app\services\payment\delivery\QrcodeDelivery::class,
        'chat_card' => \app\services\payment\delivery\ChatCardDelivery::class,
    ],
];
