<?php

namespace tests\unit;

use app\dao\TenantDao;
use app\models\system\admin\SystemAdmin;
use app\services\TenantServices;
use crmeb\exceptions\AdminException;
use crmeb\services\tenant\TenantContext;
use PHPUnit\Framework\TestCase;

/**
 * 租户CRUD与平台管理员判定测试
 *
 * 边界：租户名必填/查重、编辑不存在的租户、admin_type缺失时按最小权限处理、
 * TenantContext::id与wrap的上下文携带。
 */
class TenantCrudTest extends TestCase
{
    const TENANT_ID = 6;

    protected function setUp(): void
    {
        TenantContext::clear();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
    }

    /**
     * @param array $methodReturns 方法名 => 返回值
     * @return TenantServices
     */
    protected function makeServices(array $methodReturns): TenantServices
    {
        $dao = $this->createMock(TenantDao::class);
        foreach ($methodReturns as $method => $return) {
            $dao->method($method)->willReturn($return);
        }
        return new TenantServices($dao);
    }

    public function testCreateRequiresName()
    {
        $this->expectException(AdminException::class);
        $this->expectExceptionMessage('请填写租户名称');
        $this->makeServices([])->create(['name' => '']);
    }

    public function testCreateRejectsDuplicateName()
    {
        $this->expectException(AdminException::class);
        $this->expectExceptionMessage('租户名称已存在');
        $this->makeServices(['getCount' => 1])->create(['name' => '重复租户']);
    }

    public function testEditMissingTenantRejected()
    {
        $this->expectException(AdminException::class);
        $this->expectExceptionMessage('租户不存在');
        $this->makeServices(['get' => null])->edit(self::TENANT_ID, ['name' => '新名称']);
    }

    public function testSetStatusMissingTenantRejected()
    {
        $this->expectException(AdminException::class);
        $this->makeServices(['get' => null])->setStatus(self::TENANT_ID, 0);
    }

    public function testIsPlatformAdmin()
    {
        $this->assertTrue(SystemAdmin::isPlatformAdmin(['admin_type' => SystemAdmin::TYPE_PLATFORM]));
        $this->assertFalse(SystemAdmin::isPlatformAdmin(['admin_type' => SystemAdmin::TYPE_TENANT]));
        $this->assertFalse(SystemAdmin::isPlatformAdmin([]), 'admin_type缺失必须按租户管理员处理（最小权限）');
    }

    public function testContextIdDefaultsToPlatform()
    {
        $this->assertSame(TenantContext::PLATFORM_TENANT, TenantContext::id());
        TenantContext::set(self::TENANT_ID);
        $this->assertSame(self::TENANT_ID, TenantContext::id());
    }

    public function testWrapCarriesTenantAcrossContextLoss()
    {
        TenantContext::set(self::TENANT_ID);
        $wrapped = TenantContext::wrap(function () {
            return TenantContext::id();
        });
        //模拟Timer回调运行在无上下文的新协程
        TenantContext::clear();
        $this->assertSame(self::TENANT_ID, $wrapped());
        $this->assertSame(TenantContext::PLATFORM_TENANT, TenantContext::id(), 'wrap执行完毕后应还原环境上下文');
    }
}
