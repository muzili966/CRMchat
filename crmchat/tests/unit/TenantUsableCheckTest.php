<?php

namespace tests\unit;

use app\dao\TenantDao;
use app\models\Tenant;
use app\services\TenantServices;
use crmeb\exceptions\AdminException;
use PHPUnit\Framework\TestCase;

/**
 * 租户可用性闸门测试
 *
 * checkUsable 是三端登录/接入的统一闸门，
 * 边界：平台上下文放行、租户不存在、已删除、已禁用、已到期、永久有效。
 */
class TenantUsableCheckTest extends TestCase
{
    /**
     * @param mixed $tenantRow
     * @return TenantServices
     */
    protected function makeServices($tenantRow): TenantServices
    {
        $dao = $this->createMock(TenantDao::class);
        $dao->method('get')->willReturn($tenantRow);
        return new TenantServices($dao);
    }

    /**
     * @param int $status
     * @param int $isDelete
     * @param int $expireTime
     * @return object
     */
    protected function makeTenantRow(int $status, int $isDelete = 0, int $expireTime = 0)
    {
        $row = new \stdClass();
        $row->status = $status;
        $row->is_delete = $isDelete;
        $row->expire_time = $expireTime;
        return $row;
    }

    public function testPlatformContextPasses()
    {
        $services = $this->makeServices(null);
        $services->checkUsable(Tenant::PLATFORM_TENANT_ID);
        $this->assertTrue(true);
    }

    public function testMissingTenantRejected()
    {
        $this->expectException(AdminException::class);
        $this->makeServices(null)->checkUsable(Tenant::DEFAULT_TENANT_ID);
    }

    public function testDeletedTenantRejected()
    {
        $this->expectException(AdminException::class);
        $this->makeServices($this->makeTenantRow(Tenant::STATUS_NORMAL, 1))->checkUsable(Tenant::DEFAULT_TENANT_ID);
    }

    public function testDisabledTenantRejected()
    {
        $this->expectException(AdminException::class);
        $this->makeServices($this->makeTenantRow(Tenant::STATUS_DISABLE))->checkUsable(Tenant::DEFAULT_TENANT_ID);
    }

    public function testExpiredTenantRejected()
    {
        $this->expectException(AdminException::class);
        $this->expectExceptionMessage('租户已到期，请联系平台管理员续期');
        $this->makeServices($this->makeTenantRow(Tenant::STATUS_NORMAL, 0, time() - 1))->checkUsable(Tenant::DEFAULT_TENANT_ID);
    }

    public function testPermanentTenantPasses()
    {
        $this->makeServices($this->makeTenantRow(Tenant::STATUS_NORMAL))->checkUsable(Tenant::DEFAULT_TENANT_ID);
        $this->assertTrue(true);
    }

    public function testUnexpiredTenantPasses()
    {
        $this->makeServices($this->makeTenantRow(Tenant::STATUS_NORMAL, 0, time() + 3600))->checkUsable(Tenant::DEFAULT_TENANT_ID);
        $this->assertTrue(true);
    }
}
