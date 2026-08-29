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

namespace app\http\middleware\admin;


use app\models\system\admin\SystemAdmin;
use app\Request;
use app\services\system\admin\AdminAuthServices;
use app\services\TenantServices;
use crmeb\interfaces\MiddlewareInterface;
use crmeb\services\tenant\TenantContext;
use think\facade\Config;

/**
 * 后台登陆验证中间件
 * Class AdminAuthTokenMiddleware
 * @package app\http\middleware\admin
 */
class AdminAuthTokenMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, \Closure $next)
    {
        $authInfo = null;
        $token = trim(ltrim($request->header(Config::get('cookie.token_name', 'Authori-zation')), 'Bearer'));

        /** @var AdminAuthServices $service */
        $service = app()->make(AdminAuthServices::class);
        //token寻址发生在租户上下文建立之前，需逃逸执行
        $adminInfo = TenantContext::withoutTenant(function () use ($service, $token) {
            return $service->parseToken($token);
        });

        TenantContext::set($this->resolveTenantId($request, $adminInfo));

        Request::macro('isAdminLogin', function () use (&$adminInfo) {
            return !is_null($adminInfo);
        });
        Request::macro('adminId', function () use (&$adminInfo) {
            return $adminInfo['id'];
        });

        Request::macro('adminInfo', function () use (&$adminInfo) {
            return $adminInfo;
        });

        return $next($request);
    }

    /**
     * 解析当前请求的租户上下文：
     * 租户管理员固定为自己所属租户；平台超管默认平台视角(0)，
     * 携带 tenant_id 参数时切换为对应租户视角
     * @param Request $request
     * @param array $adminInfo
     * @return int
     */
    protected function resolveTenantId(Request $request, array $adminInfo): int
    {
        $adminType = $adminInfo['admin_type'] ?? SystemAdmin::TYPE_TENANT;
        if ($adminType != SystemAdmin::TYPE_PLATFORM) {
            return (int)($adminInfo['tenant_id'] ?? 0);
        }
        $tenantId = (int)$request->param('tenant_id', 0);
        if ($tenantId > 0) {
            /** @var TenantServices $tenantServices */
            $tenantServices = app()->make(TenantServices::class);
            $tenantServices->mustExists($tenantId);
        }
        return $tenantId;
    }
}
