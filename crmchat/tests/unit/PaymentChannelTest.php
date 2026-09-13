<?php

namespace tests\unit;

use crmeb\services\payment\channel\AlipayChannel;
use crmeb\services\payment\channel\ManualChannel;
use crmeb\services\payment\channel\WechatPayChannel;
use crmeb\services\payment\contract\RefundableChannelInterface;
use crmeb\services\payment\dto\NotifyPayload;
use crmeb\services\payment\dto\PaymentRequest;
use crmeb\services\payment\dto\PaymentResult;
use crmeb\services\payment\dto\RefundRequest;
use crmeb\services\payment\http\HttpResponse;
use crmeb\services\payment\http\HttpTransportInterface;
use crmeb\services\payment\PaymentException;
use crmeb\services\payment\PaymentManager;
use crmeb\services\payment\PaymentScene;
use crmeb\services\payment\support\ChinaTime;
use crmeb\services\payment\support\Rsa;
use PHPUnit\Framework\TestCase;

/**
 * 支付渠道测试
 *
 * 不联网、不需要真实商户号：用两对测试密钥分别扮演「商户」与「支付平台」，
 * 签名、验签、解密、报文转换都能离线验证。
 * fixtures/payment 下的密钥是专为测试生成的，与任何真实账户无关。
 */
class PaymentChannelTest extends TestCase
{
    const APP_ID = '2021000000000001';
    const WX_APPID = 'wx0000000000000001';
    const MCHID = '1900000001';
    const API_V3_KEY = '0123456789abcdef0123456789abcdef';
    const PLATFORM_SERIAL = 'PUB_KEY_ID_0000000000000001';
    const PAY_NO = 'PAY202609130001';

    private function key(string $name): string
    {
        return __DIR__ . '/fixtures/payment/' . $name . '.pem';
    }

    private function payRequest(string $scene, array $extra = []): PaymentRequest
    {
        return PaymentRequest::fromArray([
            'pay_no' => self::PAY_NO,
            'subject' => '旗舰版续费 1 个月',
            'amount' => '3000',
            'scene' => $scene,
            'expire_at' => time() + 7200,
            'notify_url' => 'https://pay.example.com/notify',
            'return_url' => 'https://pay.example.com/done',
            'extra' => $extra,
        ]);
    }

    private function alipay(): AlipayChannel
    {
        return new AlipayChannel([
            'app_id' => self::APP_ID,
            'private_key' => $this->key('merchant_private'),
            'alipay_public_key' => $this->key('platform_public'),
        ]);
    }

    private function wechat(): WechatPayChannel
    {
        return new WechatPayChannel([
            'appid' => self::WX_APPID,
            'mchid' => self::MCHID,
            'serial_no' => 'MERCHANT_SERIAL_0001',
            'private_key' => $this->key('merchant_private'),
            'api_v3_key' => self::API_V3_KEY,
            'platform_public_key' => $this->key('platform_public'),
            'platform_serial' => self::PLATFORM_SERIAL,
        ]);
    }

    /**
     * 支付宝应答：节点原文由「支付平台」私钥签名
     */
    private function alipayResponse(string $method, array $node, bool $signed = true): HttpResponse
    {
        $nodeJson = (string)json_encode($node, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sign = $signed ? ',"sign":"' . Rsa::sign($nodeJson, $this->key('platform_private')) . '"' : '';
        $raw = '{"' . str_replace('.', '_', $method) . '_response":' . $nodeJson . $sign . '}';
        return new HttpResponse(200, $raw, []);
    }

    /**
     * 支付宝回调参数：签名先算、覆盖后算，用于构造篡改场景时另行修改
     */
    private function alipayNotify(array $override): array
    {
        $params = array_merge([
            'app_id' => self::APP_ID,
            'out_trade_no' => self::PAY_NO,
            'trade_no' => '2026091322001400000001',
            'total_amount' => '3000.00',
            'trade_status' => 'TRADE_SUCCESS',
            'gmt_payment' => '2026-09-13 10:00:00',
            'notify_id' => 'notify0001',
        ], $override);
        $params['sign'] = Rsa::sign(AlipayChannel::signContent($params, ['sign', 'sign_type']), $this->key('platform_private'));
        $params['sign_type'] = 'RSA2';
        return $params;
    }

    private function wechatHeaders(string $body, int $timestamp, string $serial = self::PLATFORM_SERIAL): array
    {
        $nonce = 'nonce' . $timestamp;
        return [
            'Wechatpay-Timestamp' => (string)$timestamp,
            'Wechatpay-Nonce' => $nonce,
            'Wechatpay-Serial' => $serial,
            'Wechatpay-Signature' => Rsa::sign("{$timestamp}\n{$nonce}\n{$body}\n", $this->key('platform_private')),
        ];
    }

    private function wechatResponse(int $status, string $body): HttpResponse
    {
        return new HttpResponse($status, $body, $this->wechatHeaders($body, time()));
    }

    /**
     * 微信回调正文：交易资源用 APIv3 密钥 AES-256-GCM 加密
     */
    private function wechatNotifyBody(array $trade): string
    {
        $plain = (string)json_encode(array_merge([
            'mchid' => self::MCHID,
            'appid' => self::WX_APPID,
            'out_trade_no' => self::PAY_NO,
            'transaction_id' => '4200000000202609130001',
            'trade_state' => 'SUCCESS',
            'success_time' => '2026-09-13T10:00:00+08:00',
            'amount' => ['total' => 300000, 'currency' => 'CNY'],
        ], $trade));
        $nonce = 'abcdefghijkl';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::API_V3_KEY, OPENSSL_RAW_DATA, $nonce, $tag, 'transaction');
        return (string)json_encode([
            'id' => 'EV-0001',
            'event_type' => 'TRANSACTION.SUCCESS',
            'resource' => [
                'algorithm' => 'AEAD_AES_256_GCM',
                'ciphertext' => base64_encode($cipher . $tag),
                'nonce' => $nonce,
                'associated_data' => 'transaction',
            ],
        ]);
    }

    /**
     * 支付宝后台复制出来的公私钥是去掉头尾的一行 base64，要能直接用
     */
    public function testRsaAcceptsBareBase64Keys()
    {
        $strip = function (string $file) {
            return (string)preg_replace('/-----[^-]+-----|\s+/', '', (string)file_get_contents($file));
        };
        $signature = Rsa::sign('hello', $strip($this->key('merchant_private')));
        $this->assertTrue(Rsa::verify('hello', $signature, $strip($this->key('merchant_public'))));
        $this->assertFalse(Rsa::verify('hello!', $signature, $this->key('merchant_public')));
        $this->assertFalse(Rsa::verify('hello', '', $this->key('merchant_public')));
    }

    /**
     * 不带时区的渠道时间一律按北京时间解析，不能随进程默认时区漂移 8 小时
     */
    public function testChinaTimeIgnoresProcessTimezone()
    {
        $this->assertSame(gmmktime(2, 0, 0, 9, 13, 2026), ChinaTime::parse('2026-09-13 10:00:00'));
        $this->assertSame(gmmktime(2, 0, 0, 9, 13, 2026), ChinaTime::parse('2026-09-13T10:00:00+08:00'));
        $this->assertSame('2026-09-13T10:00:00+08:00', ChinaTime::format(gmmktime(2, 0, 0, 9, 13, 2026), DATE_RFC3339));
    }

    /**
     * 空串会被解析成当前时间，悄悄把缺失的支付时间补成现在
     */
    public function testChinaTimeRejectsEmpty()
    {
        $this->expectException(PaymentException::class);
        ChinaTime::parse('');
    }

    public function testAlipayQrcodeSendsSignedPrecreate()
    {
        $node = ['code' => '10000', 'msg' => 'Success', 'out_trade_no' => self::PAY_NO, 'qr_code' => 'https://qr.alipay.com/bax01'];
        $transport = new FakePaymentTransport([$this->alipayResponse('alipay.trade.precreate', $node)]);
        $result = $this->alipay()->withTransport($transport)->create($this->payRequest(PaymentScene::QRCODE));

        $this->assertSame(PaymentResult::TYPE_QRCODE, $result->type());
        $this->assertSame('https://qr.alipay.com/bax01', $result->content());
        $form = $transport->requests[0]['options']['form_params'];
        $this->assertSame('alipay.trade.precreate', $form['method']);
        $this->assertSame('3000.00', json_decode($form['biz_content'], true)['total_amount']);
        //请求签名必须能被商户公钥验过，否则支付宝会拒单
        $this->assertTrue(Rsa::verify(AlipayChannel::signContent($form, ['sign']), $form['sign'], $this->key('merchant_public')));
    }

    /**
     * 应答被篡改（比如把二维码换成别人的收款码）必须被验签拦住
     */
    public function testAlipayRejectsTamperedResponse()
    {
        $node = ['code' => '10000', 'msg' => 'Success', 'qr_code' => 'https://qr.alipay.com/bax01'];
        $raw = str_replace('bax01', 'evil1', $this->alipayResponse('alipay.trade.precreate', $node)->body());
        $channel = $this->alipay()->withTransport(new FakePaymentTransport([new HttpResponse(200, $raw, [])]));
        $this->expectException(PaymentException::class);
        $channel->create($this->payRequest(PaymentScene::QRCODE));
    }

    /**
     * 成功应答不带签名，只可能是被中间人剥掉了
     */
    public function testAlipayRejectsUnsignedSuccess()
    {
        $node = ['code' => '10000', 'msg' => 'Success', 'qr_code' => 'https://qr.alipay.com/bax01'];
        $channel = $this->alipay()->withTransport(new FakePaymentTransport([$this->alipayResponse('alipay.trade.precreate', $node, false)]));
        $this->expectException(PaymentException::class);
        $channel->create($this->payRequest(PaymentScene::QRCODE));
    }

    /**
     * 网页支付由付款人浏览器直接去支付宝，服务端不该发请求
     */
    public function testAlipayPcRedirectsWithoutServerCall()
    {
        $transport = new FakePaymentTransport([]);
        $result = $this->alipay()->withTransport($transport)->create($this->payRequest(PaymentScene::PC));

        $this->assertSame(PaymentResult::TYPE_REDIRECT, $result->type());
        parse_str((string)parse_url($result->content(), PHP_URL_QUERY), $query);
        $this->assertSame('alipay.trade.page.pay', $query['method']);
        $this->assertSame('FAST_INSTANT_TRADE_PAY', json_decode($query['biz_content'], true)['product_code']);
        $this->assertSame([], $transport->requests);
    }

    public function testAlipayNotifyConvertsToNotice()
    {
        $notice = $this->alipay()->parseNotify(new NotifyPayload($this->alipayNotify([]), '', []));
        $this->assertSame(self::PAY_NO, $notice->payNo());
        $this->assertSame('3000.00', $notice->amount());
        $this->assertSame(gmmktime(2, 0, 0, 9, 13, 2026), $notice->paidAt());
    }

    /**
     * 签完名再改金额，验签必须失败
     */
    public function testAlipayNotifyRejectsTamperedAmount()
    {
        $params = $this->alipayNotify([]);
        $params['total_amount'] = '0.01';
        $this->expectException(PaymentException::class);
        $this->alipay()->parseNotify(new NotifyPayload($params, '', []));
    }

    /**
     * 别的应用收到的真实回调被转发过来，验签能过，但不能给本平台开通
     */
    public function testAlipayNotifyRejectsForeignApp()
    {
        $this->expectException(PaymentException::class);
        $this->alipay()->parseNotify(new NotifyPayload($this->alipayNotify(['app_id' => '2021999999999999']), '', []));
    }

    /**
     * 可信但不是支付成功的事件：返回 null，应答成功但不履约
     */
    public function testAlipayNotifyIgnoresUnpaidStatus()
    {
        $this->assertNull($this->alipay()->parseNotify(new NotifyPayload($this->alipayNotify(['trade_status' => 'TRADE_CLOSED']), '', [])));
    }

    public function testAlipayQueryTreatsMissingTradeAsUnpaid()
    {
        $node = ['code' => '40004', 'msg' => 'Business Failed', 'sub_code' => 'ACQ.TRADE_NOT_EXIST', 'sub_msg' => '交易不存在'];
        $channel = $this->alipay()->withTransport(new FakePaymentTransport([$this->alipayResponse('alipay.trade.query', $node, false)]));
        $this->assertNull($channel->query(self::PAY_NO));
    }

    public function testWechatNativeSendsSignedOrder()
    {
        $transport = new FakePaymentTransport([$this->wechatResponse(200, '{"code_url":"weixin://wxpay/bizpayurl?pr=abc"}')]);
        $result = $this->wechat()->withTransport($transport)->create($this->payRequest(PaymentScene::QRCODE));

        $this->assertSame('weixin://wxpay/bizpayurl?pr=abc', $result->content());
        $request = $transport->requests[0];
        $this->assertSame('https://api.mch.weixin.qq.com/v3/pay/transactions/native', $request['url']);
        $body = json_decode($request['options']['body'], true);
        $this->assertSame(300000, $body['amount']['total']);
        $this->assertStringEndsWith('+08:00', $body['time_expire']);
        //Authorization 里的签名必须能被商户公钥验过，否则微信会拒单
        preg_match('/nonce_str="([^"]+)",signature="([^"]+)",timestamp="([^"]+)"/', $request['options']['headers']['Authorization'], $m);
        $message = "POST\n/v3/pay/transactions/native\n{$m[3]}\n{$m[1]}\n{$request['options']['body']}\n";
        $this->assertTrue(Rsa::verify($message, $m[2], $this->key('merchant_public')));
    }

    /**
     * H5 下单缺付款人 IP 会被微信风控拒掉，下单前就要拦住
     */
    public function testWechatH5RequiresClientIp()
    {
        $this->expectException(PaymentException::class);
        $this->wechat()->withTransport(new FakePaymentTransport([]))->create($this->payRequest(PaymentScene::H5));
    }

    public function testWechatNotifyDecryptsAndConverts()
    {
        $body = $this->wechatNotifyBody([]);
        $notice = $this->wechat()->parseNotify(new NotifyPayload([], $body, $this->wechatHeaders($body, time())));
        $this->assertSame('3000.00', $notice->amount());
        $this->assertSame('4200000000202609130001', $notice->tradeNo());
        $this->assertSame(gmmktime(2, 0, 0, 9, 13, 2026), $notice->paidAt());
    }

    /**
     * 截获的旧回调重放：签名是真的，但时间戳过期了
     */
    public function testWechatNotifyRejectsReplay()
    {
        $body = $this->wechatNotifyBody([]);
        $this->expectException(PaymentException::class);
        $this->wechat()->parseNotify(new NotifyPayload([], $body, $this->wechatHeaders($body, time() - 600)));
    }

    /**
     * 平台换钥后序列号变了，要报出来让运维更新配置
     */
    public function testWechatNotifyRejectsUnknownSerial()
    {
        $body = $this->wechatNotifyBody([]);
        $this->expectException(PaymentException::class);
        $this->wechat()->parseNotify(new NotifyPayload([], $body, $this->wechatHeaders($body, time(), 'PUB_KEY_ID_OTHER')));
    }

    public function testWechatNotifyRejectsTamperedBody()
    {
        $headers = $this->wechatHeaders($this->wechatNotifyBody([]), time());
        $this->expectException(PaymentException::class);
        $this->wechat()->parseNotify(new NotifyPayload([], $this->wechatNotifyBody(['amount' => ['total' => 1]]), $headers));
    }

    /**
     * 其他商户号的交易被转发过来，解密与验签都能过，但不能给本平台开通
     */
    public function testWechatNotifyRejectsForeignMerchant()
    {
        $body = $this->wechatNotifyBody(['mchid' => '1999999999']);
        $this->expectException(PaymentException::class);
        $this->wechat()->parseNotify(new NotifyPayload([], $body, $this->wechatHeaders($body, time())));
    }

    public function testWechatNotifyIgnoresUnpaidState()
    {
        $body = $this->wechatNotifyBody(['trade_state' => 'CLOSED']);
        $this->assertNull($this->wechat()->parseNotify(new NotifyPayload([], $body, $this->wechatHeaders($body, time()))));
    }

    public function testWechatQueryTreatsNotFoundAsUnpaid()
    {
        $response = new HttpResponse(404, '{"code":"ORDER_NOT_EXIST","message":"订单不存在"}', []);
        $this->assertNull($this->wechat()->withTransport(new FakePaymentTransport([$response]))->query(self::PAY_NO));
    }

    /**
     * 微信退款必须带原单总额，否则接口直接拒绝
     */
    public function testWechatRefundCarriesTotal()
    {
        $transport = new FakePaymentTransport([$this->wechatResponse(200, '{"refund_id":"50300000000001"}')]);
        $refund = RefundRequest::fromArray([
            'pay_no' => self::PAY_NO, 'refund_no' => 'RF202609130001', 'amount' => '1000', 'total_amount' => '3000', 'reason' => '重复支付',
        ]);
        $this->assertSame('50300000000001', $this->wechat()->withTransport($transport)->refund($refund));
        $body = json_decode($transport->requests[0]['options']['body'], true);
        $this->assertSame(['refund' => 100000, 'total' => 300000, 'currency' => 'CNY'], $body['amount']);
    }

    /**
     * 退得比收的多是资金事故
     */
    public function testRefundCannotExceedTotal()
    {
        $this->expectException(PaymentException::class);
        RefundRequest::fromArray(['pay_no' => self::PAY_NO, 'refund_no' => 'RF202609130001', 'amount' => '3000.01', 'total_amount' => '3000']);
    }

    /**
     * 备注单号是人工对账唯一的抓手
     */
    public function testManualGivesInstructionWithPayNo()
    {
        $channel = new ManualChannel(['account' => '户名：示例科技有限公司', 'qrcodes' => '/uploads/pay/a.png, /uploads/pay/b.png']);
        $result = $channel->create($this->payRequest(PaymentScene::MANUAL));

        $this->assertSame(PaymentResult::TYPE_MANUAL, $result->type());
        $this->assertStringContainsString(self::PAY_NO, $result->content());
        $this->assertStringContainsString('3000.00', $result->content());
        $this->assertSame(['/uploads/pay/a.png', '/uploads/pay/b.png'], $result->data()['qrcodes']);
    }

    public function testManualUnavailableWithoutReceivingInfo()
    {
        $channel = new ManualChannel(['account' => '']);
        $this->assertFalse($channel->available());
        $this->expectException(PaymentException::class);
        $channel->create($this->payRequest(PaymentScene::MANUAL));
    }

    /**
     * 没有渠道会给人工收款发回调，收到就是伪造的
     */
    public function testManualRejectsNotify()
    {
        $this->expectException(PaymentException::class);
        (new ManualChannel(['account' => 'x']))->parseNotify(new NotifyPayload(['out_trade_no' => self::PAY_NO], '', []));
    }

    /**
     * 退款能力按接口区分：人工收款没有原路退回
     */
    public function testOnlyOnlineChannelsAreRefundable()
    {
        $this->assertInstanceOf(RefundableChannelInterface::class, $this->alipay());
        $this->assertInstanceOf(RefundableChannelInterface::class, $this->wechat());
        $this->assertNotInstanceOf(RefundableChannelInterface::class, new ManualChannel(['account' => 'x']));
    }

    /**
     * 收银台只列出凭据配齐的渠道
     */
    public function testCashierListsOnlyConfiguredChannels()
    {
        $configs = [
            AlipayChannel::class => ['app_id' => self::APP_ID, 'private_key' => 'k', 'alipay_public_key' => 'k'],
            WechatPayChannel::class => ['appid' => self::WX_APPID],
            ManualChannel::class => ['account' => '户名：示例科技有限公司'],
        ];
        $manager = new PaymentManager([
            'channels' => ['alipay' => AlipayChannel::class, 'wechat' => WechatPayChannel::class, 'manual' => ManualChannel::class],
        ], function (string $class) use ($configs) {
            return new $class($configs[$class]);
        });
        $this->assertSame(['alipay'], array_keys($manager->channelsFor(PaymentScene::QRCODE)));
        $this->assertSame(['manual'], array_keys($manager->channelsFor(PaymentScene::MANUAL)));
    }
}

/**
 * 测试替身：按顺序返回预设应答，并记下每次请求
 */
class FakePaymentTransport implements HttpTransportInterface
{
    /**
     * @var array
     */
    public $requests = [];

    /**
     * @var HttpResponse[]
     */
    private $responses;

    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function request(string $method, string $url, array $options): HttpResponse
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];
        $next = array_shift($this->responses);
        if (!$next instanceof HttpResponse) {
            throw new \RuntimeException('没有预设的应答：' . $method . ' ' . $url);
        }
        return $next;
    }
}
