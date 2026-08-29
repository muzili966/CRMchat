<?php

namespace tests\unit;

use app\models\Application;
use app\models\Tenant;
use crmeb\services\tenant\TenantContext;
use crmeb\services\tenant\TenantScope;
use PHPUnit\Framework\TestCase;

/**
 * 租户隔离作用域判定测试
 *
 * 边界：受约束模型/豁免模型/逃逸态；insertAll 批量填充只补空缺。
 */
class TenantScopeTest extends TestCase
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

    public function testScopedModelApplies()
    {
        $this->assertTrue(TenantScope::applies(new Application()));
    }

    public function testExemptModelDoesNotApply()
    {
        $this->assertFalse(TenantScope::applies(new Tenant()));
    }

    public function testBypassDisablesScope()
    {
        TenantContext::withoutTenant(function () {
            $this->assertFalse(TenantScope::applies(new Application()));
        });
    }

    public function testFillRowsOnlyFillsMissingTenantId()
    {
        TenantContext::set(self::TENANT_A);
        $rows = TenantScope::fillRows(new Application(), [
            ['name' => 'app1'],
            ['name' => 'app2', 'tenant_id' => self::TENANT_B],
        ]);
        $this->assertSame(self::TENANT_A, $rows[0]['tenant_id']);
        $this->assertSame(self::TENANT_B, $rows[1]['tenant_id']);
    }

    public function testFillRowsSkippedForExemptModel()
    {
        TenantContext::set(self::TENANT_A);
        $rows = TenantScope::fillRows(new Tenant(), [['name' => 'tenant1']]);
        $this->assertArrayNotHasKey('tenant_id', $rows[0]);
    }
}
