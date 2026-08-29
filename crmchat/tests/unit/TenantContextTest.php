<?php

namespace tests\unit;

use crmeb\exceptions\TenantContextException;
use crmeb\services\tenant\TenantContext;
use PHPUnit\Framework\TestCase;

/**
 * 租户上下文测试
 *
 * 边界：未初始化must抛异常、withoutTenant/runAs嵌套后正确还原。
 */
class TenantContextTest extends TestCase
{
    const TENANT_A = 1;
    const TENANT_B = 2;

    protected function setUp(): void
    {
        TenantContext::clear();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
    }

    public function testUninitializedMustThrows()
    {
        $this->expectException(TenantContextException::class);
        TenantContext::must();
    }

    public function testSetAndGet()
    {
        TenantContext::set(self::TENANT_A);
        $this->assertSame(self::TENANT_A, TenantContext::get());
        $this->assertSame(self::TENANT_A, TenantContext::must());
    }

    public function testWithoutTenantRestoresPreviousState()
    {
        TenantContext::set(self::TENANT_A);
        $this->assertFalse(TenantContext::isBypass());
        $result = TenantContext::withoutTenant(function () {
            $this->assertTrue(TenantContext::isBypass());
            return 'done';
        });
        $this->assertSame('done', $result);
        $this->assertFalse(TenantContext::isBypass());
        $this->assertSame(self::TENANT_A, TenantContext::get());
    }

    public function testWithoutTenantRestoresOnException()
    {
        try {
            TenantContext::withoutTenant(function () {
                throw new \RuntimeException('业务异常');
            });
            $this->fail('异常应向上抛出');
        } catch (\RuntimeException $e) {
            $this->assertFalse(TenantContext::isBypass());
        }
    }

    public function testNestedWithoutTenantKeepsBypassUntilOuterExit()
    {
        TenantContext::withoutTenant(function () {
            TenantContext::withoutTenant(function () {
                $this->assertTrue(TenantContext::isBypass());
            });
            $this->assertTrue(TenantContext::isBypass(), '内层退出后外层逃逸态应保留');
        });
        $this->assertFalse(TenantContext::isBypass());
    }

    public function testRunAsSwitchesAndRestoresTenant()
    {
        TenantContext::set(self::TENANT_A);
        TenantContext::runAs(self::TENANT_B, function () {
            $this->assertSame(self::TENANT_B, TenantContext::must());
        });
        $this->assertSame(self::TENANT_A, TenantContext::get());
    }

    public function testRunAsFromUninitializedRestoresNull()
    {
        TenantContext::runAs(self::TENANT_B, function () {
            $this->assertSame(self::TENANT_B, TenantContext::must());
        });
        $this->assertNull(TenantContext::get());
    }
}
