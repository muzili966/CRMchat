<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\controller\pay;

use app\services\payment\CashierServices;
use crmeb\services\payment\CashierLink;
use crmeb\services\payment\PaymentException;
use think\Request;

/**
 * 收银台接口
 *
 * 付款人不登录，凭链接上的单号与签名访问。
 * Class Cashier
 * @package app\controller\pay
 */
class Cashier
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var CashierServices
     */
    protected $services;

    /**
     * @param Request $request
     * @param CashierServices $services
     */
    public function __construct(Request $request, CashierServices $services)
    {
        $this->request = $request;
        $this->services = $services;
    }

    /**
     * 支付单摘要与可选支付方式
     * @return \think\Response
     */
    public function info()
    {
        [$payNo, $signature] = $this->credential();
        return $this->respond(function () use ($payNo, $signature) {
            return $this->services->info($payNo, $signature, $this->request->isMobile());
        });
    }

    /**
     * 选定支付方式下单
     * @return \think\Response
     */
    public function pay()
    {
        [$payNo, $signature] = $this->credential();
        $channel = trim((string)$this->request->post('channel', ''));
        return $this->respond(function () use ($payNo, $signature, $channel) {
            return $this->services->pay(
                ['pay_no' => $payNo, 'signature' => $signature, 'channel' => $channel],
                ['mobile' => $this->request->isMobile(), 'client_ip' => (string)$this->request->ip()]
            );
        });
    }

    /**
     * 轮询支付状态
     * @return \think\Response
     */
    public function status()
    {
        [$payNo, $signature] = $this->credential();
        return $this->respond(function () use ($payNo, $signature) {
            return $this->services->status($payNo, $signature);
        });
    }

    /**
     * @return array 单号、签名
     */
    protected function credential(): array
    {
        return [
            trim((string)$this->request->param(CashierLink::QUERY_NO, '')),
            trim((string)$this->request->param(CashierLink::QUERY_SIGN, '')),
        ];
    }

    /**
     * 业务上的失败（链接无效、单已关闭、渠道不可用）给付款人看原因；其他异常照常上抛
     * @param callable $action
     * @return \think\Response
     */
    protected function respond(callable $action)
    {
        try {
            return app('json')->success($action());
        } catch (PaymentException $e) {
            return app('json')->fail($e->getMessage());
        }
    }
}
