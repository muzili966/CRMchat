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

namespace app\services\system\admin;


use app\dao\system\admin\AdminAuthDao;
use app\models\system\admin\SystemAdmin;
use app\services\TenantServices;
use crmeb\basic\BaseServices;
use app\services\other\CacheServices;
use crmeb\exceptions\AuthException;
use crmeb\services\CacheService;
use crmeb\services\tenant\TenantContext;
use crmeb\services\tenant\TenantScope;
use crmeb\utils\ApiErrorCode;
use crmeb\utils\JwtAuth;
use Firebase\JWT\ExpiredException;

/**
 * admin授权service
 * Class AdminAuthServices
 * @package app\services\system\admin
 */
class AdminAuthServices extends BaseServices
{
    /**
     * 构造方法
     * AdminAuthServices constructor.
     * @param AdminAuthDao $dao
     */
    public function __construct(AdminAuthDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取Admin授权信息
     * @param string $token
     * @return array
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function parseToken(string $token): array
    {

        if (!$token || $token === 'undefined') {
            throw new AuthException(ApiErrorCode::ERR_LOGIN);
        }
        /** @var JwtAuth $jwtAuth */
        $jwtAuth = app()->make(JwtAuth::class);
        //设置解析token
        [$id, $type, $tokenTenantId] = $jwtAuth->parseToken($token);

        //验证token
        try {
            $jwtAuth->verifyToken();

        } catch (ExpiredException $e) {
            throw new AuthException(ApiErrorCode::ERR_LOGIN_INVALID);
        } catch (\Throwable $e) {
            $this->authFailAfter($id, $type);
            throw new AuthException(ApiErrorCode::ERR_LOGIN_INVALID);
        }

        //获取管理员信息（token寻址先于租户上下文建立，逃逸执行）
        $adminInfo = TenantContext::withoutTenant(function () use ($id) {
            return $this->dao->get($id);
        });
        if (!$adminInfo || !$adminInfo->id) {
            $this->authFailAfter($id, $type);
            throw new AuthException(ApiErrorCode::ERR_LOGIN_STATUS);
        }

        //token租户声明与账号当前归属二次比对，防止跨租户复用（旧token无声明时兼容放行）
        if (TenantScope::tokenTenantMismatch($tokenTenantId, (int)($adminInfo->tenant_id ?? 0))) {
            throw new AuthException(ApiErrorCode::ERR_LOGIN_STATUS);
        }

        //解析即建立租户上下文；租户管理员每请求校验租户可用性，禁用/到期即时生效
        $tenantId = (int)($adminInfo->tenant_id ?? 0);
        if (!SystemAdmin::isPlatformAdmin($adminInfo)) {
            /** @var TenantServices $tenantServices */
            $tenantServices = app()->make(TenantServices::class);
            $tenantServices->checkUsable($tenantId);
        }
        TenantContext::set($tenantId);

        $adminInfo->type = $type;
        return $adminInfo->hidden(['pwd', 'is_del', 'status'])->toArray();
    }

    /**
     * token验证失败后事件
     */
    protected function authFailAfter($id, $type)
    {
        try {
            $postData = request()->post();
            $rule = trim(strtolower(request()->rule()->getRule()));
            $method = trim(strtolower(request()->method()));
            //添加商品退出后事件
            if ($rule === 'product/product/<id>' && $method === 'post') {
                $this->saveProduct($id, $postData);
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * 保存提交数据
     * @param $adminId
     * @param $postData
     */
    protected function saveProduct($adminId, $postData)
    {
        /** @var CacheServices $cacheService */
        $cacheService = app()->make(CacheServices::class);
        $cacheService->setDbCache($adminId . '_product_data', $postData, 68400);
    }
}
