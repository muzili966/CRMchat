<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment;

/**
 * 支付场景
 *
 * 同一个渠道在不同终端上的接入方式不同，收银台按付款人所在终端挑能用的渠道。
 */
final class PaymentScene
{
    /**
     * 扫码：渠道给出二维码内容，付款人用手机扫
     */
    const QRCODE = 'qrcode';

    /**
     * 电脑网页：跳转渠道收银台
     */
    const PC = 'pc';

    /**
     * 手机网页：跳转渠道收银台或唤起 App
     */
    const H5 = 'h5';

    /**
     * 人工收款：给出收款方式，到账由人工确认，不走渠道回调
     */
    const MANUAL = 'manual';

    const ALL = [self::QRCODE, self::PC, self::H5, self::MANUAL];

    /**
     * 手机上优先 H5 直接拉起支付 App，其次扫码（截图或长按识别）
     */
    const PREFER_MOBILE = [self::H5, self::QRCODE, self::MANUAL];

    /**
     * 电脑上优先扫码：页面不跳走，可以原地轮询结果；其次才跳转渠道网页收银台
     */
    const PREFER_DESKTOP = [self::QRCODE, self::PC, self::MANUAL];

    /**
     * 收银台上某个渠道在当前终端该走的场景
     * @param array $scenes 渠道支持的场景
     * @param bool $mobile
     * @return string 无可用场景返回空串
     */
    public static function preferred(array $scenes, bool $mobile): string
    {
        $matched = array_values(array_intersect($mobile ? self::PREFER_MOBILE : self::PREFER_DESKTOP, $scenes));
        return $matched[0] ?? '';
    }

    /**
     * @param string $scene
     * @return bool
     */
    public static function valid(string $scene): bool
    {
        return in_array($scene, self::ALL, true);
    }
}
