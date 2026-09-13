<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment;

/**
 * 支付单状态机
 *
 * 状态只能往前走。落库时一律用「WHERE status = 原状态」做条件更新，影响行数为 1
 * 才算翻转成功——渠道并发重复回调时，只有一个请求能拿到履约资格。
 */
final class PaymentStatus
{
    /**
     * 待支付
     */
    const PENDING = 0;

    /**
     * 已支付
     */
    const PAID = 1;

    /**
     * 已关闭：过期或主动取消
     */
    const CLOSED = 2;

    /**
     * 已退款
     */
    const REFUNDED = 3;

    /**
     * 合法流转
     *
     * 已关闭不能直接变已支付：关单后付款人仍对着旧二维码付了钱，属于要人工处理的
     * 异常单（补开通或原路退回），不能被回调悄悄翻成已支付。
     */
    const TRANSITIONS = [
        self::PENDING  => [self::PAID, self::CLOSED],
        self::PAID     => [self::REFUNDED],
        self::CLOSED   => [],
        self::REFUNDED => [],
    ];

    /**
     * @param int $from
     * @param int $to
     * @return bool
     */
    public static function canTransit(int $from, int $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }
}
