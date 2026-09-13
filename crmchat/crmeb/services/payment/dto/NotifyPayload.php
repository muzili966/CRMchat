<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment\dto;

/**
 * 回调原始报文
 *
 * 不同渠道把签名放在不同地方：支付宝在表单字段里，微信 v3 在请求头里、正文是
 * 加密 JSON。三样都原样交给渠道，模块不替渠道做任何解析。
 */
final class NotifyPayload
{
    private $query;
    private $body;
    private $headers;

    /**
     * @param array $query 表单或查询参数
     * @param string $body 原始请求体，验签必须用原文，不能用解析后再序列化的
     * @param array $headers 请求头
     */
    public function __construct(array $query, string $body, array $headers)
    {
        $this->query = $query;
        $this->body = $body;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
    }

    public function query(): array
    {
        return $this->query;
    }

    public function body(): string
    {
        return $this->body;
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
