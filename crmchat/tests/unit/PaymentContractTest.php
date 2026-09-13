<?php

namespace tests\unit;

use crmeb\services\payment\contract\DeliveryInterface;
use crmeb\services\payment\contract\PayableInterface;
use crmeb\services\payment\contract\PaymentChannelInterface;
use crmeb\services\payment\dto\NotifyPayload;
use crmeb\services\payment\dto\PaymentNotice;
use crmeb\services\payment\dto\PaymentOrder;
use crmeb\services\payment\dto\PaymentQuote;
use crmeb\services\payment\dto\PaymentRequest;
use crmeb\services\payment\dto\PaymentResult;
use crmeb\services\payment\Money;
use crmeb\services\payment\PaymentException;
use crmeb\services\payment\PaymentManager;
use crmeb\services\payment\PaymentScene;
use crmeb\services\payment\PaymentStatus;
use PHPUnit\Framework\TestCase;

/**
 * 支付抽象层测试
 *
 * 这一层不碰任何渠道，但钱怎么表示、状态怎么流转、组件怎么注册，
 * 是以后每接一个渠道都要依赖的约定，错一处就是全渠道一起错。
 */
class PaymentContractTest extends TestCase
{
    /**
     * 浮点不收：0.1+0.2 这种误差放在钱上不可接受
     */
    public function testMoneyRejectsFloat()
    {
        $this->expectException(PaymentException::class);
        Money::normalize(0.1);
    }

    /**
     * @return array
     */
    public function invalidAmountProvider(): array
    {
        return [
            '三位小数' => ['1.234'],
            '负数'     => ['-1'],
            '零'       => ['0'],
            '零点零'   => ['0.00'],
            '非数字'   => ['abc'],
            '空串'     => [''],
        ];
    }

    /**
     * @dataProvider invalidAmountProvider
     * @param string $amount
     */
    public function testMoneyRejectsInvalid(string $amount)
    {
        $this->expectException(PaymentException::class);
        Money::normalize($amount);
    }

    public function testMoneyNormalizesToTwoDecimals()
    {
        $this->assertSame('100.00', Money::normalize('100'));
        $this->assertSame('3000.50', Money::normalize('3000.5'));
        $this->assertSame('1500.00', Money::normalize(1500));
        $this->assertTrue(Money::equals('100', '100.00'));
        $this->assertSame(300000, Money::toCents('3000.00'));
    }

    /**
     * 状态只能往前走；已关闭不能被回调悄悄翻成已支付
     */
    public function testStatusOnlyMovesForward()
    {
        $this->assertTrue(PaymentStatus::canTransit(PaymentStatus::PENDING, PaymentStatus::PAID));
        $this->assertTrue(PaymentStatus::canTransit(PaymentStatus::PENDING, PaymentStatus::CLOSED));
        $this->assertTrue(PaymentStatus::canTransit(PaymentStatus::PAID, PaymentStatus::REFUNDED));
        $this->assertFalse(PaymentStatus::canTransit(PaymentStatus::PAID, PaymentStatus::PENDING));
        $this->assertFalse(PaymentStatus::canTransit(PaymentStatus::CLOSED, PaymentStatus::PAID));
        $this->assertFalse(PaymentStatus::canTransit(PaymentStatus::REFUNDED, PaymentStatus::PAID));
        $this->assertFalse(PaymentStatus::canTransit(99, PaymentStatus::PAID));
    }

    /**
     * @param array $override
     * @return array
     */
    private function requestData(array $override = []): array
    {
        return array_merge([
            'pay_no' => 'PAY202609130001',
            'subject' => '旗舰版续费 1 个月',
            'amount' => '3000',
            'scene' => PaymentScene::QRCODE,
            'expire_at' => time() + 7200,
            'notify_url' => 'https://example.com/notify',
        ], $override);
    }

    public function testRequestNormalizesAmount()
    {
        $req = PaymentRequest::fromArray($this->requestData());
        $this->assertSame('3000.00', $req->amount());
    }

    /**
     * 非人工场景没有回调地址就永远收不到到账通知，下单时就要拦住
     */
    public function testRequestRequiresNotifyUrlExceptManual()
    {
        $manual = PaymentRequest::fromArray($this->requestData(['scene' => PaymentScene::MANUAL, 'notify_url' => '']));
        $this->assertSame(PaymentScene::MANUAL, $manual->scene());

        $this->expectException(PaymentException::class);
        PaymentRequest::fromArray($this->requestData(['notify_url' => '']));
    }

    /**
     * 单号超过 32 位微信会拒单，下单前就要拦住
     */
    public function testRequestRejectsTooLongPayNo()
    {
        $this->expectException(PaymentException::class);
        PaymentRequest::fromArray($this->requestData(['pay_no' => str_repeat('A', 33)]));
    }

    public function testRequestRejectsUnknownScene()
    {
        $this->expectException(PaymentException::class);
        PaymentRequest::fromArray($this->requestData(['scene' => 'bitcoin']));
    }

    /**
     * 标题按字节截断，且截断后仍是合法 UTF-8，不能截出半个汉字
     */
    public function testRequestTruncatesSubjectSafely()
    {
        $req = PaymentRequest::fromArray($this->requestData(['subject' => str_repeat('旗舰版续费', 30)]));
        $this->assertLessThanOrEqual(PaymentRequest::SUBJECT_MAX_BYTES, strlen($req->subject()));
        $this->assertTrue(mb_check_encoding($req->subject(), 'UTF-8'));
    }

    /**
     * 验签只证明消息来自渠道，不证明钱付够了
     */
    public function testNoticeAmountMustMatch()
    {
        $notice = PaymentNotice::fromArray(['pay_no' => 'PAY1', 'trade_no' => 'T1', 'amount' => '0.01', 'paid_at' => time()]);
        $this->assertFalse($notice->matchesAmount('3000.00'));
        $this->assertTrue($notice->matchesAmount('0.01'));
    }

    public function testNoticeRequiresTradeNo()
    {
        $this->expectException(PaymentException::class);
        PaymentNotice::fromArray(['pay_no' => 'PAY1', 'trade_no' => '', 'amount' => '1', 'paid_at' => time()]);
    }

    public function testResultRejectsEmptyContent()
    {
        $this->assertSame(PaymentResult::TYPE_QRCODE, PaymentResult::qrcode('https://qr.alipay.com/x')->type());
        $this->expectException(PaymentException::class);
        PaymentResult::redirect('');
    }

    /**
     * 只有待支付且未过期的单子能付；过期的链接、二维码、卡片都得失效
     */
    public function testOrderPayableOnlyWhenPendingAndNotExpired()
    {
        $now = time();
        $row = ['pay_no' => 'PAY1', 'biz_type' => 'tenant_plan', 'amount' => '3000', 'payload' => '{"plan_id":4,"months":1}'];
        $pending = PaymentOrder::fromArray($row + ['status' => PaymentStatus::PENDING, 'expire_at' => $now + 60]);
        $expired = PaymentOrder::fromArray($row + ['status' => PaymentStatus::PENDING, 'expire_at' => $now - 1]);
        $paid = PaymentOrder::fromArray($row + ['status' => PaymentStatus::PAID, 'expire_at' => $now + 60]);

        $this->assertTrue($pending->isPayable($now));
        $this->assertFalse($expired->isPayable($now));
        $this->assertFalse($paid->isPayable($now));
        $this->assertSame(['plan_id' => 4, 'months' => 1], $pending->payload());
    }

    /**
     * 微信 v3 的签名在请求头里，头名大小写不能影响取值
     */
    public function testNotifyHeaderIsCaseInsensitive()
    {
        $payload = new NotifyPayload([], '{}', ['Wechatpay-Signature' => ['abc']]);
        $this->assertSame('abc', $payload->header('wechatpay-signature'));
        $this->assertSame('', $payload->header('missing'));
    }

    /**
     * @return PaymentManager
     */
    private function manager(): PaymentManager
    {
        return new PaymentManager([
            'channels' => ['fake_qr' => FakeQrChannel::class, 'fake_manual' => FakeManualChannel::class],
            'payables' => ['fake_plan' => FakePayable::class],
            'deliveries' => ['fake_link' => FakeDelivery::class],
        ], function (string $class) {
            return new $class();
        });
    }

    public function testManagerResolvesAndCaches()
    {
        $manager = $this->manager();
        $this->assertSame($manager->channel('fake_qr'), $manager->channel('fake_qr'));
        $this->assertSame('fake_plan', $manager->payable('fake_plan')->bizType());
        $this->assertSame('fake_link', $manager->delivery('fake_link')->code());
    }

    public function testManagerRejectsUnregistered()
    {
        $this->expectException(PaymentException::class);
        $this->manager()->channel('wechat');
    }

    /**
     * 注册成渠道的类必须真的实现了渠道接口，配错了在解析时就要报出来
     */
    public function testManagerRejectsWrongContract()
    {
        $manager = new PaymentManager(['channels' => ['fake_link' => FakeDelivery::class]], function (string $class) {
            return new $class();
        });
        $this->expectException(PaymentException::class);
        $manager->channel('fake_link');
    }

    /**
     * 注册键与自报编码不一致时，回调按编码分发会找错渠道
     */
    public function testManagerRejectsCodeMismatch()
    {
        $manager = new PaymentManager(['channels' => ['alipay' => FakeQrChannel::class]], function (string $class) {
            return new $class();
        });
        $this->expectException(PaymentException::class);
        $manager->channel('alipay');
    }

    public function testChannelsForFiltersByScene()
    {
        $manager = $this->manager();
        $this->assertSame(['fake_qr'], array_keys($manager->channelsFor(PaymentScene::QRCODE)));
        $this->assertSame(['fake_manual'], array_keys($manager->channelsFor(PaymentScene::MANUAL)));
        $this->assertSame([], array_keys($manager->channelsFor(PaymentScene::H5)));
    }
}

/**
 * 测试替身：扫码渠道
 */
class FakeQrChannel implements PaymentChannelInterface
{
    public function code(): string
    {
        return 'fake_qr';
    }

    public function name(): string
    {
        return '测试扫码';
    }

    public function scenes(): array
    {
        return [PaymentScene::QRCODE, PaymentScene::PC];
    }

    public function available(): bool
    {
        return true;
    }

    public function create(PaymentRequest $request): PaymentResult
    {
        return PaymentResult::qrcode('fake://' . $request->payNo());
    }

    public function query(string $payNo): ?PaymentNotice
    {
        return null;
    }

    public function parseNotify(NotifyPayload $payload): PaymentNotice
    {
        return PaymentNotice::fromArray($payload->query());
    }

    public function notifyAck(bool $success): string
    {
        return $success ? 'success' : 'fail';
    }

    public function close(string $payNo): void
    {
    }
}

/**
 * 测试替身：人工收款渠道
 */
class FakeManualChannel extends FakeQrChannel
{
    public function code(): string
    {
        return 'fake_manual';
    }

    public function scenes(): array
    {
        return [PaymentScene::MANUAL];
    }
}

/**
 * 测试替身：业务
 */
class FakePayable implements PayableInterface
{
    public function bizType(): string
    {
        return 'fake_plan';
    }

    public function quote(int $tenantId, array $params): PaymentQuote
    {
        return new PaymentQuote('测试', '1', $params);
    }

    public function fulfill(PaymentOrder $order, PaymentNotice $notice): void
    {
    }
}

/**
 * 测试替身：投递方式
 */
class FakeDelivery implements DeliveryInterface
{
    public function code(): string
    {
        return 'fake_link';
    }

    public function deliver(PaymentOrder $order, array $context): array
    {
        return ['url' => 'fake://' . $order->payNo()];
    }
}
