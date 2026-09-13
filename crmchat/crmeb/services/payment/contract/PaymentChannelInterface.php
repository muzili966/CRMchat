<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment\contract;

use crmeb\services\payment\dto\NotifyPayload;
use crmeb\services\payment\dto\PaymentNotice;
use crmeb\services\payment\dto\PaymentRequest;
use crmeb\services\payment\dto\PaymentResult;

/**
 * 支付渠道
 *
 * 一个实现对应一家收单机构的一种接入方式（支付宝、微信、人工收款……）。
 * 渠道只做「把已算好的金额交给收单方」与「把收单方的通知翻译成统一格式」，
 * 不计价、不履约、不碰业务表：接一个新渠道时，能出错的地方越少越好。
 */
interface PaymentChannelInterface
{
    /**
     * 渠道编码，必须与配置里的注册键一致
     * @return string
     */
    public function code(): string;

    /**
     * 展示名，收银台上给付款人看
     * @return string
     */
    public function name(): string;

    /**
     * 支持的场景，取值见 PaymentScene
     * @return string[]
     */
    public function scenes(): array;

    /**
     * 凭据是否配齐
     *
     * 没配齐的渠道不出现在收银台上：让付款人点进去才报「未配置」，比不给入口更糟。
     * @return bool
     */
    public function available(): bool;

    /**
     * 下单，拿回二维码内容、跳转地址或收款说明
     * @param PaymentRequest $request
     * @return PaymentResult
     */
    public function create(PaymentRequest $request): PaymentResult;

    /**
     * 主动查单
     *
     * 回调可能丢，也可能被防火墙挡在外面；对账任务与付款人点「我已支付」时靠它兜底。
     * @param string $payNo
     * @return PaymentNotice|null 未支付或渠道侧无此交易返回 null
     */
    public function query(string $payNo): ?PaymentNotice;

    /**
     * 解析并验签回调
     *
     * 三种结果语义不同，调用方据此决定怎么应答：
     * - 抛异常：验签失败或报文不属于本平台，应答失败，绝不能开通权益
     * - 返回 null：报文可信但不是支付成功事件（关单、退款等），应答成功让渠道别再重发，但不履约
     * - 返回通知：支付成功，进入状态机
     * @param NotifyPayload $payload
     * @return PaymentNotice|null
     */
    public function parseNotify(NotifyPayload $payload): ?PaymentNotice;

    /**
     * 回调应答体
     *
     * 各渠道要求的格式不同，回错了渠道会当成没收到，持续重发。
     * HTTP 状态码由调用方按成败设置：成功 200，失败 500。
     * @param bool $success
     * @return string
     */
    public function notifyAck(bool $success): string;

    /**
     * 关单
     *
     * 过期未付的单子要在渠道侧也关掉，否则付款人对着旧二维码仍能付款成功，
     * 钱进来了单子却已作废。
     * @param string $payNo
     * @return void
     */
    public function close(string $payNo): void;
}
