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
use app\dao\system\admin\SystemAdminDao;
use app\dao\TenantDao;
use app\models\system\admin\SystemAdmin;
use app\models\Tenant;
use crmeb\basic\BaseServices;
use crmeb\exceptions\AdminException;

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
        if (!$appid) {
            return;
        }
        /** @var ApplicationDao $applicationDao */
        $applicationDao = app()->make(ApplicationDao::class);
        $tenantId = $applicationDao->value(['appid' => $appid], 'tenant_id');
        if (is_null($tenantId)) {
            return;
        }
        $this->checkUsable((int)$tenantId);
    }

    /**
     * 为租户创建管理员
     * @param array $data
     * @return bool
     */
    public function createTenantAdmin(array $data)
    {
        $tenantInfo = $this->dao->get((int)$data['tenant_id']);
        if (!$tenantInfo || $tenantInfo->is_delete) {
            throw new AdminException('租户不存在');
        }
        if (!$data['account'] || !$data['pwd']) {
            throw new AdminException('请填写管理员账号和密码');
        }
        if ($data['pwd'] != $data['conf_pwd']) {
            throw new AdminException('两次输入的密码不相同');
        }
        /** @var SystemAdminDao $adminDao */
        $adminDao = app()->make(SystemAdminDao::class);
        if ($adminDao->count(['account' => $data['account'], 'is_del' => 0])) {
            throw new AdminException('管理员账号已存在');
        }
        $adminData = [
            'account' => $data['account'],
            'pwd' => $this->passwordHash($data['pwd']),
            'real_name' => $data['real_name'] ?: $data['account'],
            'roles' => implode(',', $data['roles'] ?? []),
            'level' => SystemAdmin::TENANT_ADMIN_LEVEL,
            'status' => 1,
            'add_time' => time(),
            'tenant_id' => (int)$data['tenant_id'],
            'admin_type' => SystemAdmin::TYPE_TENANT,
        ];
        if (!$adminDao->save($adminData)) {
            throw new AdminException('创建租户管理员失败');
        }
        return true;
    }
}
