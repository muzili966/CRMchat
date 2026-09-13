<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\controller\admin\platform;

use app\controller\admin\AuthController;
use app\services\payment\PaymentServices;
use app\services\payment\PaymentSettleServices;
use crmeb\services\payment\PaymentException;
use crmeb\services\payment\PaymentSettlement;

/**
 * 支付单（平台专属）
 *
 * 平台向租户收款的单据，跨租户查看与处理，故仅平台端可用。
 * Class Payment
 * @package app\controller\admin\platform
 */
class Payment extends AuthController
{
    /**
     * 人工确认到账后的提示
     */
    const CONFIRM_MESSAGES = [
        PaymentSettlement::FULFILL => '已确认到账并完成开通',
        PaymentSettlement::ABNORMAL => '已记录到账，但未能开通，已标为异常，请查看异常原因',
        PaymentSettlement::DUPLICATE => '该单已处理过',
    ];

    /**
     * @var PaymentSettleServices
     */
    protected $settleServices;

    /**
     * @param PaymentServices $services
     * @param PaymentSettleServices $settleServices
     */
    public function __construct(PaymentServices $services, PaymentSettleServices $settleServices)
    {
        parent::__construct();
        $this->mustPlatformAdmin('支付单');
        $this->services = $services;
        $this->settleServices = $settleServices;
    }

    /**
     * 支付单列表
     * @return mixed
     */
    public function index()
    {
        $where = $this->request->getMore([
            ['status', ''],
            ['channel', ''],
            [['tenant_id', 'd'], 0],
            ['pay_no', ''],
            ['abnormal', ''],
        ]);
        return $this->success($this->services->getPaymentList($where));
    }

    /**
     * 渠道与状态选项
     * @return mixed
     */
    public function options()
    {
        return $this->success($this->services->options());
    }

    /**
     * 创建套餐续费支付单，返回链接与二维码
     * @return mixed
     */
    public function save()
    {
        $data = $this->request->postMore([
            [['tenant_id', 'd'], 0],
            [['plan_id', 'd'], 0],
            [['months', 'd'], 1],
            ['remark', ''],
        ]);
        return $this->guard('支付单已创建', function () use ($data) {
            return $this->services->createForAdmin($data, (array)$this->adminInfo);
        });
    }

    /**
     * 待支付单的链接与二维码
     * @param int $id
     * @return mixed
     */
    public function deliver($id)
    {
        return $this->guard('ok', function () use ($id) {
            return $this->services->links((int)$id);
        });
    }

    /**
     * 人工确认到账
     * @param int $id
     * @return mixed
     */
    public function confirm($id)
    {
        $data = $this->request->postMore([['trade_no', ''], ['paid_amount', ''], ['remark', '']]);
        $data['operator'] = (string)($this->adminInfo['real_name'] ?? '');
        try {
            $action = $this->settleServices->confirmManual((int)$id, $data);
        } catch (PaymentException $e) {
            return $this->fail($e->getMessage());
        }
        return $this->success(self::CONFIRM_MESSAGES[$action] ?? 'ok', ['action' => $action]);
    }

    /**
     * 关闭待支付单
     * @param int $id
     * @return mixed
     */
    public function close($id)
    {
        return $this->guard('支付单已关闭', function () use ($id) {
            $this->settleServices->close((int)$id);
            return [];
        });
    }

    /**
     * 向渠道查询并同步支付状态
     * @param int $id
     * @return mixed
     */
    public function sync($id)
    {
        return $this->guard('同步完成', function () use ($id) {
            return ['paid' => $this->settleServices->syncById((int)$id)];
        });
    }

    /**
     * 已支付未开通的单补开通
     * @param int $id
     * @return mixed
     */
    public function fulfill($id)
    {
        return $this->guard('已补开通', function () use ($id) {
            $this->settleServices->refulfill((int)$id);
            return [];
        });
    }

    /**
     * 支付层的业务失败给操作人看原因
     * @param string $message
     * @param callable $action
     * @return mixed
     */
    protected function guard(string $message, callable $action)
    {
        try {
            return $this->success($message, $action());
        } catch (PaymentException $e) {
            return $this->fail($e->getMessage());
        }
    }
}
