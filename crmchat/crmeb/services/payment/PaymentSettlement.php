<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment;

/**
 * 到账通知的处理决策
 *
 * 纯函数：只看支付单当前状态、金额是否一致、是不是同一笔交易，决定这笔通知该怎么处理。
 * 回调、主动查单、人工确认三条入口共用，决策不散落在各处。
 */
final class PaymentSettlement
{
    /**
     * 待支付且金额一致：翻成已支付并开通
     */
    const FULFILL = 'fulfill';

    /**
     * 同一笔交易的重复通知（渠道重发、回调与查单撞车）：应答成功，不再开通
     */
    const DUPLICATE = 'duplicate';

    /**
     * 钱进来了但不能正常开通：金额对不上、单子已关闭或已退款、同一单被付了两次。
     * 应答成功让渠道别再重发，标成异常交平台处理（补开通或原路退回）
     */
    const ABNORMAL = 'abnormal';

    /**
     * @param int $status 支付单当前状态
     * @param bool $amountMatches 到账金额是否与下单金额一致
     * @param bool $sameTrade 是否与已记录的交易是同一笔；支付单尚未记录交易时为 true
     * @return array action/reason
     */
    public static function decide(int $status, bool $amountMatches, bool $sameTrade = true): array
    {
        //先判重复：已处理过的同一笔交易，金额再怎么比都不该产生新动作
        if ($status === PaymentStatus::PAID && $sameTrade) {
            return ['action' => self::DUPLICATE, 'reason' => ''];
        }
        if ($status === PaymentStatus::PAID) {
            //付款人在收银台先后用两个渠道各付了一次，第二笔要退
            return ['action' => self::ABNORMAL, 'reason' => '支付单已支付，又收到另一笔到账'];
        }
        if (!$amountMatches) {
            return ['action' => self::ABNORMAL, 'reason' => '到账金额与支付单金额不一致'];
        }
        if ($status === PaymentStatus::PENDING) {
            return ['action' => self::FULFILL, 'reason' => ''];
        }
        $label = $status === PaymentStatus::REFUNDED ? '已退款' : '已关闭';
        return ['action' => self::ABNORMAL, 'reason' => "支付单{$label}后仍收到到账"];
    }
}
