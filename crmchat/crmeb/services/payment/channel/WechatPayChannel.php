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
use crmeb\services\payment\http\HttpResponse;
use crmeb\services\payment\Money;
use crmeb\services\payment\PaymentException;
use crmeb\services\payment\PaymentScene;
use crmeb\services\payment\support\ChinaTime;
use crmeb\services\payment\support\Rsa;
use think\facade\Env;

/**
 * 微信支付（API v3，平台公钥模式）
 *
 * 仓库里没有微信支付 SDK，按 v3 协议直接对接：请求用商户私钥签名，应答与回调用
 * 微信支付平台公钥验签，回调正文用 APIv3 密钥 AES-256-GCM 解密。
 * 平台证书模式与平台公钥模式验签算法相同，只是公钥来源不同，这里统一按公钥处理。
 */
class WechatPayChannel extends AbstractChannel implements RefundableChannelInterface
{
    const CODE = 'wechat';

    const BASE_URI = 'https://api.mch.weixin.qq.com';

    const AUTH_SCHEMA = 'WECHATPAY2-SHA256-RSA2048';

    /**
     * 签名时间戳允许的偏差（秒），超出视为重放
     */
    const SIGN_TTL = 300;

    const TRADE_SUCCESS = 'SUCCESS';

    const API_V3_KEY_LENGTH = 32;

    const GCM_TAG_LENGTH = 16;

    const HTTP_NOT_FOUND = 404;

    /**
     * 场景 => [下单接口, 应答里的支付内容字段]
     * JSAPI 需要付款人 openid，本平台的付款人是租户管理员而非微信用户，暂不支持
     */
    const SCENE_PATHS = [
        PaymentScene::QRCODE => ['/v3/pay/transactions/native', 'code_url'],
        PaymentScene::H5     => ['/v3/pay/transactions/h5', 'h5_url'],
    ];

    public function code(): string
    {
        return self::CODE;
    }

    public function name(): string
    {
        return '微信支付';
    }

    public function scenes(): array
    {
        return array_keys(self::SCENE_PATHS);
    }

    public function available(): bool
    {
        return $this->hasConfig(['appid', 'mchid', 'serial_no', 'private_key', 'api_v3_key', 'platform_public_key', 'platform_serial']);
    }

    /**
     * @param PaymentRequest $request
     * @return PaymentResult
     */
    public function create(PaymentRequest $request): PaymentResult
    {
        $scene = $request->scene();
        if (!isset(self::SCENE_PATHS[$scene])) {
            throw new PaymentException('微信支付不支持该支付场景');
        }
        [$path, $field] = self::SCENE_PATHS[$scene];
        $data = $this->decode($this->send('POST', $path, $this->orderBody($request)));
        $content = (string)($data[$field] ?? '');
        return $scene === PaymentScene::QRCODE ? PaymentResult::qrcode($content) : PaymentResult::redirect($content);
    }

    /**
     * @param string $payNo
     * @return PaymentNotice|null
     */
    public function query(string $payNo): ?PaymentNotice
    {
        $path = '/v3/pay/transactions/out-trade-no/' . rawurlencode($payNo) . '?mchid=' . rawurlencode($this->cfg('mchid'));
        $res = $this->send('GET', $path, null);
        if ($res->status() === self::HTTP_NOT_FOUND) {
            return null;
        }
        $trade = $this->decode($res);
        return ($trade['trade_state'] ?? '') === self::TRADE_SUCCESS ? $this->noticeFrom($trade) : null;
    }

    /**
     * @param NotifyPayload $payload
     * @return PaymentNotice|null
     */
    public function parseNotify(NotifyPayload $payload): ?PaymentNotice
    {
        $this->verifySigned($payload);
        $event = json_decode($payload->body(), true);
        $resource = $this->decrypt(is_array($event['resource'] ?? null) ? $event['resource'] : []);
        //退款等其他事件也会打到同一个地址，不是支付成功就只应答不履约
        return ($resource['trade_state'] ?? '') === self::TRADE_SUCCESS ? $this->noticeFrom($resource) : null;
    }

    /**
     * 成功应答 200 即可，失败要带 FAIL 让微信重发
     * @param bool $success
     * @return string
     */
    public function notifyAck(bool $success): string
    {
        return (string)json_encode([
            'code' => $success ? 'SUCCESS' : 'FAIL',
            'message' => $success ? '成功' : '失败',
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param string $payNo
     * @return void
     */
    public function close(string $payNo): void
    {
        $path = '/v3/pay/transactions/out-trade-no/' . rawurlencode($payNo) . '/close';
        $res = $this->send('POST', $path, ['mchid' => $this->cfg('mchid')]);
        //微信侧没有这笔交易时关单等于已经达成目的
        if ($res->status() !== self::HTTP_NOT_FOUND) {
            $this->decode($res);
        }
    }

    /**
     * @param RefundRequest $request
     * @return string
     */
    public function refund(RefundRequest $request): string
    {
        $body = array_filter([
            'out_trade_no' => $request->payNo(),
            //同一个退款单号重复提交只会退一次
            'out_refund_no' => $request->refundNo(),
            'reason' => $request->reason(),
            'amount' => [
                'refund' => Money::toCents($request->amount()),
                'total' => Money::toCents($request->totalAmount()),
                'currency' => 'CNY',
            ],
        ]);
        $data = $this->decode($this->send('POST', '/v3/refund/domestic/refunds', $body));
        return (string)($data['refund_id'] ?? $request->refundNo());
    }

    /**
     * @param PaymentRequest $request
     * @return array
     */
    private function orderBody(PaymentRequest $request): array
    {
        $body = [
            'appid' => $this->cfg('appid'),
            'mchid' => $this->cfg('mchid'),
            'description' => $request->subject(),
            'out_trade_no' => $request->payNo(),
            'time_expire' => ChinaTime::format($request->expireAt(), DATE_RFC3339),
            'notify_url' => $request->notifyUrl(),
            'amount' => ['total' => Money::toCents($request->amount()), 'currency' => 'CNY'],
        ];
        if ($request->scene() !== PaymentScene::H5) {
            return $body;
        }
        //H5 下单微信要核对付款人 IP，拿不到真实 IP 会被风控拒单
        $ip = (string)($request->extra()['client_ip'] ?? '');
        if ($ip === '') {
            throw new PaymentException('微信 H5 支付需要付款人 IP');
        }
        $body['scene_info'] = ['payer_client_ip' => $ip, 'h5_info' => ['type' => 'Wap']];
        return $body;
    }

    /**
     * 发请求，2xx 应答验签
     * @param string $method
     * @param string $path 含查询串，签名要用它
     * @param array|null $body
     * @return HttpResponse
     */
    private function send(string $method, string $path, ?array $body): HttpResponse
    {
        $json = $body === null ? '' : (string)json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $res = $this->http()->request($method, $this->opt('base_uri', self::BASE_URI) . $path, [
            'headers' => [
                'Authorization' => $this->authorization($method, $path, $json),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => $json,
        ]);
        //应答同样要验签：查单结果会直接决定开不开通权益，不能信被篡改的应答
        if ($res->status() >= 200 && $res->status() < 300) {
            $this->verifySigned(new NotifyPayload([], $res->body(), $res->headers()));
        }
        return $res;
    }

    /**
     * @param HttpResponse $res
     * @return array
     */
    private function decode(HttpResponse $res): array
    {
        $data = $res->body() === '' ? [] : json_decode($res->body(), true);
        if ($res->status() >= 300) {
            throw new PaymentException('微信支付：' . (string)($data['message'] ?? ('HTTP ' . $res->status())));
        }
        return is_array($data) ? $data : [];
    }

    /**
     * 请求头签名
     * @param string $method
     * @param string $path
     * @param string $body
     * @return string
     */
    private function authorization(string $method, string $path, string $body): string
    {
        $timestamp = (string)time();
        $nonce = bin2hex(random_bytes(16));
        $message = "{$method}\n{$path}\n{$timestamp}\n{$nonce}\n{$body}\n";
        return sprintf('%s mchid="%s",nonce_str="%s",signature="%s",timestamp="%s",serial_no="%s"',
            self::AUTH_SCHEMA, $this->cfg('mchid'), $nonce, Rsa::sign($message, $this->cfg('private_key')),
            $timestamp, $this->cfg('serial_no'));
    }

    /**
     * 验签：回调与应答共用
     * @param NotifyPayload $payload
     * @return void
     */
    private function verifySigned(NotifyPayload $payload): void
    {
        $timestamp = $payload->header('Wechatpay-Timestamp');
        if (!ctype_digit($timestamp) || abs(time() - (int)$timestamp) > self::SIGN_TTL) {
            throw new PaymentException('微信支付签名时间戳无效或已过期');
        }
        //平台换钥时序列号会变：宁可报错让运维更新配置，也不能拿旧公钥硬验
        if ($payload->header('Wechatpay-Serial') !== $this->cfg('platform_serial')) {
            throw new PaymentException('微信支付平台公钥序列号与配置不符');
        }
        $message = $timestamp . "\n" . $payload->header('Wechatpay-Nonce') . "\n" . $payload->body() . "\n";
        if (!Rsa::verify($message, $payload->header('Wechatpay-Signature'), $this->cfg('platform_public_key'))) {
            throw new PaymentException('微信支付验签失败');
        }
    }

    /**
     * 解密回调资源：AES-256-GCM，密文末尾 16 字节是认证标签
     * @param array $resource
     * @return array
     */
    private function decrypt(array $resource): array
    {
        $key = $this->cfg('api_v3_key');
        if (strlen($key) !== self::API_V3_KEY_LENGTH) {
            throw new PaymentException('微信支付 APIv3 密钥必须是 32 位');
        }
        $cipher = base64_decode((string)($resource['ciphertext'] ?? ''), true);
        if ($cipher === false || strlen($cipher) <= self::GCM_TAG_LENGTH) {
            throw new PaymentException('微信支付回调缺少密文');
        }
        $plain = openssl_decrypt(substr($cipher, 0, -self::GCM_TAG_LENGTH), 'aes-256-gcm', $key, OPENSSL_RAW_DATA,
            (string)($resource['nonce'] ?? ''), substr($cipher, -self::GCM_TAG_LENGTH), (string)($resource['associated_data'] ?? ''));
        $data = $plain === false ? null : json_decode($plain, true);
        if (!is_array($data)) {
            throw new PaymentException('微信支付回调解密失败');
        }
        return $data;
    }

    /**
     * @param array $trade 解密后的回调资源或查单应答
     * @return PaymentNotice
     */
    private function noticeFrom(array $trade): PaymentNotice
    {
        //验签只证明报文出自微信，不证明是发给本平台这个商户号与应用的
        if (($trade['mchid'] ?? '') !== $this->cfg('mchid') || ($trade['appid'] ?? '') !== $this->cfg('appid')) {
            throw new PaymentException('交易的商户号或应用号与本平台不符');
        }
        return PaymentNotice::fromArray([
            'pay_no' => (string)($trade['out_trade_no'] ?? ''),
            'trade_no' => (string)($trade['transaction_id'] ?? ''),
            'amount' => Money::fromCents((int)($trade['amount']['total'] ?? 0)),
            'paid_at' => ChinaTime::parse((string)($trade['success_time'] ?? '')),
            'raw' => $trade,
        ]);
    }

    protected static function envConfig(): array
    {
        return [
            'appid' => Env::get('PAY_WECHAT_APPID', ''),
            'mchid' => Env::get('PAY_WECHAT_MCHID', ''),
            'serial_no' => Env::get('PAY_WECHAT_SERIAL_NO', ''),
            'private_key' => Env::get('PAY_WECHAT_PRIVATE_KEY', ''),
            'api_v3_key' => Env::get('PAY_WECHAT_API_V3_KEY', ''),
            'platform_public_key' => Env::get('PAY_WECHAT_PLATFORM_PUBLIC_KEY', ''),
            'platform_serial' => Env::get('PAY_WECHAT_PLATFORM_SERIAL', ''),
            'base_uri' => Env::get('PAY_WECHAT_BASE_URI', ''),
        ];
    }
}
