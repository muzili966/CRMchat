<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment\contract;

use crmeb\services\payment\dto\PaymentNotice;
use crmeb\services\payment\dto\PaymentOrder;
use crmeb\services\payment\dto\PaymentQuote;

/**
 * 可付费的业务
 *
 * 套餐续费是第一个，以后加购坐席、AI 次数包、增值服务都实现这个接口即可，
 * 支付单、渠道、投递方式一行不用改。
 */
interface PayableInterface
{
    /**
     * 业务类型，必须与配置里的注册键一致
     * @return string
     */
    public function bizType(): string;

    /**
     * 报价：按服务端数据计价，并校验这笔能不能买
     *
     * 金额只能出自这里。下单请求里前端带来的任何金额都不可信，改个参数
     * 就能一分钱买旗舰版。
     * @param int $tenantId 付款的租户
     * @param array $params 业务参数，如套餐与月数
     * @return PaymentQuote
     */
    public function quote(int $tenantId, array $params): PaymentQuote;

    /**
     * 履约：到账后开通对应权益
     *
     * 调用方在同一事务内先把支付单从待支付翻成已支付、影响行数为 1 才会调这里，
     * 渠道重复回调不会重复开通。实现仍应自带幂等，挡住人工补单这类绕过状态机的调用。
     * @param PaymentOrder $order
     * @param PaymentNotice $notice
     * @return void
     */
    public function fulfill(PaymentOrder $order, PaymentNotice $notice): void;
}
