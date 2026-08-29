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

namespace app\http\middleware\kefu;


use app\Request;
use app\services\kefu\LoginServices;
use app\services\TenantServices;
use crmeb\interfaces\MiddlewareInterface;
use crmeb\services\tenant\TenantContext;
use think\facade\Config;

/**
 * Class KefuAuthTokenMiddleware
 * @package app\kefu\middleware
 */
class KefuAuthTokenMiddleware implements MiddlewareInterface
{

    /**
     * @param Request $request
     * @param \Closure $next
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function handle(Request $request, \Closure $next)
    {
        $authInfo = null;
        $token = trim(ltrim($request->header(Config::get('cookie.token_name', 'Authori-zation')), 'Bearer'));
        /** @var LoginServices $services */
        $services = app()->make(LoginServices::class);
        //token寻址发生在租户上下文建立之前，需逃逸执行
        $kefuInfo = TenantContext::withoutTenant(function () use ($services, $token) {
            return $services->parseToken($token);
        });

        /** @var TenantServices $tenantServices */
        $tenantServices = app()->make(TenantServices::class);
        TenantContext::set($tenantServices->tenantIdByAppid((string)$kefuInfo['appid']));

        Request::macro('kefuId', function () use (&$kefuInfo) {
            return (int)$kefuInfo['id'];
        });

        Request::macro('kefuInfo', function () use (&$kefuInfo) {
            return $kefuInfo;
        });

        return $next($request);
    }
}
