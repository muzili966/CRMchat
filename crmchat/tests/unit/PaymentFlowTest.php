<?php

namespace tests\unit;

use app\services\payment\delivery\ChatCardDelivery;
use app\services\payment\PaymentServices;
use app\services\payment\PaymentSettleServices;
use app\services\payment\TenantPlanPayable;
use app\services\platform\PlatformSupportServices;
use crmeb\services\payment\CashierLink;
use crmeb\services\payment\dto\PaymentNotice;
use crmeb\services\payment\dto\PaymentOrder;
use crmeb\services\payment\PaymentException;
use crmeb\services\payment\PaymentScene;
use crmeb\services\payment\PaymentSettlement;
use crmeb\services\payment\PaymentStatus;
use PHPUnit\Framework\TestCase;

/**
 * 支付流程里的纯逻辑：收银台链接签名、到账决策、场景挑选、续费报价校验、访客反推租户
 */
class PaymentFlowTest extends TestCase
{
    const KEY = 'unit-test-app-key';

    const PAY_NO = 'PY2026091312000012345678';

    const EXPIRE = 1789000000;

    public function testCashierLinkRoundTrip()
    {
        $sign = CashierLink::sign(self::PAY_NO, self::EXPIRE, self::KEY);
        $this->assertTrue(CashierLink::verify(self::PAY_NO, self::EXPIRE, ['signature' => $sign, 'key' => self::KEY]));
        //签名要能直接放进 URL
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $sign);
    }

    /**
     * 改单号窥探别人的账单、延长过期时间、换密钥伪造，都必须验不过
     */
    public function testCashierLinkRejectsTampering()
    {
        $sign = CashierLink::sign(self::PAY_NO, self::EXPIRE, self::KEY);
        $this->assertFalse(CashierLink::verify('PY2026091312000012345679', self::EXPIRE, ['signature' => $sign, 'key' => self::KEY]));
        $this->assertFalse(CashierLink::verify(self::PAY_NO, self::EXPIRE + 1, ['signature' => $sign, 'key' => self::KEY]));
        $this->assertFalse(CashierLink::verify(self::PAY_NO, self::EXPIRE, ['signature' => $sign, 'key' => 'other']));
        $this->assertFalse(CashierLink::verify(self::PAY_NO, self::EXPIRE, ['signature' => '', 'key' => self::KEY]));
    }

    /**
     * 没配密钥时宁可不签发，也不能用空密钥签出人人可算的签名
     */
    public function testCashierLinkRequiresKey()
    {
        $this->expectException(PaymentException::class);
        CashierLink::sign(self::PAY_NO, self::EXPIRE, '');
    }

    public function testCashierLinkUrl()
    {
        $url = CashierLink::url('https://kf.example.com/', self::PAY_NO, 'abc');
        $this->assertSame('https://kf.example.com/pay/cashier?no=' . self::PAY_NO . '&sign=abc', $url);
        //s 是 ThinkPHP 的 pathinfo 变量，链接里出现它会让收银台接口被路由到前端页面
        $this->assertStringNotContainsString('&s=', $url);
    }

    /**
     * 二维码要被手机扫开，没有对外地址就不能生成相对链接糊弄过去
     */
    public function testCashierLinkUrlRequiresOrigin()
    {
        $this->expectException(PaymentException::class);
        CashierLink::url('', self::PAY_NO, 'abc');
    }

    public function testSettlementPendingFulfills()
    {
        $this->assertSame(PaymentSettlement::FULFILL, PaymentSettlement::decide(PaymentStatus::PENDING, true)['action']);
    }

    public function testSettlementSameTradeIsDuplicate()
    {
        $this->assertSame(PaymentSettlement::DUPLICATE, PaymentSettlement::decide(PaymentStatus::PAID, true, true)['action']);
        //同一笔的重复通知哪怕金额字段异常，也不该产生新动作
        $this->assertSame(PaymentSettlement::DUPLICATE, PaymentSettlement::decide(PaymentStatus::PAID, false, true)['action']);
    }

    /**
     * 同一张单被两个渠道各付一次：第二笔要标异常退款，不能当重复通知吞掉
     */
    public function testSettlementSecondTradeIsAbnormal()
    {
        $this->assertSame(PaymentSettlement::ABNORMAL, PaymentSettlement::decide(PaymentStatus::PAID, true, false)['action']);
    }

    public function testSettlementAbnormalCases()
    {
        $this->assertSame(PaymentSettlement::ABNORMAL, PaymentSettlement::decide(PaymentStatus::PENDING, false)['action']);
        $closed = PaymentSettlement::decide(PaymentStatus::CLOSED, true);
        $this->assertSame(PaymentSettlement::ABNORMAL, $closed['action']);
        $this->assertStringContainsString('已关闭', $closed['reason']);
        $this->assertStringContainsString('已退款', PaymentSettlement::decide(PaymentStatus::REFUNDED, true)['reason']);
    }

    public function testSameTrade()
    {
        $notice = $this->notice('T1');
        $this->assertTrue(PaymentSettleServices::sameTrade(['trade_no' => '', 'channel' => 'alipay'], 'wechat', $notice));
        $this->assertTrue(PaymentSettleServices::sameTrade(['trade_no' => 'T1', 'channel' => 'alipay'], 'alipay', $notice));
        $this->assertFalse(PaymentSettleServices::sameTrade(['trade_no' => 'T0', 'channel' => 'alipay'], 'alipay', $notice));
        //交易号撞了但渠道不同，也不是同一笔
        $this->assertFalse(PaymentSettleServices::sameTrade(['trade_no' => 'T1', 'channel' => 'wechat'], 'alipay', $notice));
    }

    public function testScenePreference()
    {
        $all = [PaymentScene::QRCODE, PaymentScene::PC, PaymentScene::H5];
        $this->assertSame(PaymentScene::H5, PaymentScene::preferred($all, true));
        $this->assertSame(PaymentScene::QRCODE, PaymentScene::preferred($all, false));
        $this->assertSame(PaymentScene::PC, PaymentScene::preferred([PaymentScene::PC, PaymentScene::H5], false));
        //手机上没有 H5 时退回扫码
        $this->assertSame(PaymentScene::QRCODE, PaymentScene::preferred([PaymentScene::QRCODE], true));
        $this->assertSame(PaymentScene::MANUAL, PaymentScene::preferred([PaymentScene::MANUAL], true));
        $this->assertSame('', PaymentScene::preferred([PaymentScene::PC], true));
    }

    public function testQuoteAcceptsOnSalePaidPlan()
    {
        TenantPlanPayable::checkQuote($this->plan(), TenantPlanPayable::MONTHS_MIN);
        TenantPlanPayable::checkQuote($this->plan(), TenantPlanPayable::MONTHS_MAX);
        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider invalidQuotes
     */
    public function testQuoteRejects(?array $plan, int $months, string $message)
    {
        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage($message);
        TenantPlanPayable::checkQuote($plan, $months);
    }

    public function invalidQuotes(): array
    {
        return [
            '零个月' => [$this->plan(), 0, '订购月数'],
            '超过上限' => [$this->plan(), TenantPlanPayable::MONTHS_MAX + 1, '订购月数'],
            '套餐不存在' => [null, 1, '套餐不存在'],
            '已删除' => [$this->plan(['is_delete' => 1]), 1, '套餐不存在'],
            '已停售' => [$this->plan(['status' => 0]), 1, '已停售'],
            '免费套餐' => [$this->plan(['price' => '0.00']), 1, '免费套餐'],
        ];
    }

    public function testTenantIdOfVisitorUid()
    {
        $base = PlatformSupportServices::UID_BASE;
        $this->assertSame(7, PlatformSupportServices::tenantIdOf($base + 7));
        //平台自营租户自己不会是付款方
        $this->assertSame(0, PlatformSupportServices::tenantIdOf($base + 1));
        $this->assertSame(0, PlatformSupportServices::tenantIdOf(12345));
        $this->assertSame(0, PlatformSupportServices::tenantIdOf($base * 2 + 7));
    }

    public function testChatCardPayload()
    {
        $order = PaymentOrder::fromArray([
            'pay_no' => self::PAY_NO,
            'tenant_id' => 7,
            'biz_type' => TenantPlanPayable::BIZ_TYPE,
            'subject' => '「标准版」套餐 3个月',
            'amount' => '4500.00',
            'expire_at' => self::EXPIRE,
        ]);
        $card = json_decode(base64_decode(ChatCardDelivery::encode($order, 'https://kf.example.com/pay/cashier?no=1&s=2')), true);
        $this->assertSame(self::PAY_NO, $card['pay_no']);
        $this->assertSame('「标准版」套餐 3个月', $card['subject']);
        $this->assertSame('4500.00', $card['amount']);
        $this->assertSame(self::EXPIRE, $card['expire_at']);
        $this->assertSame('https://kf.example.com/pay/cashier?no=1&s=2', $card['url']);
    }

    /**
     * 单号同时是渠道侧商户订单号，必须满足渠道的格式要求
     */
    public function testPayNoFormat()
    {
        $payNo = PaymentServices::buildPayNo(self::EXPIRE);
        $this->assertMatchesRegularExpression('/^PY\d{22}$/', $payNo);
        $this->assertNotSame($payNo, PaymentServices::buildPayNo(self::EXPIRE));
    }

    /**
     * @param string $tradeNo
     * @return PaymentNotice
     */
    private function notice(string $tradeNo): PaymentNotice
    {
        return PaymentNotice::fromArray(['pay_no' => self::PAY_NO, 'trade_no' => $tradeNo, 'amount' => '1500.00', 'paid_at' => self::EXPIRE]);
    }

    /**
     * @param array $override
     * @return array
     */
    private function plan(array $override = []): array
    {
        return $override + ['id' => 3, 'name' => '标准版', 'price' => '1500.00', 'status' => 1, 'is_delete' => 0];
    }
}
