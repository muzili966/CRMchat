<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment\http;

/**
 * 渠道 HTTP 传输层
 *
 * 渠道只依赖这个接口：线上走 Guzzle，测试注入替身，签名、验签、报文转换
 * 都能在不联网、不需要真实商户号的情况下验证。
 */
interface HttpTransportInterface
{
    /**
     * @param string $method
     * @param string $url
     * @param array $options Guzzle 风格：headers/body/form_params
     * @return HttpResponse 非 2xx 也照常返回，由渠道自己解读错误码
     */
    public function request(string $method, string $url, array $options): HttpResponse;
}
