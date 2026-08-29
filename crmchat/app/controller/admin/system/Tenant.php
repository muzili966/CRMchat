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

namespace app\controller\admin\system;


use app\controller\admin\AuthController;
use app\models\system\admin\SystemAdmin;
use app\services\TenantServices;

/**
 * 租户管理（仅平台超管可操作）
 * Class Tenant
 * @package app\controller\admin\system
 */
class Tenant extends AuthController
{

    /**
     * Tenant constructor.
     * @param TenantServices $services
     */
    public function __construct(TenantServices $services)
    {
        parent::__construct();
        $this->services = $services;
        $this->checkPlatformAdmin();
    }

    /**
     * 仅平台超管可进入租户管理
     * @return void
     */
    protected function checkPlatformAdmin()
    {
        $adminType = $this->adminInfo['admin_type'] ?? SystemAdmin::TYPE_TENANT;
        if ($adminType != SystemAdmin::TYPE_PLATFORM) {
            throw new \crmeb\exceptions\AdminException('仅平台管理员可以管理租户');
        }
    }

    /**
     * 租户列表
     * @return mixed
     */
    public function index()
    {
        $where = $this->request->getMore([
            ['name', '', 'name_like'],
            ['status', ''],
        ]);
        return $this->success($this->services->getTenantList($where));
    }

    /**
     * 创建租户
     * @return mixed
     */
    public function save()
    {
        $data = $this->request->postMore([
            ['name', ''],
            ['plan', ''],
            [['expire_time', 'd'], 0],
            ['domain', ''],
            ['remark', ''],
        ]);
        $this->services->create($data);
        return $this->success('租户创建成功');
    }

    /**
     * 修改租户
     * @param $id
     * @return mixed
     */
    public function update($id)
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }
        $data = $this->request->postMore([
            ['name', ''],
            ['plan', ''],
            [['expire_time', 'd'], 0],
            ['domain', ''],
            ['remark', ''],
        ]);
        $this->services->edit((int)$id, $data);
        return $this->success('修改成功');
    }

    /**
     * 启用/禁用租户
     * @param $id
     * @param $status
     * @return mixed
     */
    public function set_status($id, $status)
    {
        if ($status == '' || !$id) {
            return $this->fail('参数错误');
        }
        $this->services->setStatus((int)$id, (int)$status);
        return $this->success($status == 0 ? '租户已禁用' : '租户已启用');
    }

    /**
     * 创建租户管理员
     * @return mixed
     */
    public function createAdmin()
    {
        $data = $this->request->postMore([
            [['tenant_id', 'd'], 0],
            ['account', ''],
            ['pwd', ''],
            ['conf_pwd', ''],
            ['real_name', ''],
            ['roles', []],
        ]);
        if (!$data['tenant_id']) {
            return $this->fail('缺少租户ID');
        }
        $this->services->createTenantAdmin($data);
        return $this->success('租户管理员创建成功');
    }
}
