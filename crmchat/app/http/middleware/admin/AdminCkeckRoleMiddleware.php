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
use app\services\system\admin\SystemRoleServices;
use crmeb\exceptions\AuthException;
use crmeb\interfaces\MiddlewareInterface;
use crmeb\utils\ApiErrorCode;

/**
 * 权限规则验证
 * Class AdminCkeckRoleMiddleware
 * @package app\http\middleware
 */
class AdminCkeckRoleMiddleware implements MiddlewareInterface
{

    public function handle(Request $request, \Closure $next)
    {
        if (!$request->adminId() || !$request->adminInfo())
            throw new AuthException(ApiErrorCode::ERR_ADMINID_VOID);

        $adminType = $request->adminInfo()['admin_type'] ?? SystemAdmin::TYPE_TENANT;
        if ($adminType != SystemAdmin::TYPE_PLATFORM) {
            /** @var SystemRoleServices $systemRoleService */
            $systemRoleService = app()->make(SystemRoleServices::class);
            $systemRoleService->verifiAuth($request);
        }

        return $next($request);
    }
}
