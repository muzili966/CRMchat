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


use app\Request;
use crmeb\basic\BaseServices;
use app\dao\system\admin\SystemRoleDao;
use app\services\system\SystemMenusServices;
use crmeb\exceptions\AuthException;
use crmeb\utils\ApiErrorCode;
use crmeb\services\CacheService;


/**
 * Class SystemRoleServices
 * @package app\services\system\admin
 * @method update($id, array $data, ?string $key = null) 修改数据
 * @method save(array $data) 保存数据
 * @method get(int $id, ?array $field = []) 获取数据
 * @method delete(int $id, ?string $key = null) 删除数据
 */
class SystemRoleServices extends BaseServices
{

    /**
     * 当前管理员权限缓存前缀
     */
    const ADMIN_RULES_LEVEL = 'Admin_rules_level_';

    /**
     * SystemRoleServices constructor.
     * @param SystemRoleDao $dao
     */
    public function __construct(SystemRoleDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取权限
     * @return mixed
     */
    public function getRoleArray(array $where = [], string $field = '', string $key = '')
    {
        return $this->dao->getRoule($where, $field, $key);
    }

    /**
     * 获取表单所需的权限名称列表
     * @param int $level
     * @return array
     */
    public function getRoleFormSelect(int $level)
    {
        $list = $this->getRoleArray(['level' => $level, 'status' => 1]);
        $options = [];
        foreach ($list as $id => $roleName) {
            $options[] = ['label' => $roleName, 'value' => $id];
        }
        return $options;
    }

    /**
     * 身份管理列表
     * @param array $where
     * @return array
     */
    public function getRoleList(array $where)
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getRouleList($where, $page, $limit);
        $count = $this->dao->count($where);
        /** @var SystemMenusServices $service */
        $service = app()->make(SystemMenusServices::class);
        foreach ($list as &$item) {
            $item['rules'] = implode(',', array_merge($service->column(['id' => $item['rules']], 'menu_name', 'id')));
        }
        return compact('count', 'list');
    }

    /**
     * 后台验证权限
     * @param Request $request
     */
    public function verifiAuth(Request $request)
    {
        $auth = $this->getRolesByAuth($request->adminInfo()['roles'], 2);
        $rule = str_replace('adminapi/', '', trim(strtolower($request->rule()->getRule())));
        $method = trim(strtolower($request->method()));
        //viewauth与menuslist同属"拿自己能看什么"，不能反过来被权限拦住
        if (in_array($rule, ['setting/admin/logout', 'menuslist', 'viewauth'])) {
            return true;
        }
        //验证访问接口是否存在
        if (!in_array($rule, array_map(function ($item) {
            return trim(strtolower(str_replace(' ', '', $item)));
        }, array_column($auth, 'api_url')))) {
            throw new AuthException(ApiErrorCode::ERR_RULE);
        }
        //验证访问接口是否有权限
        if (empty(array_filter($auth, function ($item) use ($rule, $method) {
            if (trim(strtolower($item['api_url'])) === $rule && $method === trim(strtolower($item['methods'])))
                return true;
        }))) {
            throw new AuthException(ApiErrorCode::ERR_AUTH);
        }
    }

    /**
     * 校验角色是否拥有指定接口权限。
     * 用于非当前路由的虚拟权限点（如平台运营人员切换租户视角），
     * 与verifiAuth共享同一份角色权限数据与缓存
     * @param array $roles
     * @param string $apiUrl
     * @param string $method
     * @return bool
     */
    public function hasApiAuth(array $roles, string $apiUrl, string $method = 'GET'): bool
    {
        return self::matchApiAuth($this->getRolesByAuth($roles, 2), $apiUrl, $method);
    }

    /**
     * 在权限清单中匹配接口（大小写与空白不敏感，与verifiAuth规则一致）
     * @param array $auth [['api_url'=>..,'methods'=>..]]
     * @param string $apiUrl
     * @param string $method
     * @return bool
     */
    public static function matchApiAuth(array $auth, string $apiUrl, string $method): bool
    {
        $apiUrl = trim(strtolower($apiUrl));
        $method = trim(strtolower($method));
        foreach ($auth as $item) {
            if (trim(strtolower($item['api_url'] ?? '')) === $apiUrl && trim(strtolower($item['methods'] ?? '')) === $method) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取指定权限
     * @param array $rules
     * @param int $type
     * @param string $cachePrefix
     * @return array|mixed
     * @throws \throwable
     */
    public function getRolesByAuth(array $rules, int $type = 1, string $cachePrefix = self::ADMIN_RULES_LEVEL)
    {
        if (empty($rules)) return [];
        $cacheName = md5($cachePrefix . '_' . $type . '_' . implode('_', $rules));
        return CacheService::remember($cacheName, function () use ($rules, $type) {
            /** @var SystemMenusServices $menusService */
            $menusService = app()->make(SystemMenusServices::class);
            return $menusService->getColumn([['id', 'IN', $this->getRoleIds($rules)], ['auth_type', '=', $type]], 'api_url,methods');
        });
    }

    /**
     * 获取权限id
     * @param array $rules
     * @return array
     */
    public function getRoleIds(array $rules)
    {
        $rules = $this->dao->getColumn([['id', 'IN', $rules], ['status', '=', '1']], 'rules', 'id');
        return array_unique(explode(',', implode(',', $rules)));
    }
}
