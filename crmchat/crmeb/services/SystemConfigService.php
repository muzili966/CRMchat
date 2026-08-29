<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2020 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace crmeb\services;

use app\services\system\config\SystemConfigServices;
use crmeb\services\tenant\TenantContext;
use crmeb\utils\Arr;
use think\facade\Config;

/** 获取系统配置服务类
 * Class SystemConfigService
 * @package service
 */
class SystemConfigService
{
    /**
     * 缓存前缀字符
     */
    const CACHE_SYSTEM = 'system_config';
    /**
     * 过期时间
     */
    const EXPIRE_TIME = 30 * 24 * 3600;

    /**
     * 允许租户覆盖的配置白名单（品牌类）；
     * 存储AK/SK、上传方式等基础设施配置为平台统一，不在此列
     */
    const TENANT_OVERRIDABLE = [
        'site_name',
        'seo_title',
        'site_logo',
        'site_logo_square',
        'login_logo',
        'tourist_avatar',
        'service_feedback',
    ];

    /**
     * 获取配置缓存前缀
     * @return string
     */
    public static function getTag()
    {
        return Config::get('cache.stores.redis.tag_prefix') . 'cahce_' . self::CACHE_SYSTEM;
    }

    /**
     * 获取单个配置效率更高
     * @param $key
     * @param string $default
     * @param bool $isCaChe 是否获取缓存配置
     * @return bool|mixed|string
     */
    public static function get(string $key, $default = '', bool $isCaChe = false)
    {
        $tenantId = TenantContext::id();
        $callable = function () use ($key, $tenantId) {
            /** @var SystemConfigServices $service */
            $service = app()->make(SystemConfigServices::class);
            return $service->getTenantConfigValue($key, $tenantId);
        };

        try {
            if ($isCaChe) {
                return $callable();
            }
            return CacheService::redisHandler(self::getTag())->remember(self::CACHE_SYSTEM . ':' . $tenantId . ':' . $key, $callable, self::EXPIRE_TIME);
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * 获取多个配置
     * @param array $keys 示例 [['appid','1'],'appkey']
     * @param bool $isCaChe 是否获取缓存配置
     * @return array
     */
    public static function more(array $keys, bool $isCaChe = false)
    {
        $tenantId = TenantContext::id();
        $callable = function () use ($keys, $tenantId) {
            /** @var SystemConfigServices $service */
            $service = app()->make(SystemConfigServices::class);
            return Arr::getDefaultValue($keys, $service->getTenantConfigAll($keys, $tenantId));
        };
        try {
            if ($isCaChe)
                return $callable();

            return CacheService::redisHandler(self::getTag())->remember(self::CACHE_SYSTEM . ':' . $tenantId . ':' . md5(implode(',', $keys)), $callable, self::EXPIRE_TIME);
        } catch (\Throwable $e) {
            return Arr::getDefaultValue($keys);
        }
    }

    /**
     * 清空配置缓存
     * @return bool|void
     */
    public static function clear()
    {
        try {
            return CacheService::redisHandler(self::getTag())->clear();
        } catch (\Throwable $e) {
            \think\facade\Log::error('清空配置缓存失败：原因：' . $e->getMessage());
            return false;
        }
    }

}
