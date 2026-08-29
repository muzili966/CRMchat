<?php

namespace tests\unit;

use app\dao\system\config\SystemConfigDao;
use app\services\system\config\SystemConfigServices;
use crmeb\services\FormBuilder;
use crmeb\services\SystemConfigService;
use crmeb\services\tenant\TenantContext;
use crmeb\services\tenant\TenantScope;
use PHPUnit\Framework\TestCase;

/**
 * 配置两层读取与租户上传目录测试
 *
 * 边界：白名单内租户覆盖优先、覆盖缺失回落平台默认、白名单外始终读平台层；
 * 上传目录仅在租户上下文中加前缀。
 */
class TenantConfigLayerTest extends TestCase
{
    const TENANT_ID = 5;
    const OVERRIDABLE_KEY = 'site_name';
    const PLATFORM_ONLY_KEY = 'upload_type';

    protected function setUp(): void
    {
        TenantContext::clear();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
    }

    /**
     * @param array $tenantValueMap 租户覆盖层数据
     * @param mixed $platformValue 平台默认层数据（json编码前的原始值）
     * @return SystemConfigServices
     */
    protected function makeServices(array $tenantValueMap, $platformValue): SystemConfigServices
    {
        $dao = $this->createMock(SystemConfigDao::class);
        $dao->method('getTenantValueMap')->willReturn($tenantValueMap);
        $dao->method('getConfigValue')->willReturn(json_encode($platformValue));
        $builder = $this->createMock(FormBuilder::class);
        return new SystemConfigServices($dao, $builder);
    }

    public function testOverridableKeyPrefersTenantValue()
    {
        $services = $this->makeServices([self::OVERRIDABLE_KEY => json_encode('租户站点')], '平台站点');
        $this->assertSame('租户站点', $services->getTenantConfigValue(self::OVERRIDABLE_KEY, self::TENANT_ID));
    }

    public function testMissingTenantValueFallsBackToPlatform()
    {
        $services = $this->makeServices([], '平台站点');
        $this->assertSame('平台站点', $services->getTenantConfigValue(self::OVERRIDABLE_KEY, self::TENANT_ID));
    }

    public function testNonOverridableKeyAlwaysReadsPlatform()
    {
        $services = $this->makeServices([self::PLATFORM_ONLY_KEY => json_encode(2)], 1);
        $this->assertSame(1, $services->getTenantConfigValue(self::PLATFORM_ONLY_KEY, self::TENANT_ID));
    }

    public function testPlatformContextReadsPlatformLayer()
    {
        $services = $this->makeServices([self::OVERRIDABLE_KEY => json_encode('租户站点')], '平台站点');
        $this->assertSame('平台站点', $services->getTenantConfigValue(self::OVERRIDABLE_KEY, 0));
    }

    public function testOverridableWhitelistContainsBrandKeysOnly()
    {
        $this->assertContains('site_name', SystemConfigService::TENANT_OVERRIDABLE);
        $this->assertNotContains('upload_type', SystemConfigService::TENANT_OVERRIDABLE, '基础设施配置禁止租户覆盖');
        $this->assertNotContains('accessKey', SystemConfigService::TENANT_OVERRIDABLE, '云存储密钥禁止租户覆盖');
    }

    public function testUploadDirPrefixedInTenantContext()
    {
        TenantContext::set(self::TENANT_ID);
        $this->assertSame('tenant/5/store/comment', TenantScope::uploadDir('store/comment'));
    }

    public function testUploadDirUnchangedInPlatformContext()
    {
        $this->assertSame('store/comment', TenantScope::uploadDir('store/comment'));
        TenantContext::set(0);
        $this->assertSame('store/comment', TenantScope::uploadDir('store/comment'));
    }
}
