<?php

namespace tests\unit;

use app\models\TenantNotice;
use app\services\TenantNoticeServices;
use app\services\TenantPlanOrderServices;
use app\services\TenantPlanServices;
use PHPUnit\Framework\TestCase;

/**
 * 套餐计费核心逻辑测试
 *
 * 边界：配额0=不限、用量达上限即拒绝；免费套餐永久有效、续订顺延与换套餐重算；
 * 到期通知的临界天数与去重语义。
 */
class TenantPlanBillingTest extends TestCase
{
    const DAY = 86400;

    public function testUnlimitedQuotaNeverOver()
    {
        $this->assertFalse(TenantPlanServices::isOverLimit(999999, 0));
    }

    public function testQuotaBoundary()
    {
        $this->assertFalse(TenantPlanServices::isOverLimit(1, 2), '未达上限允许新增');
        $this->assertTrue(TenantPlanServices::isOverLimit(2, 2), '已达上限拒绝新增');
        $this->assertTrue(TenantPlanServices::isOverLimit(3, 2));
    }

    public function testFreePlanNeverExpires()
    {
        $now = time();
        $this->assertSame(0, TenantPlanOrderServices::computeExpireAfter(0, 12, '0.00', false, $now));
        $this->assertSame(0, TenantPlanOrderServices::computeExpireAfter($now + self::DAY, 3, '0.00', true, $now));
    }

    public function testRenewSamePlanExtendsFromCurrentExpire()
    {
        $now = strtotime('2026-08-29 10:00:00');
        $expire = strtotime('2026-09-10 10:00:00');
        $result = TenantPlanOrderServices::computeExpireAfter($expire, 1, '500.00', true, $now);
        $this->assertSame(strtotime('2026-10-10 10:00:00'), $result, '续订同套餐应在原到期时间上顺延');
    }

    public function testSwitchPlanStartsFromNow()
    {
        $now = strtotime('2026-08-29 10:00:00');
        $expire = strtotime('2026-09-10 10:00:00');
        $result = TenantPlanOrderServices::computeExpireAfter($expire, 1, '1000.00', false, $now);
        $this->assertSame(strtotime('2026-09-29 10:00:00'), $result, '换套餐应从当前时间起算');
    }

    public function testExpiredSamePlanRenewStartsFromNow()
    {
        $now = strtotime('2026-08-29 10:00:00');
        $expire = strtotime('2026-08-01 10:00:00');
        $result = TenantPlanOrderServices::computeExpireAfter($expire, 2, '500.00', true, $now);
        $this->assertSame(strtotime('2026-10-29 10:00:00', $now), $result, '已过期续订应从当前时间起算');
    }

    public function testPermanentTenantNoNotice()
    {
        $this->assertNull(TenantNoticeServices::buildExpireNotice(0, time()));
    }

    public function testFarFromExpiryNoNotice()
    {
        $now = time();
        $this->assertNull(TenantNoticeServices::buildExpireNotice($now + 8 * self::DAY + 60, $now));
    }

    public function testWithinWarnDaysNotified()
    {
        $now = time();
        $notice = TenantNoticeServices::buildExpireNotice($now + 3 * self::DAY, $now);
        $this->assertSame(TenantNotice::TYPE_EXPIRE_WARN, $notice['type']);
        $this->assertStringContainsString('3天后', $notice['content']);
    }

    public function testExpiredNotified()
    {
        $now = time();
        $notice = TenantNoticeServices::buildExpireNotice($now - self::DAY, $now);
        $this->assertSame(TenantNotice::TYPE_EXPIRED, $notice['type']);
        $this->assertStringContainsString('已于', $notice['content']);
    }
}
