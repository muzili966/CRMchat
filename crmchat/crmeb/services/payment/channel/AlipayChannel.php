<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment\channel;

use crmeb\services\payment\contract\RefundableChannelInterface;
use crmeb\services\payment\dto\NotifyPayload;
use crmeb\services\payment\dto\PaymentNotice;
use crmeb\services\payment\dto\PaymentRequest;
use crmeb\services\payment\dto\PaymentResult;
use crmeb\services\payment\dto\RefundRequest;
use crmeb\services\payment\PaymentException;
use crmeb\services\payment\PaymentScene;
use crmeb\services\payment\support\ChinaTime;
use crmeb\services\payment\support\Rsa;
use think\facade\Env;

/**
 * 支付宝（开放平台公钥模式，RSA2）
 *
 * 没用仓库里的 EasySDK：它靠静态 Factory 持有全局配置，在 Swoole 常驻进程里是共享状态，
 * 也没法注入 HTTP 层做离线测试。开放平台协议本身不复杂，直接按文档签名调用，
 * 与微信渠道保持同一套结构。
 */
class AlipayChannel extends AbstractChannel implements RefundableChannelInterface
{
    const CODE = 'alipay';

    const GATEWAY = 'https://openapi.alipay.com/gateway.do';

    const CODE_SUCCESS = '10000';

    /**
     * 付款人从没扫过码时，支付宝侧根本没有这笔交易
     */
    const SUB_CODE_NOT_EXIST = 'ACQ.TRADE_NOT_EXIST';

    /**
     * 这两种状态都代表钱已到账：TRADE_FINISHED 是过了可退款期之后的终态
     */
    const PAID_STATES = ['TRADE_SUCCESS', 'TRADE_FINISHED'];

    /**
     * 场景 => [接口, 产品码]：扫码走当面付预下单，网页走跳转
     */
    const SCENE_METHODS = [
        PaymentScene::QRCODE => ['alipay.trade.precreate', ''],
        PaymentScene::PC     => ['alipay.trade.page.pay', 'FAST_INSTANT_TRADE_PAY'],
        PaymentScene::H5     => ['alipay.trade.wap.pay', 'QUICK_WAP_WAY'],
    ];

    const TIME_FORMAT = 'Y-m-d H:i:s';

    public function code(): string
    {
        return self::CODE;
    }

    public function name(): string
    {
        return '支付宝';
    }

    public function scenes(): array
    {
        return array_keys(self::SCENE_METHODS);
    }

    public function available(): bool
    {
        return $this->hasConfig(['app_id', 'private_key', 'alipay_public_key']);
    }

    /**
     * @param PaymentRequest $request
     * @return PaymentResult
     */
    public function create(PaymentRequest $request): PaymentResult
    {
        $scene = $request->scene();
        if (!isset(self::SCENE_METHODS[$scene])) {
            throw new PaymentException('支付宝不支持该支付场景');
        }
        [$method, $productCode] = self::SCENE_METHODS[$scene];
        $biz = array_filter([
            'out_trade_no' => $request->payNo(),
            'total_amount' => $request->amount(),
            'subject' => $request->subject(),
            'product_code' => $productCode,
            //渠道侧同步过期，过期后付款人对着旧码也付不了
            'time_expire' => ChinaTime::format($request->expireAt(), self::TIME_FORMAT),
        ], 'strlen');
        $params = $this->signedParams($method, $biz, ['notify_url' => $request->notifyUrl(), 'return_url' => $request->returnUrl()]);
        if ($scene !== PaymentScene::QRCODE) {
            //网页支付由付款人浏览器带着签名参数直接去支付宝，服务端不发请求
            return PaymentResult::redirect($this->gateway() . '?' . http_build_query($params));
        }
        $resp = $this->call($method, $params);
        $this->assertSuccess($resp);
        return PaymentResult::qrcode((string)($resp['qr_code'] ?? ''));
    }

    /**
     * @param string $payNo
     * @return PaymentNotice|null
     */
    public function query(string $payNo): ?PaymentNotice
    {
        $method = 'alipay.trade.query';
        $resp = $this->call($method, $this->signedParams($method, ['out_trade_no' => $payNo], []));
        if (($resp['sub_code'] ?? '') === self::SUB_CODE_NOT_EXIST) {
            return null;
        }
        $this->assertSuccess($resp);
        if (!in_array($resp['trade_status'] ?? '', self::PAID_STATES, true)) {
            return null;
        }
        return $this->noticeFrom($resp, (string)($resp['send_pay_date'] ?? ''));
    }

    /**
     * @param NotifyPayload $payload
     * @return PaymentNotice|null
     */
    public function parseNotify(NotifyPayload $payload): ?PaymentNotice
    {
        $params = $payload->query();
        $content = self::signContent($params, ['sign', 'sign_type']);
        if (!Rsa::verify($content, (string)($params['sign'] ?? ''), $this->cfg('alipay_public_key'))) {
            throw new PaymentException('支付宝回调验签失败');
        }
        //验签只证明报文出自支付宝，不证明是发给本平台这个应用的
        if ((string)($params['app_id'] ?? '') !== $this->cfg('app_id')) {
            throw new PaymentException('支付宝回调的应用号与本平台不符');
        }
        if (!in_array($params['trade_status'] ?? '', self::PAID_STATES, true)) {
            return null;
        }
        return $this->noticeFrom($params, (string)($params['gmt_payment'] ?? ''));
    }

    public function notifyAck(bool $success): string
    {
        return $success ? 'success' : 'failure';
    }

    /**
     * @param string $payNo
     * @return void
     */
    public function close(string $payNo): void
    {
        $method = 'alipay.trade.close';
        $resp = $this->call($method, $this->signedParams($method, ['out_trade_no' => $payNo], []));
        //付款人从没扫过码时支付宝侧没有这笔交易，关单等于已经达成目的
        if (($resp['sub_code'] ?? '') !== self::SUB_CODE_NOT_EXIST) {
            $this->assertSuccess($resp);
        }
    }

    /**
     * @param RefundRequest $request
     * @return string
     */
    public function refund(RefundRequest $request): string
    {
        $method = 'alipay.trade.refund';
        $resp = $this->call($method, $this->signedParams($method, array_filter([
            'out_trade_no' => $request->payNo(),
            'refund_amount' => $request->amount(),
            //同一个退款请求号重复提交只会退一次
            'out_request_no' => $request->refundNo(),
            'refund_reason' => $request->reason(),
        ], 'strlen'), []));
        $this->assertSuccess($resp);
        return $request->refundNo();
    }

    /**
     * 待签字符串：参数按键名排序，剔除空值与指定键，拼成 k=v&k=v
     *
     * 请求签名只剔除 sign，回调验签还要剔除 sign_type，两边规则不对称，由调用方指定。
     * @param array $params
     * @param string[] $exclude
     * @return string
     */
    public static function signContent(array $params, array $exclude): string
    {
        $filtered = array_filter($params, function ($value, $key) use ($exclude) {
            return !in_array($key, $exclude, true) && is_scalar($value) && (string)$value !== '';
        }, ARRAY_FILTER_USE_BOTH);
        ksort($filtered);
        return implode('&', array_map(function ($key, $value) {
            return $key . '=' . $value;
        }, array_keys($filtered), $filtered));
    }

    /**
     * @param string $method
     * @param array $biz
     * @param array $extra 公共参数里的 notify_url、return_url
     * @return array
     */
    private function signedParams(string $method, array $biz, array $extra): array
    {
        $params = array_merge([
            'app_id' => $this->cfg('app_id'),
            'method' => $method,
            'format' => 'JSON',
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => ChinaTime::format(time(), self::TIME_FORMAT),
            'version' => '1.0',
            'biz_content' => (string)json_encode($biz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ], array_filter($extra, 'strlen'));
        $params['sign'] = Rsa::sign(self::signContent($params, ['sign']), $this->cfg('private_key'));
        return $params;
    }

    /**
     * 调用开放平台接口并验签应答
     * @param string $method
     * @param array $params
     * @return array 应答节点
     */
    private function call(string $method, array $params): array
    {
        $raw = $this->http()->request('POST', $this->gateway(), ['form_params' => $params])->body();
        $node = str_replace('.', '_', $method) . '_response';
        $data = json_decode($raw, true);
        if (!is_array($data) || !is_array($data[$node] ?? null)) {
            throw new PaymentException('支付宝应答无法解析');
        }
        $sign = (string)($data['sign'] ?? '');
        //查单结果会直接决定开不开通权益，带签名的应答必须验
        if ($sign !== '' && !Rsa::verify(self::rawNode($raw, $node), $sign, $this->cfg('alipay_public_key'))) {
            throw new PaymentException('支付宝应答验签失败');
        }
        //部分参数错误的应答确实不带签名，但成功应答不带签名就是被篡改了
        if ($sign === '' && (string)($data[$node]['code'] ?? '') === self::CODE_SUCCESS) {
            throw new PaymentException('支付宝成功应答缺少签名');
        }
        return $data[$node];
    }

    /**
     * @param array $resp
     * @return void
     */
    private function assertSuccess(array $resp): void
    {
        if ((string)($resp['code'] ?? '') !== self::CODE_SUCCESS) {
            throw new PaymentException('支付宝：' . (string)($resp['sub_msg'] ?? $resp['msg'] ?? '请求失败'));
        }
    }

    /**
     * @param array $trade 回调参数或查单应答
     * @param string $paidAt 北京时间，不带时区
     * @return PaymentNotice
     */
    private function noticeFrom(array $trade, string $paidAt): PaymentNotice
    {
        return PaymentNotice::fromArray([
            'pay_no' => (string)($trade['out_trade_no'] ?? ''),
            'trade_no' => (string)($trade['trade_no'] ?? ''),
            'amount' => (string)($trade['total_amount'] ?? ''),
            'paid_at' => ChinaTime::parse($paidAt),
            'raw' => $trade,
        ]);
    }

    private function gateway(): string
    {
        return $this->opt('gateway', self::GATEWAY);
    }

    /**
     * 从应答原文里截出某个节点的 JSON 原文
     *
     * 支付宝的应答签名针对的是原始片段，json_decode 后再 encode 会改变转义与空白，
     * 验签必然失败，只能按括号配对从原文里截。
     * @param string $raw
     * @param string $node
     * @return string
     */
    private static function rawNode(string $raw, string $node): string
    {
        $keyPos = strpos($raw, '"' . $node . '"');
        $start = $keyPos === false ? false : strpos($raw, '{', $keyPos);
        if ($start === false) {
            return '';
        }
        $depth = 0;
        $inString = false;
        for ($i = $start, $len = strlen($raw); $i < $len; $i++) {
            $ch = $raw[$i];
            if ($inString) {
                $i += $ch === '\\' ? 1 : 0;
                $inString = $ch !== '"';
                continue;
            }
            $inString = $ch === '"';
            $depth += $ch === '{' ? 1 : ($ch === '}' ? -1 : 0);
            if ($depth === 0) {
                return substr($raw, $start, $i - $start + 1);
            }
        }
        return '';
    }

    protected static function envConfig(): array
    {
        return [
            'app_id' => Env::get('PAY_ALIPAY_APP_ID', ''),
            'private_key' => Env::get('PAY_ALIPAY_PRIVATE_KEY', ''),
            'alipay_public_key' => Env::get('PAY_ALIPAY_PUBLIC_KEY', ''),
            'gateway' => Env::get('PAY_ALIPAY_GATEWAY', ''),
        ];
    }
}
