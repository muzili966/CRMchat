<?php

namespace tests\unit;

use app\services\chat\ChatFileGcServices;
use app\services\TenantPlanServices;
use PHPUnit\Framework\TestCase;

/**
 * 降级宽限期测试
 *
 * 保留期清理是不可逆的删除，判定写错的代价是客户数据没了。
 * 这里把「谁受保护、保护到哪天」的边界全部钉死。
 */
class DowngradeGraceTest extends TestCase
{
    /**
     * 宽限期的秒数
     * @return int
     */
    protected function graceSeconds(): int
    {
        return ChatFileGcServices::DOWNGRADE_GRACE_DAYS * 86400;
    }

    /**
     * 从未付费的租户不受保护，否则免费试用的数据永远删不掉
     */
    public function testNeverPaidIsNotProtected()
    {
        $this->assertFalse(ChatFileGcServices::isInGrace(0, time()));
        //异常的负值同样不该被当成「付过费」
        $this->assertFalse(ChatFileGcServices::isInGrace(-1, time()));
    }

    /**
     * 付费到期当天仍在保护期内
     */
    public function testJustExpiredIsStillProtected()
    {
        $now = time();
        $this->assertTrue(ChatFileGcServices::isInGrace($now - 60, $now));
    }

    /**
     * 尚未到期的付费租户当然受保护
     */
    public function testActiveSubscriptionIsProtected()
    {
        $now = time();
        $this->assertTrue(ChatFileGcServices::isInGrace($now + 86400 * 30, $now));
    }

    /**
     * 宽限期最后一刻仍受保护，越过即不再保护
     */
    public function testGraceBoundaryIsExclusiveAtEnd()
    {
        $now = time();
        $paidUntil = $now - $this->graceSeconds();
        //到期时间 + 宽限期 == 现在，此刻边界已过
        $this->assertFalse(ChatFileGcServices::isInGrace($paidUntil, $now));
        //差一秒则仍在期内
        $this->assertTrue(ChatFileGcServices::isInGrace($paidUntil + 1, $now));
    }

    /**
     * 宽限期要留足续费与导出的时间，别被误调成几天
     */
    public function testGraceLengthIsSane()
    {
        $this->assertGreaterThanOrEqual(30, ChatFileGcServices::DOWNGRADE_GRACE_DAYS);
    }

    /**
     * 概览要告诉租户数据保留到哪天，否则只能等数据没了才知道
     */
    public function testRetentionExposesGraceDeadline()
    {
        $paidUntil = time() - 86400;
        $r = TenantPlanServices::buildRetention(
            ['last_paid_expire_at' => $paidUntil],
            ['record_keep_days' => 7]
        );
        $this->assertTrue($r['in_grace']);
        $this->assertFalse($r['unlimited']);
        $this->assertSame($paidUntil + $this->graceSeconds(), $r['grace_until']);
        $this->assertSame(date('Y-m-d', $paidUntil + $this->graceSeconds()), $r['_grace_until']);
    }

    /**
     * 不在宽限期时不能给出截止日，否则前端会显示一个早已过去的日期
     */
    public function testRetentionHidesDeadlineOutsideGrace()
    {
        $r = TenantPlanServices::buildRetention(
            ['last_paid_expire_at' => time() - $this->graceSeconds() - 86400],
            ['record_keep_days' => 7]
        );
        $this->assertFalse($r['in_grace']);
        $this->assertSame(0, $r['grace_until']);
        $this->assertSame('', $r['_grace_until']);
        $this->assertSame(7, $r['keep_days']);
    }

    /**
     * 保留天数为0是「不限」，不能被读成「一天都不留」
     */
    public function testZeroKeepDaysMeansUnlimited()
    {
        $r = TenantPlanServices::buildRetention([], ['record_keep_days' => 0]);
        $this->assertTrue($r['unlimited']);
        $this->assertFalse($r['in_grace']);
    }

    /**
     * 缺字段不能致命：套餐或租户数据不全时按最保守的「不限」处理
     */
    public function testMissingFieldsFallBackToUnlimited()
    {
        $r = TenantPlanServices::buildRetention([], []);
        $this->assertSame(0, $r['keep_days']);
        $this->assertTrue($r['unlimited']);
    }
}
