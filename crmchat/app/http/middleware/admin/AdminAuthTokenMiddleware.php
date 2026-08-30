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
        //parseToken内部完成token寻址逃逸、租户可用性校验与上下文建立
        $adminInfo = $service->parseToken($token);

        //平台侧管理员可携带query参数tenant_id切换租户视角；仅读query，避免与POST数据字段冲突。
        //切换视角是独立权限点：level=0免校验，普通运营人员需角色授予对应权限行
        if (SystemAdmin::isPlatformAdmin($adminInfo)) {
            $viewTenantId = (int)$request->get('tenant_id', 0);
            if ($viewTenantId > 0) {
                if (!empty($adminInfo['level'])) {
                    /** @var \app\services\system\admin\SystemRoleServices $roleServices */
                    $roleServices = app()->make(\app\services\system\admin\SystemRoleServices::class);
                    if (!$roleServices->hasApiAuth($adminInfo['roles'] ?? [], SystemAdmin::VIEW_SWITCH_AUTH)) {
                        throw new \crmeb\exceptions\AuthException(\crmeb\utils\ApiErrorCode::ERR_AUTH);
                    }
                }
                /** @var TenantServices $tenantServices */
                $tenantServices = app()->make(TenantServices::class);
                $tenantServices->mustExists($viewTenantId);
                TenantContext::set($viewTenantId);
            }
        }

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
}
