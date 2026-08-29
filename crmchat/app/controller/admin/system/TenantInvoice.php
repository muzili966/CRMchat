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
use app\services\TenantInvoiceServices;
use crmeb\services\tenant\TenantContext;

/**
 * 租户发票管理：租户视角申请，平台开具/驳回
 * Class TenantInvoice
 * @package app\controller\admin\system
 */
class TenantInvoice extends AuthController
{

    /**
     * TenantInvoice constructor.
     * @param TenantInvoiceServices $services
     */
    public function __construct(TenantInvoiceServices $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    /**
     * 发票列表：平台未选租户视角看全部，否则看当前租户
     * @return mixed
     */
    public function index()
    {
        $where = $this->request->getMore([
            ['status', ''],
        ]);
        return $this->success($this->withPlatformScope(function () use ($where) {
            return $this->services->getInvoiceList($where);
        }));
    }

    /**
     * 申请开票（需处于租户视角）
     * @return mixed
     */
    public function apply()
    {
        if (!TenantContext::id()) {
            return $this->fail('请先切换到租户视角再申请发票');
        }
        $data = $this->request->postMore([
            [['order_id', 'd'], 0],
            ['title', ''],
            ['tax_no', ''],
            [['type', 'd'], \app\models\TenantInvoice::TYPE_NORMAL],
            ['email', ''],
        ]);
        if (!$data['order_id']) {
            return $this->fail('请选择需要开票的对账单');
        }
        $this->services->apply($data);
        return $this->success('发票申请已提交');
    }

    /**
     * 开具/驳回发票（仅平台超管）
     * @param $id
     * @return mixed
     */
    public function audit($id)
    {
        if (!SystemAdmin::isPlatformAdmin($this->adminInfo)) {
            return $this->fail('仅平台管理员可以处理发票');
        }
        if (!$id) {
            return $this->fail('缺少参数');
        }
        $data = $this->request->postMore([
            [['status', 'd'], 0],
            ['invoice_no', ''],
            ['remark', ''],
        ]);
        $this->withPlatformScope(function () use ($id, $data) {
            $this->services->audit((int)$id, $data);
        });
        return $this->success('处理成功');
    }
}
