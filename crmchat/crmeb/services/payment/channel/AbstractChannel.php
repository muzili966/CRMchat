<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment\channel;

use crmeb\services\payment\contract\PaymentChannelInterface;
use crmeb\services\payment\http\GuzzleTransport;
use crmeb\services\payment\http\HttpTransportInterface;
use crmeb\services\payment\PaymentException;

/**
 * 渠道基类：配置读取与传输层注入
 *
 * 构造参数只收数组、不声明类类型：ThinkPHP 容器遇到类类型参数一律自己去实例化，
 * 不看默认值，声明了接口类型就没法直接 app()->make()。传输层因此改由 withTransport 注入。
 */
abstract class AbstractChannel implements PaymentChannelInterface
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var HttpTransportInterface|null
     */
    private $http;

    /**
     * @param array $config 不传则从环境变量读取；凭据随环境走且属敏感信息，不入库
     */
    public function __construct(array $config = [])
    {
        $this->config = $config ?: static::envConfig();
    }

    /**
     * 从环境变量读取凭据
     * @return array
     */
    abstract protected static function envConfig(): array;

    /**
     * 注入传输层，返回新实例，不改动已被注册中心缓存的那个
     * @param HttpTransportInterface $http
     * @return static
     */
    public function withTransport(HttpTransportInterface $http): self
    {
        $clone = clone $this;
        $clone->http = $http;
        return $clone;
    }

    /**
     * @return HttpTransportInterface
     */
    protected function http(): HttpTransportInterface
    {
        if (!$this->http) {
            $this->http = new GuzzleTransport();
        }
        return $this->http;
    }

    /**
     * 必填配置，缺了在用到时报出具体是哪一项
     * @param string $key
     * @return string
     */
    protected function cfg(string $key): string
    {
        $value = trim((string)($this->config[$key] ?? ''));
        if ($value === '') {
            throw new PaymentException(sprintf('%s 缺少配置项 %s', $this->name(), $key));
        }
        return $value;
    }

    /**
     * 选填配置
     * @param string $key
     * @param string $default
     * @return string
     */
    protected function opt(string $key, string $default): string
    {
        $value = trim((string)($this->config[$key] ?? ''));
        return $value === '' ? $default : $value;
    }

    /**
     * 这些配置项是否都已填写
     * @param string[] $keys
     * @return bool
     */
    protected function hasConfig(array $keys): bool
    {
        return array_reduce($keys, function (bool $ok, string $key) {
            return $ok && trim((string)($this->config[$key] ?? '')) !== '';
        }, true);
    }
}
