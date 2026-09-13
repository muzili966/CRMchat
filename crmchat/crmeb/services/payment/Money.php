<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment;

/**
 * 金额
 *
 * 全模块统一用「元、两位小数的字符串」表示钱，运算换算成整数分进行。
 * 不接受浮点：0.1 + 0.2 这种误差放在钱上是不可接受的，调用方必须传字符串。
 * 不用 bcmath：金额只有两位小数，整数分足够精确；CI 单测容器是裸 php:7.4-cli，
 * 没有 bcmath，依赖它会让本地通过、流水线恒挂。
 */
final class Money
{
    /**
     * 最多十位整数、两位小数：换算成分后远在 int64 范围内
     */
    const PATTERN = '/^\d{1,10}(\.\d{1,2})?$/';

    /**
     * 小数位
     */
    const SCALE = 2;

    const CENTS_PER_YUAN = 100;

    /**
     * 规范成两位小数，并要求大于 0
     * @param string|int $amount
     * @return string
     */
    public static function normalize($amount): string
    {
        $text = is_int($amount) ? (string)$amount : (is_string($amount) ? trim($amount) : '');
        $cents = self::parseCents($text);
        if ($cents <= 0) {
            throw new PaymentException('金额必须大于0');
        }
        return self::fromCents($cents);
    }

    /**
     * 金额是否相等，「100」与「100.00」视为相等
     * @param string $a
     * @param string $b
     * @return bool
     */
    public static function equals(string $a, string $b): bool
    {
        return self::compare($a, $b) === 0;
    }

    /**
     * @param string $a
     * @param string $b
     * @return int 小于、等于、大于分别为 -1、0、1
     */
    public static function compare(string $a, string $b): int
    {
        return self::toCents($a) <=> self::toCents($b);
    }

    /**
     * 单价乘以数量，如套餐月价 × 月数
     * @param string $amount
     * @param int $times
     * @return string
     */
    public static function multiply(string $amount, int $times): string
    {
        if ($times < 0) {
            throw new PaymentException('数量不能为负');
        }
        return self::fromCents(self::toCents($amount) * $times);
    }

    /**
     * 元转分：微信等渠道以分为单位
     * @param string $amount
     * @return int
     */
    public static function toCents(string $amount): int
    {
        return self::parseCents(trim($amount));
    }

    /**
     * 分转元：微信回调与查单给的是分
     * @param int $cents
     * @return string
     */
    public static function fromCents(int $cents): string
    {
        $abs = abs($cents);
        return sprintf('%s%d.%02d', $cents < 0 ? '-' : '', intdiv($abs, self::CENTS_PER_YUAN), $abs % self::CENTS_PER_YUAN);
    }

    /**
     * @param string $text
     * @return int
     */
    private static function parseCents(string $text): int
    {
        if ($text === '' || !preg_match(self::PATTERN, $text)) {
            throw new PaymentException('金额格式不正确');
        }
        [$yuan, $fraction] = array_pad(explode('.', $text, 2), 2, '');
        return (int)$yuan * self::CENTS_PER_YUAN + (int)str_pad($fraction, self::SCALE, '0');
    }
}
