<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment;

use crmeb\services\payment\contract\DeliveryInterface;
use crmeb\services\payment\contract\PayableInterface;
use crmeb\services\payment\contract\PaymentChannelInterface;

/**
 * 支付组件注册中心
 *
 * 渠道、业务、投递方式三类组件都在 config/payment.php 里按「编码 => 类名」注册。
 * 扩展一种能力 = 实现对应接口 + 在配置里加一行，核心流程不改。
 *
 * 实例按编码缓存：支付是平台对租户收款，凭据是平台级的、与租户上下文无关，
 * 在 Swoole 常驻进程里复用是安全的。
 */
class PaymentManager
{
    const KIND_CHANNEL = 'channels';
    const KIND_PAYABLE = 'payables';
    const KIND_DELIVERY = 'deliveries';

    /**
     * 每类组件必须实现的接口
     */
    const CONTRACTS = [
        self::KIND_CHANNEL  => PaymentChannelInterface::class,
        self::KIND_PAYABLE  => PayableInterface::class,
        self::KIND_DELIVERY => DeliveryInterface::class,
    ];

    /**
     * @var array
     */
    private $config;

    /**
     * @var callable 类名 => 实例，默认走容器以支持依赖注入
     */
    private $resolver;

    /**
     * @var array
     */
    private $instances = [];

    /**
     * @param array $config channels/payables/deliveries
     * @param callable|null $resolver 测试可注入，不传则由容器构造
     */
    public function __construct(array $config, ?callable $resolver = null)
    {
        $this->config = $config;
        $this->resolver = $resolver ?: function (string $class) {
            return app()->make($class);
        };
    }

    public function channel(string $code): PaymentChannelInterface
    {
        return $this->resolve(self::KIND_CHANNEL, $code);
    }

    public function payable(string $bizType): PayableInterface
    {
        return $this->resolve(self::KIND_PAYABLE, $bizType);
    }

    public function delivery(string $code): DeliveryInterface
    {
        return $this->resolve(self::KIND_DELIVERY, $code);
    }

    /**
     * 某个场景下可用的渠道，收银台据此列出付款人能选的方式
     *
     * 凭据没配齐的渠道不列出：让付款人点进去才报「未配置」，比不给入口更糟。
     * @param string $scene
     * @return PaymentChannelInterface[] 编码 => 渠道
     */
    public function channelsFor(string $scene): array
    {
        $codes = array_keys((array)($this->config[self::KIND_CHANNEL] ?? []));
        $channels = array_map(function (string $code) {
            return $this->channel($code);
        }, $codes);
        return array_filter(array_combine($codes, $channels), function (PaymentChannelInterface $channel) use ($scene) {
            return $channel->available() && in_array($scene, $channel->scenes(), true);
        });
    }

    /**
     * @param string $kind
     * @param string $code
     * @return mixed
     */
    protected function resolve(string $kind, string $code)
    {
        if (isset($this->instances[$kind][$code])) {
            return $this->instances[$kind][$code];
        }
        $class = (string)($this->config[$kind][$code] ?? '');
        if ($class === '' || !class_exists($class)) {
            throw new PaymentException("未注册的支付组件：{$kind}.{$code}");
        }
        $instance = ($this->resolver)($class);
        $contract = self::CONTRACTS[$kind];
        if (!$instance instanceof $contract) {
            throw new PaymentException("{$class} 未实现 {$contract}");
        }
        //注册键与实例自报的编码必须一致：否则回调按编码找渠道时会找错人
        if (self::identity($instance) !== $code) {
            throw new PaymentException("{$class} 的编码与注册键 {$code} 不一致");
        }
        return $this->instances[$kind][$code] = $instance;
    }

    /**
     * 组件自报的编码
     * @param PaymentChannelInterface|PayableInterface|DeliveryInterface $instance
     * @return string
     */
    private static function identity($instance): string
    {
        return $instance instanceof PayableInterface ? $instance->bizType() : $instance->code();
    }
}
