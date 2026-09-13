<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment\http;

use crmeb\services\payment\PaymentException;
use GuzzleHttp\Client;

/**
 * 基于 Guzzle 的传输层
 */
class GuzzleTransport implements HttpTransportInterface
{
    /**
     * 建连超时（秒）：渠道网关不通时尽快失败，别把 Swoole 工作进程拖住
     */
    const CONNECT_TIMEOUT = 5;

    /**
     * 整体超时（秒）
     */
    const TIMEOUT = 15;

    /**
     * @var Client
     */
    private $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    /**
     * @param string $method
     * @param string $url
     * @param array $options
     * @return HttpResponse
     */
    public function request(string $method, string $url, array $options): HttpResponse
    {
        $options = array_merge([
            //非 2xx 不抛异常，交给渠道按各自错误码解读
            'http_errors' => false,
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'timeout' => self::TIMEOUT,
        ], $options);
        try {
            $res = $this->client->request($method, $url, $options);
        } catch (\Exception $e) {
            //网络层失败向上抛：调用方要区分「渠道拒单」与「根本没连上」
            throw new PaymentException('支付渠道请求失败：' . $e->getMessage(), 0, $e);
        }
        $headers = array_map(function (array $values) {
            return implode(', ', $values);
        }, $res->getHeaders());
        return new HttpResponse($res->getStatusCode(), (string)$res->getBody(), $headers);
    }
}
