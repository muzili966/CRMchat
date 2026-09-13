<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment;

/**
 * 金额
 *
 * 全模块统一用「元、两位小数的字符串」表示钱，运算走 bcmath。
 * 不接受浮点：0.1 + 0.2 这种误差放在钱上是不可接受的，调用方必须传字符串。
 */
final class Money
{
    /**
     * 最多十位整数、两位小数
     */
    const PATTERN = '/^\d{1,10}(\.\d{1,2})?$/';

    /**
     * 小数位
     */
    const SCALE = 2;

    /**
     * 规范成两位小数，并要求大于 0
     * @param string|int $amount
     * @return string
     */
    public static function normalize($amount): string
    {
        $text = is_int($amount) ? (string)$amount : (is_string($amount) ? trim($amount) : '');
        if ($text === '' || !preg_match(self::PATTERN, $text)) {
            throw new PaymentException('金额格式不正确');
        }
        $normalized = bcadd($text, '0', self::SCALE);
        if (bccomp($normalized, '0', self::SCALE) <= 0) {
            throw new PaymentException('金额必须大于0');
        }
        return $normalized;
    }

    /**
     * 金额是否相等，「100」与「100.00」视为相等
     * @param string $a
     * @param string $b
     * @return bool
     */
    public static function equals(string $a, string $b): bool
    {
        return bccomp($a, $b, self::SCALE) === 0;
    }

    /**
     * 元转分：微信等渠道以分为单位
     * @param string $amount
     * @return int
     */
    public static function toCents(string $amount): int
    {
        return (int)bcmul($amount, '100', 0);
    }

    /**
     * 分转元：微信回调与查单给的是分
     * @param int $cents
     * @return string
     */
    public static function fromCents(int $cents): string
    {
        return bcdiv((string)$cents, '100', self::SCALE);
    }
}
