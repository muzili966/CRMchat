<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\controller\pay;

use app\services\payment\PaymentSettleServices;
use crmeb\services\payment\dto\NotifyPayload;
use think\Request;
use think\Response;

/**
 * 支付渠道到账回调
 *
 * 渠道只看 HTTP 状态码与应答正文决定是否重发，所以这里不走统一的 JSON 响应格式。
 * Class Notify
 * @package app\controller\pay
 */
class Notify
{
    const HTTP_OK = 200;

    /**
     * 非 2xx 渠道才会重发
     */
    const HTTP_FAIL = 500;

    const HTTP_NOT_FOUND = 404;

    const FORM_CONTENT_TYPE = 'application/x-www-form-urlencoded';

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var PaymentSettleServices
     */
    protected $services;

    /**
     * @param Request $request
     * @param PaymentSettleServices $services
     */
    public function __construct(Request $request, PaymentSettleServices $services)
    {
        $this->request = $request;
        $this->services = $services;
    }

    /**
     * @param string $channel
     * @return Response
     */
    public function handle(string $channel)
    {
        if (!$this->services->hasChannel($channel)) {
            return Response::create('', 'html', self::HTTP_NOT_FOUND);
        }
        $result = $this->services->handleNotify($channel, $this->payload());
        return Response::create($result['body'], 'html', $result['ok'] ? self::HTTP_OK : self::HTTP_FAIL);
    }

    /**
     * 回调原文绕开框架的参数过滤：验签按原始内容算，过滤器改掉一个字符都会验不过
     * @return NotifyPayload
     */
    protected function payload(): NotifyPayload
    {
        parse_str((string)$this->request->query(), $params);
        $body = (string)$this->request->getContent();
        //支付宝回调是表单提交，参数在正文里；微信是 JSON 正文，由渠道自己解析
        if (stripos((string)$this->request->header('content-type', ''), self::FORM_CONTENT_TYPE) !== false) {
            parse_str($body, $form);
            $params = array_merge($params, $form);
        }
        return new NotifyPayload($params, $body, (array)$this->request->header());
    }
}
