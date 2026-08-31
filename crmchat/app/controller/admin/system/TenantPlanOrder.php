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
use app\services\TenantPlanOrderServices;
use app\services\TenantPlanServices;
use crmeb\services\tenant\TenantContext;

/**
 * 套餐订购对账：平台未选租户视角看全部，租户管理员看自己的
 * Class TenantPlanOrder
 * @package app\controller\admin\system
 */
class TenantPlanOrder extends AuthController
{

    /**
     * TenantPlanOrder constructor.
     * @param TenantPlanOrderServices $services
     */
    public function __construct(TenantPlanOrderServices $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    /**
     * 对账查询条件
     * @return array
     */
    protected function orderWhere(): array
    {
        $where = $this->request->getMore([
            [['plan_id', 'd'], 0],
            ['order_no', ''],
            ['status', ''],
            [['start', 'd'], 0],
            [['end', 'd'], 0],
        ]);
        if ($where['start'] && $where['end']) {
            $where['create_time_between'] = [$where['start'], $where['end']];
        }
        unset($where['start'], $where['end']);
        return $where;
    }

    /**
     * 订购对账列表
     * @return mixed
     */
    public function index()
    {
        $where = $this->orderWhere();
        return $this->success($this->withPlatformScope(function () use ($where) {
            return $this->services->getOrderList($where);
        }));
    }

    /**
     * 对账CSV导出
     * @return mixed
     */
    public function export()
    {
        $where = $this->orderWhere();
        //前端只是禁用按钮，能力约束必须在服务端兜底，否则直接调接口即可绕过
        $tenantId = (int)TenantContext::id();
        if ($tenantId) {
            /** @var TenantPlanServices $planServices */
            $planServices = app()->make(TenantPlanServices::class);
            $planServices->assertFeature($tenantId, 'data_export', '当前套餐不支持数据导出，请升级套餐');
        }
        $format = (string)$this->request->param('format', TenantPlanOrderServices::FORMAT_CSV);
        //格式来自前端，白名单收口避免拼进文件名
        if (!in_array($format, TenantPlanOrderServices::EXPORT_FORMATS, true)) {
            $format = TenantPlanOrderServices::FORMAT_CSV;
        }
        $url = $this->withPlatformScope(function () use ($where, $format) {
            return $this->services->exportOrders($where, $format);
        });
        return $this->success('导出成功', ['url' => $url]);
    }
}
