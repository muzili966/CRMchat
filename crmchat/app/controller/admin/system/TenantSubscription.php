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
use app\services\TenantPlanServices;
use crmeb\services\tenant\TenantContext;

/**
 * 租户端订阅信息（租户视角查看自己的套餐；平台侧带tenant_id视角参数亦可查看）
 * Class TenantSubscription
 * @package app\controller\admin\system
 */
class TenantSubscription extends AuthController
{

    /**
     * TenantSubscription constructor.
     * @param TenantPlanServices $services
     */
    public function __construct(TenantPlanServices $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    /**
     * 我的订阅概览
     * @return mixed
     */
    public function my()
    {
        $tenantId = (int)TenantContext::id();
        if (!$tenantId) {
            return $this->fail('平台账号请通过租户视角(tenant_id)查看订阅信息');
        }
        return $this->success($this->services->getMySubscription($tenantId));
    }

    /**
     * 能力门禁：供各功能页判断是否展示升级提示
     * @return mixed
     */
    public function features()
    {
        $tenantId = (int)TenantContext::id();
        //平台视角不受套餐约束，一律放行，避免平台预览租户页面时被拦
        if (!$tenantId) {
            return $this->success(['plan_name' => '', 'features' => [], 'upgrade' => [], 'unlimited' => true]);
        }
        return $this->success($this->services->getFeatureGate($tenantId) + ['unlimited' => false]);
    }

    /**
     * 在售套餐展示（租户升级续订选择用，只读）
     * @return mixed
     */
    public function plans()
    {
        return $this->success($this->services->getOptions());
    }

    /**
     * 设置独立域名（高阶套餐能力，租户自助）
     * @return mixed
     */
    public function saveDomain()
    {
        $tenantId = (int)TenantContext::id();
        if (!$tenantId) {
            return $this->fail('平台账号请切换到租户视角后设置');
        }
        if (!$this->services->hasFeature($tenantId, \app\services\TenantServices::FEATURE_CUSTOM_DOMAIN)) {
            return $this->fail('当前套餐不支持独立域名，请升级套餐');
        }
        [$domain] = $this->request->postMore([['domain', '']], true);
        /** @var \app\services\TenantServices $tenantServices */
        $tenantServices = app()->make(\app\services\TenantServices::class);
        $tenantServices->saveDomain($tenantId, (string)$domain);
        return $this->success('保存成功，请将该域名解析到平台服务器后生效');
    }
}
