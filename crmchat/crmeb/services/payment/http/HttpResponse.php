<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment\http;

/**
 * 渠道应答
 *
 * 正文保留原文：支付宝的应答签名针对的是原始 JSON 片段，解析后再序列化就验不过了。
 */
final class HttpResponse
{
    private $status;
    private $body;
    private $headers;

    /**
     * @param int $status
     * @param string $body
     * @param array $headers
     */
    public function __construct(int $status, string $body, array $headers)
    {
        $this->status = $status;
        $this->body = $body;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * 按名取请求头，大小写不敏感
     * @param string $name
     * @return string
     */
    public function header(string $name): string
    {
        $value = $this->headers[strtolower($name)] ?? '';
        return is_array($value) ? (string)reset($value) : (string)$value;
    }
}
