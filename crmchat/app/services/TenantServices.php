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

namespace app\services;


use app\dao\ApplicationDao;
use app\dao\TenantDao;
use app\models\system\admin\SystemAdmin;
use app\models\Tenant;
use crmeb\basic\BaseServices;
use crmeb\exceptions\AdminException;
use crmeb\services\tenant\TenantContext;

/**
 * 租户service
 * Class TenantServices
 * @package app\services
 */
class TenantServices extends BaseServices
{

    /**
     * TenantServices constructor.
     * @param TenantDao $dao
     */
    public function __construct(TenantDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 租户列表
     * @param array $where
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getTenantList(array $where)
    {
        $where['is_delete'] = 0;
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getTenantList($where, $page, $limit);
        $count = $this->dao->count($where);
        foreach ($list as &$item) {
            $item['_create_time'] = $item['create_time'] ? date('Y-m-d H:i:s', $item['create_time']) : '';
            $item['_expire_time'] = $item['expire_time'] ? date('Y-m-d H:i:s', $item['expire_time']) : '永久';
        }
        return compact('list', 'count');
    }

    /**
     * 创建租户
     * @param array $data
     * @return \crmeb\basic\BaseModel|\think\Model
     */
    public function create(array $data)
    {
        if (!$data['name']) {
            throw new AdminException('请填写租户名称');
        }
        if ($this->dao->getCount(['name' => $data['name'], 'is_delete' => 0])) {
            throw new AdminException('租户名称已存在');
        }
        $data['status'] = Tenant::STATUS_NORMAL;
        $data['create_time'] = time();
        $data['update_time'] = time();
        return $this->dao->save($data);
    }

    /**
     * 修改租户
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
        $tenantInfo = $this->dao->get($id);
        if (!$tenantInfo || $tenantInfo->is_delete) {
            throw new AdminException('租户不存在');
        }
        if ($data['name'] && $data['name'] != $tenantInfo->name && $this->dao->getCount(['name' => $data['name'], 'is_delete' => 0])) {
            throw new AdminException('租户名称已存在');
        }
        $data['update_time'] = time();
        return false !== $this->dao->update($id, $data);
    }

    /**
     * 修改租户状态
     * @param int $id
     * @param int $status
     * @return bool
     */
    public function setStatus(int $id, int $status)
    {
        $tenantInfo = $this->dao->get($id);
        if (!$tenantInfo || $tenantInfo->is_delete) {
            throw new AdminException('租户不存在');
        }
        return false !== $this->dao->update($id, ['status' => $status, 'update_time' => time()]);
    }

    /**
     * 租户下拉选项
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getTenantOptions()
    {
        return $this->dao->getDataList(['is_delete' => 0], ['name as label', 'id as value'], 'id');
    }

    /**
     * 校验租户是否可用，禁用或到期直接抛出异常
     * @param int $tenantId 0=平台上下文或未迁移数据，直接放行
     * @return void
     */
    public function checkUsable(int $tenantId)
    {
        if ($tenantId <= Tenant::PLATFORM_TENANT_ID) {
            return;
        }
        $tenantInfo = $this->dao->get($tenantId);
        if (!$tenantInfo || $tenantInfo->is_delete || $tenantInfo->status != Tenant::STATUS_NORMAL) {
            throw new AdminException('租户已被禁用，请联系平台管理员');
        }
        if ($tenantInfo->expire_time > 0 && $tenantInfo->expire_time < time()) {
            throw new AdminException('租户已到期，请联系平台管理员续期');
        }
    }

    /**
     * 按应用appid校验所属租户是否可用
     * @param string $appid
     * @return void
     */
    public function checkUsableByAppid(string $appid)
    {
        $this->checkUsable($this->tenantIdByAppid($appid));
    }

    /**
     * 由appid解析所属租户ID，应用不存在返回0
     * @param string $appid
     * @return int
     */
    public function tenantIdByAppid(string $appid): int
    {
        if (!$appid) {
            return Tenant::PLATFORM_TENANT_ID;
        }
        //appid寻址属于跨租户定位，需逃逸执行
        return (int)TenantContext::withoutTenant(function () use ($appid) {
            /** @var ApplicationDao $applicationDao */
            $applicationDao = app()->make(ApplicationDao::class);
            return $applicationDao->value(['appid' => $appid], 'tenant_id') ?: 0;
        });
    }

    /**
     * 校验租户存在性（不校验状态，供平台超管切换租户视角）
     * @param int $tenantId
     * @return void
     */
    public function mustExists(int $tenantId)
    {
        $tenantInfo = $this->dao->get($tenantId);
        if (!$tenantInfo || $tenantInfo->is_delete) {
            throw new AdminException('租户不存在');
        }
    }

    /**
     * 为租户创建管理员：在目标租户上下文中复用统一的管理员创建流程
     * @param array $data
     * @return bool
     */
    public function createTenantAdmin(array $data)
    {
        $tenantId = (int)$data['tenant_id'];
        $this->mustExists($tenantId);
        if (!$data['account'] || !$data['pwd']) {
            throw new AdminException('请填写管理员账号和密码');
        }
        $roles = array_map('intval', $data['roles'] ?? []);
        /** @var \app\services\system\admin\SystemAdminServices $adminServices */
        $adminServices = app()->make(\app\services\system\admin\SystemAdminServices::class);
        return TenantContext::runAs($tenantId, function () use ($adminServices, $data, $roles) {
            $this->checkTenantRoles($roles);
            return $adminServices->create([
                'account' => $data['account'],
                'pwd' => $data['pwd'],
                'conf_pwd' => $data['conf_pwd'],
                'real_name' => $data['real_name'] ?: $data['account'],
                'roles' => $roles,
                'level' => $roles ? SystemAdmin::TENANT_ADMIN_LEVEL : 0,
                'status' => SystemAdmin::STATUS_NORMAL,
            ]);
        });
    }

    /**
     * 校验角色归属当前租户，禁止引用其他租户的角色
     * @param array $roles
     * @return void
     */
    protected function checkTenantRoles(array $roles)
    {
        if (!$roles) {
            return;
        }
        /** @var \app\services\system\admin\SystemRoleServices $roleServices */
        $roleServices = app()->make(\app\services\system\admin\SystemRoleServices::class);
        $validCount = count($roleServices->getColumn([['id', 'IN', $roles]], 'id'));
        if ($validCount != count($roles)) {
            throw new AdminException('所选角色不属于该租户');
        }
    }
}
