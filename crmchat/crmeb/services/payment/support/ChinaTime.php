<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment\support;

use crmeb\services\payment\PaymentException;

/**
 * 渠道时间
 *
 * 支付宝的时间字符串不带时区、默认北京时间，按进程默认时区解析就会差 8 小时——
 * 这个项目早先就踩过 PHP 进程是 UTC、数据库是 +08 的坑。所以一律显式指定时区，
 * 不依赖 date_default_timezone_set。
 */
final class ChinaTime
{
    const ZONE = 'Asia/Shanghai';

    /**
     * @param int $timestamp
     * @param string $format 如 Y-m-d H:i:s 或 DATE_RFC3339
     * @return string
     */
    public static function format(int $timestamp, string $format): string
    {
        return (new \DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new \DateTimeZone(self::ZONE))
            ->format($format);
    }

    /**
     * 解析渠道时间：带时区的按原时区，不带的按北京时间
     * @param string $text
     * @return int
     */
    public static function parse(string $text): int
    {
        //空串会被解析成「当前时间」，悄悄把缺失的支付时间补成现在，必须拦住
        if (trim($text) === '') {
            throw new PaymentException('缺少时间');
        }
        try {
            return (new \DateTimeImmutable($text, new \DateTimeZone(self::ZONE)))->getTimestamp();
        } catch (\Exception $e) {
            throw new PaymentException('无法识别的时间：' . $text, 0, $e);
        }
    }
}
