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


use app\dao\TenantInvoiceDao;
use app\dao\TenantPlanOrderDao;
use app\models\TenantInvoice;
use app\models\TenantPlanOrder;
use crmeb\basic\BaseServices;
use crmeb\exceptions\AdminException;

/**
 * 租户发票service
 * Class TenantInvoiceServices
 * @package app\services
 */
class TenantInvoiceServices extends BaseServices
{

    /**
     * TenantInvoiceServices constructor.
     * @param TenantInvoiceDao $dao
     */
    public function __construct(TenantInvoiceDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 申请开票（当前租户视角，订单查询受租户Scope约束）
     * @param array $data order_id/title/tax_no/type/email
     * @return \crmeb\basic\BaseModel|\think\Model
     */
    public function apply(array $data)
    {
        if (!$data['title']) {
            throw new AdminException('请填写发票抬头');
        }
        if ((int)$data['type'] == TenantInvoice::TYPE_SPECIAL && !$data['tax_no']) {
            throw new AdminException('专票必须填写税号');
        }
        /** @var TenantPlanOrderDao $orderDao */
        $orderDao = app()->make(TenantPlanOrderDao::class);
        $order = $orderDao->get(['id' => (int)$data['order_id'], 'status' => TenantPlanOrder::STATUS_EFFECTIVE]);
        if (!$order) {
            throw new AdminException('对账单不存在或已作废');
        }
        if ((float)$order->amount <= 0) {
            throw new AdminException('免费套餐无需开票');
        }
        if ($this->dao->getCount([['order_id', '=', $order->id], ['status', '<>', TenantInvoice::STATUS_REJECTED]])) {
            throw new AdminException('该对账单已申请过发票');
        }
        return $this->dao->save([
            'tenant_id' => (int)$order->tenant_id,
            'order_id' => (int)$order->id,
            'order_no' => $order->order_no,
            'title' => $data['title'],
            'tax_no' => (string)($data['tax_no'] ?? ''),
            'type' => (int)($data['type'] ?? TenantInvoice::TYPE_NORMAL),
            'amount' => $order->amount,
            'email' => (string)($data['email'] ?? ''),
            'status' => TenantInvoice::STATUS_PENDING,
            'create_time' => time(),
            'update_time' => time(),
        ]);
    }

    /**
     * 发票列表（调用方决定视角）
     * @param array $where
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getInvoiceList(array $where)
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getInvoiceList($where, $page, $limit);
        $count = $this->dao->count($where);
        foreach ($list as &$item) {
            $item['_create_time'] = date('Y-m-d H:i:s', $item['create_time']);
        }
        return compact('list', 'count');
    }

    /**
     * 开具/驳回发票（平台操作）
     * @param int $id
     * @param array $data status/invoice_no/remark
     * @return bool
     */
    public function audit(int $id, array $data)
    {
        $invoice = $this->dao->get($id);
        if (!$invoice) {
            throw new AdminException('发票记录不存在');
        }
        if ($invoice->status != TenantInvoice::STATUS_PENDING) {
            throw new AdminException('该发票已处理，不能重复操作');
        }
        $status = (int)$data['status'];
        if (!in_array($status, [TenantInvoice::STATUS_ISSUED, TenantInvoice::STATUS_REJECTED])) {
            throw new AdminException('无效的处理状态');
        }
        if ($status == TenantInvoice::STATUS_ISSUED && empty($data['invoice_no'])) {
            throw new AdminException('开具发票需填写发票号码');
        }
        if ($status == TenantInvoice::STATUS_REJECTED && empty($data['remark'])) {
            throw new AdminException('驳回需填写原因');
        }
        return false !== $this->dao->update($id, [
            'status' => $status,
            'invoice_no' => (string)($data['invoice_no'] ?? ''),
            'remark' => (string)($data['remark'] ?? ''),
            'update_time' => time(),
        ]);
    }
}
