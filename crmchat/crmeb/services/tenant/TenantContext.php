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

namespace crmeb\services\tenant;


use crmeb\exceptions\TenantContextException;

/**
 * 租户上下文
 *
 * Swoole 常驻 + 协程环境下基于 Coroutine::getContext() 存储，随协程销毁自动回收，
 * 请求间不会串味，因此无需借助 think-swoole 的 resetters；
 * 非协程环境（CLI 安装、单元测试）回退到进程内静态存储。
 *
 * Class TenantContext
 * @package crmeb\services\tenant
 */
class TenantContext
{
    /**
     * 平台（无租户）上下文标识
     */
    const PLATFORM_TENANT = 0;

    /**
     * 协程上下文中的租户ID键名
     */
    const CONTEXT_TENANT_KEY = '__tenant_id';

    /**
     * 协程上下文中的逃逸标记键名
     */
    const CONTEXT_BYPASS_KEY = '__tenant_bypass';

    /**
     * 非协程环境的回退存储
     * @var int|null
     */
    protected static $fallbackTenantId = null;

    /**
     * 非协程环境的逃逸标记
     * @var bool
     */
    protected static $fallbackBypass = false;

    /**
     * 设置当前租户上下文
     * @param int|null $tenantId null 表示清除上下文
     * @return void
     */
    public static function set(?int $tenantId)
    {
        $context = self::context();
        if ($context !== null) {
            $context[self::CONTEXT_TENANT_KEY] = $tenantId;
            return;
        }
        self::$fallbackTenantId = $tenantId;
    }

    /**
     * 获取当前租户ID，未设置返回null
     * @return int|null
     */
    public static function get()
    {
        $context = self::context();
        if ($context !== null) {
            return isset($context[self::CONTEXT_TENANT_KEY]) ? $context[self::CONTEXT_TENANT_KEY] : null;
        }
        return self::$fallbackTenantId;
    }

    /**
     * 当前租户ID归一化访问器：未初始化视为平台(0)
     * 适用于key构建、目录前缀等非数据隔离场景；数据访问请用must()
     * @return int
     */
    public static function id(): int
    {
        return (int)(self::get() ?: self::PLATFORM_TENANT);
    }

    /**
     * 包装闭包使其携带当前租户上下文执行，
     * 用于Timer/协程等脱离当前协程上下文的异步回调边界
     * @param \Closure $fn
     * @return \Closure
     */
    public static function wrap(\Closure $fn): \Closure
    {
        $tenantId = self::id();
        return function (...$args) use ($tenantId, $fn) {
            return self::runAs($tenantId, function () use ($fn, $args) {
                return $fn(...$args);
            });
        };
    }

    /**
     * 获取当前租户ID，未初始化直接抛出异常——默认拒绝，绝不静默放行
     * @return int
     */
    public static function must(): int
    {
        $tenantId = self::get();
        if (is_null($tenantId)) {
            throw new TenantContextException('租户上下文未初始化，禁止访问租户数据');
        }
        return $tenantId;
    }

    /**
     * 是否处于逃逸状态（平台级操作显式绕过租户隔离）
     * @return bool
     */
    public static function isBypass(): bool
    {
        $context = self::context();
        if ($context !== null) {
            return !empty($context[self::CONTEXT_BYPASS_KEY]);
        }
        return self::$fallbackBypass;
    }

    /**
     * 在无租户隔离的逃逸状态下执行平台级操作（登录寻址、安装、全局清理）
     * @param \Closure $fn
     * @return mixed
     */
    public static function withoutTenant(\Closure $fn)
    {
        $previous = self::isBypass();
        self::setBypass(true);
        try {
            return $fn();
        } finally {
            self::setBypass($previous);
        }
    }

    /**
     * 以指定租户身份执行操作（平台超管跨租户视角）
     * @param int $tenantId
     * @param \Closure $fn
     * @return mixed
     */
    public static function runAs(int $tenantId, \Closure $fn)
    {
        $previous = self::get();
        self::set($tenantId);
        try {
            return $fn();
        } finally {
            self::set($previous);
        }
    }

    /**
     * 清除上下文（仅用于测试或请求收尾）
     * @return void
     */
    public static function clear()
    {
        $context = self::context();
        if ($context !== null) {
            unset($context[self::CONTEXT_TENANT_KEY], $context[self::CONTEXT_BYPASS_KEY]);
        }
        self::$fallbackTenantId = null;
        self::$fallbackBypass = false;
    }

    /**
     * 设置逃逸标记
     * @param bool $bypass
     * @return void
     */
    protected static function setBypass(bool $bypass)
    {
        $context = self::context();
        if ($context !== null) {
            $context[self::CONTEXT_BYPASS_KEY] = $bypass;
            return;
        }
        self::$fallbackBypass = $bypass;
    }

    /**
     * 当前协程上下文，非协程环境返回null
     * @return \ArrayObject|null
     */
    protected static function context()
    {
        if (class_exists(\Swoole\Coroutine::class) && \Swoole\Coroutine::getCid() > 0) {
            return \Swoole\Coroutine::getContext();
        }
        return null;
    }
}
