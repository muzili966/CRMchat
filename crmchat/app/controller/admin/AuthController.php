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

namespace app\controller\admin;

use app\models\system\admin\SystemAdmin;
use crmeb\services\tenant\TenantContext;
use crmeb\traits\Help;
use think\facade\Validate;

/**
 * Class AuthController
 * @package app\controller\admin
 */
abstract class AuthController
{
    use Help;

    /**
     * 当前登陆管理员信息
     * @var
     */
    protected $adminInfo;

    /**
     * 当前登陆管理员ID
     * @var
     */
    protected $adminId;

    /**
     * 当前管理员权限
     * @var array
     */
    protected $auth = [];

    /**
     * service
     * @var
     */
    protected $services;

    /**
     * @var
     */
    protected $request;

    /**
     * @var object|\think\App
     */
    protected $app;

    /**
     * AuthController constructor.
     */
    public function __construct()
    {
        $this->app     = app();
        $this->request = app()->request;
        $this->initialize();
    }

    /**
     * 初始化
     */
    protected function initialize()
    {
        $this->adminId   = $this->request->adminId();
        $this->adminInfo = $this->request->adminInfo();
        $this->auth      = $this->request->adminInfo['rule'] ?? [];
    }

    /**
     * 平台超管未选择租户视角时跨全租户执行（如全局列表），
     * 租户管理员或已选租户视角时按当前上下文执行
     * @param \Closure $fn
     * @return mixed
     */
    protected function withPlatformScope(\Closure $fn)
    {
        if (SystemAdmin::isPlatformAdmin($this->adminInfo) && !TenantContext::get()) {
            return TenantContext::withoutTenant($fn);
        }
        return $fn();
    }

    /**
     * 平台专属功能的硬守卫
     *
     * 菜单不可见只是隐藏入口，租户仍可直接构造请求；
     * 涉及平台级资源（权限规则、系统配置、组合数据等）的控制器必须在构造函数调用本方法
     * @param string $scene 用于提示的功能名
     * @return void
     */
    protected function mustPlatformAdmin(string $scene = '该功能')
    {
        if (!SystemAdmin::isPlatformAdmin($this->adminInfo)) {
            throw new \crmeb\exceptions\AdminException($scene . '仅平台管理员可操作');
        }
    }

    /**
     * 数据验证
     * @param array $data
     * @param $validate
     * @param null $message
     * @param bool $batch
     * @return bool
     */
    protected function validate(array $data, $validate, $message = null, bool $batch = false)
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                // 支持场景
                list($validate, $scene) = explode('.', $validate);
            }
            $class = false !== strpos($validate, '\\') ? $validate : $this->app->parseClass('validate', $validate);
            $v     = new $class();
            if (!empty($scene)) {
                $v->scene($scene);
            }

            if (is_string($message) && empty($scene)) {
                $v->scene($message);
            }
        }

        if (is_array($message))
            $v->message($message);


        // 是否批量验证
        if ($batch) {
            $v->batch(true);
        }

        return $v->failException(true)->check($data);
    }
}
