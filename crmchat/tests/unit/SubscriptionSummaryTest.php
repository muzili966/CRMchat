<?php

namespace tests\unit;

use app\services\TenantPlanServices;
use PHPUnit\Framework\TestCase;

/**
 * 租户订阅概览组装测试
 *
 * 边界：永久租户、已到期、未订购套餐。
 */
class SubscriptionSummaryTest extends TestCase
{
    public function testPermanentTenant()
    {
        $summary = TenantPlanServices::buildSubscriptionSummary(
            ['id' => 4, 'name' => '云图信息', 'status' => 1, 'expire_time' => 0],
            ['name' => '免费版'],
            1,
            2
        );
        $this->assertSame('永久', $summary['tenant']['_expire_time']);
        $this->assertFalse($summary['tenant']['is_expired']);
        $this->assertSame(1, $summary['usage']['app_count']);
        $this->assertSame(2, $summary['usage']['seat_count']);
    }

    public function testExpiredTenant()
    {
        $summary = TenantPlanServices::buildSubscriptionSummary(
            ['id' => 5, 'name' => '极光传媒', 'status' => 1, 'expire_time' => time() - 86400],
            ['name' => '旗舰版'],
            0,
            0
        );
        $this->assertTrue($summary['tenant']['is_expired']);
    }

    public function testFutureExpireNotExpired()
    {
        $summary = TenantPlanServices::buildSubscriptionSummary(
            ['id' => 2, 'name' => '星辰科技', 'status' => 1, 'expire_time' => time() + 86400],
            ['name' => '标准版'],
            0,
            0
        );
        $this->assertFalse($summary['tenant']['is_expired']);
    }

    public function testNoPlanBecomesNull()
    {
        $summary = TenantPlanServices::buildSubscriptionSummary(
            ['id' => 9, 'name' => '新租户', 'status' => 1, 'expire_time' => 0],
            [],
            0,
            0
        );
        $this->assertNull($summary['plan']);
    }
}
