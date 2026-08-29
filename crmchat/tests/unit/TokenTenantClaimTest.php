<?php

namespace tests\unit;

use crmeb\services\tenant\TenantScope;
use PHPUnit\Framework\TestCase;

/**
 * token租户声明二次比对测试
 *
 * 边界：旧token无声明兼容放行、声明一致放行、跨租户声明拒绝、平台0与租户互换拒绝。
 */
class TokenTenantClaimTest extends TestCase
{
    const TENANT_A = 1;
    const TENANT_B = 2;
    const PLATFORM = 0;

    public function testLegacyTokenWithoutClaimPasses()
    {
        $this->assertFalse(TenantScope::tokenTenantMismatch(null, self::TENANT_A));
        $this->assertFalse(TenantScope::tokenTenantMismatch(null, self::PLATFORM));
    }

    public function testMatchingClaimPasses()
    {
        $this->assertFalse(TenantScope::tokenTenantMismatch(self::TENANT_A, self::TENANT_A));
        $this->assertFalse(TenantScope::tokenTenantMismatch(self::PLATFORM, self::PLATFORM));
    }

    public function testCrossTenantClaimRejected()
    {
        $this->assertTrue(TenantScope::tokenTenantMismatch(self::TENANT_A, self::TENANT_B));
    }

    public function testPlatformAndTenantNotInterchangeable()
    {
        $this->assertTrue(TenantScope::tokenTenantMismatch(self::PLATFORM, self::TENANT_A), '平台token不可用于租户账号');
        $this->assertTrue(TenantScope::tokenTenantMismatch(self::TENANT_A, self::PLATFORM), '租户token不可用于平台账号');
    }

    public function testStringClaimComparedNumerically()
    {
        $this->assertFalse(TenantScope::tokenTenantMismatch('1', self::TENANT_A), 'jwt反序列化可能产生字符串数字');
        $this->assertTrue(TenantScope::tokenTenantMismatch('2', self::TENANT_A));
    }
}
