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

use app\models\system\admin\SystemAdmin;
use app\services\TenantServices;
use crmeb\basic\BaseServices;
use crmeb\services\tenant\TenantContext;
use crmeb\exceptions\AdminException;
use app\dao\system\admin\SystemAdminDao;
use app\services\system\SystemMenusServices;
use crmeb\services\FormBuilder;
use crmeb\services\SwooleTaskService;

/**
 * 管理员service
 * Class SystemAdminServices
 * @package app\services\system\admin
 * @method getAdminIds(int $level) 根据管理员等级获取管理员id
 * @method getOrdAdmin(string $field, int $level) 获取低于等级的管理员名称和id
 */
class SystemAdminServices extends BaseServices
{

    /**
     * form表单创建
     * @var FormBuilder
     */
    protected $builder;

    /**
     * SystemAdminServices constructor.
     * @param SystemAdminDao $dao
     */
    public function __construct(SystemAdminDao $dao, FormBuilder $builder)
    {
        $this->dao     = $dao;
        $this->builder = $builder;
    }

    /**
     * 管理员登陆
     * @param string $account
     * @param string $password
     * @return array|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function verifyLogin(string $account, string $password)
    {
        //登录先于租户上下文建立，按账号全局定位管理员
        $adminInfo = TenantContext::withoutTenant(function () use ($account) {
            return $this->dao->accountByAdmin($account);
        });
        if (!$adminInfo) {
            throw new AdminException('管理员不存在!');
        }
        if (!$adminInfo->status) {
            throw new AdminException('您已被禁止登录!');
        }
        if (!password_verify($password, $adminInfo->pwd)) {
            throw new AdminException('账号或密码错误，请重新输入');
        }
        //租户管理员登录时校验租户可用性（禁用/到期拦截）
        if (!SystemAdmin::isPlatformAdmin($adminInfo)) {
            /** @var TenantServices $tenantServices */
            $tenantServices = app()->make(TenantServices::class);
            $tenantServices->checkUsable((int)($adminInfo->tenant_id ?? 0));
        }
        $adminInfo->last_time = time();
        $adminInfo->last_ip   = app('request')->ip();
        $adminInfo->login_count++;
        TenantContext::withoutTenant(function () use ($adminInfo) {
            $adminInfo->save();
        });

        return $adminInfo;
    }

    /**
     * 后台登陆获取菜单获取token
     * @param string $account
     * @param string $password
     * @param string $type
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function login(string $account, string $password, string $type)
    {
        $adminInfo = $this->verifyLogin($account, $password);
        //登录路由无租户上下文，菜单/角色/品牌配置读取需在管理员所属租户上下文中执行
        return TenantContext::runAs((int)($adminInfo->tenant_id ?? 0), function () use ($adminInfo, $type) {
            $tokenInfo = $this->createToken($adminInfo->id, $type, (int)($adminInfo->tenant_id ?? 0));
            /** @var SystemMenusServices $services */
            $services = app()->make(SystemMenusServices::class);
            //登录时必然处于自身视角：平台账号剔除租户专属页，否则直接输网址仍进得去
            $scope = SystemAdmin::isPlatformAdmin($adminInfo->toArray())
                ? SystemMenusServices::SCOPE_PLATFORM
                : SystemMenusServices::SCOPE_TENANT;
            [$menus, $uniqueAuth] = $services->getMenusList($adminInfo->roles, (int)$adminInfo['level'], $scope);
            return [
                'token'             => $tokenInfo['token'],
                'expires_time'      => $tokenInfo['params']['exp'],
                'menus'             => $menus,
                'unique_auth'       => $uniqueAuth,
                'user_info'         => [
                    'id'       => $adminInfo->getData('id'),
                    'account'  => $adminInfo->getData('account'),
                    'head_pic' => $adminInfo->getData('head_pic'),
                ],
                'logo'              => sys_config('site_logo'),
                'logo_square'       => sys_config('site_logo_square'),
                'version'           => get_crmeb_version(),
                'newOrderAudioLink' => get_file_link(sys_config('new_order_audio_link', ''))
            ];
        });
    }

    /**
     * 获取登陆前的login等信息
     * @return array
     */
    public function getLoginInfo()
    {
        return [
            'slide'          => sys_data('admin_login_slide') ?? [],
            'logo_square'    => sys_config('site_logo_square'),//透明
            'logo_rectangle' => sys_config('site_logo'),//方形
            'login_logo'     => sys_config('login_logo'),//登陆
            //官网被解析到独立域名后，登录页需要一条回官网的路；留空则前端不展示
            'website_url'    => \crmeb\utils\SiteUrl::website()
        ];
    }

    /**
     * 管理员列表
     * @param array $where
     * @return array
     */
    public function getAdminList(array $where)
    {
        [$page, $limit] = $this->getPageValue();
        $list  = $this->dao->getList($where, $page, $limit);
        $count = $this->dao->count($where);

        /** @var SystemRoleServices $service */
        $service = app()->make(SystemRoleServices::class);
        $allRole = $service->getRoleArray();
        foreach ($list as &$item) {
            if ($item['roles']) {
                $roles = [];
                foreach ($item['roles'] as $id) {
                    if (isset($allRole[$id])) $roles[] = $allRole[$id];
                }
                if ($roles) {
                    $item['roles'] = implode(',', $roles);
                } else {
                    $item['roles'] = '';
                }
            }
            $item['_add_time']  = date('Y-m-d H:i:s', $item['add_time']);
            $item['_last_time'] = $item['last_time'] ? date('Y-m-d H:i:s', $item['last_time']) : '';
        }
        return compact('list', 'count');
    }

    /**
     * 创建管理员表单
     * @param int $level
     * @param array $formData
     * @return mixed
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function createAdminForm(int $level, array $formData = [])
    {
        $f[] = $this->builder->input('account', '管理员账号', $formData['account'] ?? '')->required('请填写管理员账号');
        $f[] = $this->builder->input('pwd', '管理员密码')->type('password')->required('请填写管理员密码');
        $f[] = $this->builder->input('conf_pwd', '确认密码')->type('password')->required('请输入确认密码');
        $f[] = $this->builder->input('real_name', '管理员姓名', $formData['real_name'] ?? '')->required('请输入管理员姓名');

        /** @var SystemRoleServices $service */
        $service = app()->make(SystemRoleServices::class);
        $options = $service->getRoleFormSelect($level);
        $f[]     = $this->builder->select('roles', '管理员身份', $formData['roles'] ?? [])->setOptions(FormBuilder::setOptions($options))->multiple(true)->required('请选择管理员身份');
        $f[]     = $this->builder->radio('status', '状态', $formData['status'] ?? 1)->options([['label' => '开启', 'value' => 1], ['label' => '关闭', 'value' => 0]]);
        return $f;
    }

    /**
     * 添加管理员form表单获取
     * @param int $level
     * @return array
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function createForm(int $level)
    {
        return create_form('管理员添加', $this->createAdminForm($level), $this->url('/setting/admin'));
    }

    /**
     * 创建管理员
     * @param array $data
     * @return bool
     */
    public function create(array $data)
    {
        if ($data['conf_pwd'] != $data['pwd']) {
            throw new AdminException('两次输入的密码不相同');
        }
        unset($data['conf_pwd']);

        //管理员账号全局唯一（统一入口按账号定位租户），需跨租户检查
        $accountExists = TenantContext::withoutTenant(function () use ($data) {
            return $this->dao->count(['account' => $data['account'], 'is_del' => 0]);
        });
        if ($accountExists) {
            throw new AdminException('管理员账号已存在');
        }

        $data['pwd']      = $this->passwordHash($data['pwd']);
        $data['add_time'] = time();
        $data['roles']    = implode(',', $data['roles']);
        //归属跟随创建者视角：平台视角产出平台管理员，租户视角产出该租户管理员
        $data['tenant_id']  = TenantContext::id();
        $data['admin_type'] = $data['tenant_id'] > 0 ? SystemAdmin::TYPE_TENANT : SystemAdmin::TYPE_PLATFORM;

        return $this->transaction(function () use ($data) {
            if ($this->dao->save($data)) {
                \crmeb\services\CacheService::clear();
                return true;
            } else {
                throw new AdminException('添加失败');
            }
        });
    }

    /**
     * 修改管理员表单
     * @param int $level
     * @param int $id
     * @return array
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function updateForm(int $level, int $id)
    {
        $adminInfo = $this->dao->get($id);
        if (!$adminInfo) {
            throw new AdminException('管理员不存在!');
        }
        if ($adminInfo->is_del) {
            throw new AdminException('管理员已经删除');
        }
        return create_form('管理员修改', $this->createAdminForm($level, $adminInfo->toArray()), $this->url('/setting/admin/' . $id), 'PUT');
    }

    /**
     * 修改管理员
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function save(int $id, array $data)
    {
        if (!$adminInfo = $this->dao->get($id)) {
            throw new AdminException('管理员不存在,无法修改');
        }
        if ($adminInfo->is_del) {
            throw new AdminException('管理员已经删除');
        }
        //修改密码
        if ($data['pwd']) {

            if (!$data['conf_pwd']) {
                throw new AdminException('请输入确认密码');
            }

            if ($data['conf_pwd'] != $data['pwd']) {
                throw new AdminException('上次输入的密码不相同');
            }
            $adminInfo->pwd = $this->passwordHash($data['pwd']);
        }
        //修改账号（账号全局唯一，需跨租户检查）
        $accountConflict = isset($data['account']) && $data['account'] != $adminInfo->account
            && TenantContext::withoutTenant(function () use ($data, $id) {
                return $this->dao->isAccountUsable($data['account'], $id);
            });
        if ($accountConflict) {
            throw new AdminException('管理员账号已存在');
        }
        if (isset($data['roles'])) {
            $adminInfo->roles = implode(',', $data['roles']);
        }
        $adminInfo->real_name = $data['real_name'] ?? $adminInfo->real_name;
        $adminInfo->account   = $data['account'] ?? $adminInfo->account;
        $adminInfo->status    = $data['status'];
        if ($adminInfo->save()) {
            \crmeb\services\CacheService::clear();
            return true;
        } else {
            return false;
        }
    }

    /**
     * 修改当前管理员信息
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function updateAdmin(int $id, array $data)
    {
        $adminInfo = $this->dao->get($id);
        if (!$adminInfo)
            throw new AdminException('管理员信息未查到');
        if ($adminInfo->is_del) {
            throw new AdminException('管理员已经删除');
        }
        if (!$data['real_name'])
            throw new AdminException('管理员姓名不能为空');
        if ($data['pwd']) {
            if (!password_verify($data['pwd'], $adminInfo['pwd']))
                throw new AdminException('原始密码错误');
            if (!$data['new_pwd'])
                throw new AdminException('请输入新密码');
            if (!$data['conf_pwd'])
                throw new AdminException('请输入确认密码');
            if ($data['new_pwd'] != $data['conf_pwd'])
                throw new AdminException('两次输入的密码不一致');
            $adminInfo->pwd = $this->passwordHash($data['new_pwd']);
        }

        $adminInfo->real_name = $data['real_name'];
        $adminInfo->head_pic  = $data['head_pic'];
        if ($adminInfo->save())
            return true;
        else
            return false;
    }


    /**
     * @param $event
     */
    public function adminNewPush()
    {
        try {

            SwooleTaskService::admin()->type('ADMIN_NEW_PUSH')->data([])->push();
        } catch (\Exception $e) {
        }
    }
}
