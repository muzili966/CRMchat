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
use app\services\system\admin\SystemRoleServices;
use app\services\system\SystemMenusServices;

/**
 * Class SystemRole
 * @package app\controller\admin\system
 */
class Role extends AuthController
{
    /**
     * SystemRole constructor.
     * @param SystemRoleServices $services
     */
    public function __construct(SystemRoleServices $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {
        $where          = $this->request->getMore([
            ['status', ''],
            ['role_name', ''],
        ]);
        $where['level'] = $this->adminInfo['level'] + 1;
        return $this->success($this->services->getRoleList($where));
    }

    /**
     * 显示创建资源表单页.
     *
     * @return \think\Response
     */
    public function create(SystemMenusServices $services)
    {
        $menus = $services->getmenus($this->adminInfo['level'] == 0 ? [] : $this->adminInfo['roles']);
        return $this->success(compact('menus'));
    }

    /**
     * 保存新建的资源
     *
     * @return \think\Response
     */
    public function save($id)
    {
        $data = $this->request->postMore([
            'role_name',
            ['status', 0],
            ['checked_menus', [], '', 'rules']
        ]);
        if (!$data['role_name']) return $this->fail('请输入身份名称');
        if (!is_array($data['rules']) || !count($data['rules']))
            return $this->fail('请选择最少一个权限');
        $illegal = $this->filterGrantableRules($data['rules']);
        if ($illegal) {
            return $this->fail('不能授予自身不具备的权限');
        }
        $data['rules'] = implode(',', $data['rules']);
        if ($id) {
            if (!$this->services->update($id, $data)) return $this->fail('修改失败!');
            \crmeb\services\CacheService::clear();
            return $this->success('修改成功!');
        } else {
            $data['level'] = $this->adminInfo['level'] + 1;
            if (!$this->services->save($data)) return $this->fail('添加身份失败!');
            \crmeb\services\CacheService::clear();
            return $this->success('添加身份成功!');
        }
    }

    /**
     * 找出超出授权者自身权限范围的菜单
     *
     * 防提权：租户管理员若能勾选平台专属菜单，即可自铸权限点越权访问平台接口。
     * 平台超管(level=0)不受限，其余账号只能授予自己已有权限的子集
     * @param array $rules 提交的菜单id
     * @return array 越权的菜单id
     */
    protected function filterGrantableRules(array $rules): array
    {
        if ((int)$this->adminInfo['level'] === 0) {
            return [];
        }
        /** @var SystemMenusServices $menusServices */
        $menusServices = app()->make(SystemMenusServices::class);
        $granted = $menusServices->getGrantableMenuIds((array)$this->adminInfo['roles']);
        return array_values(array_diff(array_map('intval', $rules), $granted));
    }

    /**
     * 显示编辑资源表单页.
     *
     * @param int $id
     * @return \think\Response
     */
    public function edit(SystemMenusServices $services, $id)
    {
        $role = $this->services->get($id);
        if (!$role) {
            return $this->fail('修改的规格不存在');
        }
        $menus = $services->getMenus($this->adminInfo['level'] == 0 ? [] : $this->adminInfo['roles']);
        return $this->success(['role' => $role->toArray(), 'menus' => $menus]);
    }

    /**
     * 删除指定资源
     *
     * @param int $id
     * @return \think\Response
     */
    public function delete($id)
    {
        if (!$this->services->delete($id))
            return $this->fail('删除失败,请稍候再试!');
        else {
            \crmeb\services\CacheService::clear();
            return $this->success('删除成功!');
        }
    }

    /**
     * 修改状态
     * @param $id
     * @param $status
     * @return mixed
     */
    public function set_status($id, $status)
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }
        $role = $this->services->get($id);
        if (!$role) {
            return $this->fail('没有查到此身份');
        }
        $role->status = $status;
        if ($role->save()) {
            \crmeb\services\CacheService::clear();
            return $this->success('修改成功');
        } else {
            return $this->fail('修改失败');
        }
    }
}
