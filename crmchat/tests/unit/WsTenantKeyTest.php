<?php

namespace tests\unit;

use app\webscoket\Manager;
use crmeb\services\tenant\TenantContext;
use PHPUnit\Framework\TestCase;

/**
 * WebSocket 在线态key租户隔离测试
 *
 * 边界：显式传租户（task进程场景）、协程上下文回退、无上下文时落到平台0。
 */
class WsTenantKeyTest extends TestCase
{
    const TENANT_A = 3;
    const TENANT_B = 7;

    protected function setUp(): void
    {
        TenantContext::clear();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
    }

    public function testExplicitTenantIdWins()
    {
        TenantContext::set(self::TENANT_A);
        $this->assertSame('_ws_7_kefu', Manager::wsKey('kefu', '', self::TENANT_B));
    }

    public function testFallsBackToContext()
    {
        TenantContext::set(self::TENANT_A);
        $this->assertSame('_ws_3_kefu', Manager::wsKey('kefu'));
        $this->assertSame('_ws_3_user123', Manager::wsKey('user', 123));
    }

    public function testUninitializedContextUsesPlatformZero()
    {
        $this->assertSame('_ws_0_kefu', Manager::wsKey('kefu'));
    }

    public function testDifferentTenantsNeverShareKeys()
    {
        $keyA = Manager::wsKey('kefu', 9, self::TENANT_A);
        $keyB = Manager::wsKey('kefu', 9, self::TENANT_B);
        $this->assertNotSame($keyA, $keyB);
    }
}
