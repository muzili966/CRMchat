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
}
