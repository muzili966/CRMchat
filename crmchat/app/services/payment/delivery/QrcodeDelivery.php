<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\payment\delivery;

use crmeb\services\payment\contract\DeliveryInterface;
use crmeb\services\payment\dto\PaymentOrder;
use crmeb\services\payment\PaymentException;
use crmeb\utils\SiteUrl;

/**
 * 支付二维码：扫开的是收银台，不是某个渠道的收款码
 *
 * 渠道收款码只能用那一个渠道付，而且付完没人知道是哪张单；
 * 收银台二维码付款人自己挑方式，到账自动对上单子。
 * Class QrcodeDelivery
 * @package app\services\payment\delivery
 */
class QrcodeDelivery implements DeliveryInterface
{
    const CODE = 'qrcode';

    /**
     * 相对 public 目录
     */
    const DIR = 'uploads/payment/qrcode/';

    /**
     * 模块尺寸与留白：6 像素一格在聊天窗口与打印件上都扫得出
     */
    const MODULE_SIZE = 6;

    const MARGIN = 2;

    /**
     * 文件名长度：取自收银台地址的摘要，地址里带签名，文件名因而不可猜
     */
    const NAME_LENGTH = 32;

    /**
     * 不用 dh2y 的封装：它每次实例化都 require 一遍 qrlib，常驻进程里第二次调用就重复声明类
     */
    const QRLIB = 'vendor/dh2y/think-qrcode/src/phpqrcode/qrlib.php';

    /**
     * @return string
     */
    public function code(): string
    {
        return self::CODE;
    }

    /**
     * @param PaymentOrder $order
     * @param array $context cashier_url
     * @return array url/qrcode
     */
    public function deliver(PaymentOrder $order, array $context): array
    {
        $url = LinkDelivery::requireUrl($context);
        $relative = self::DIR . substr(hash('sha256', $url), 0, self::NAME_LENGTH) . '.png';
        $file = public_path() . $relative;
        if (!is_file($file)) {
            $this->render($url, $file);
        }
        return ['url' => $url, 'qrcode' => rtrim(SiteUrl::service(), '/') . '/' . $relative];
    }

    /**
     * @param string $text
     * @param string $file
     * @return void
     */
    protected function render(string $text, string $file): void
    {
        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new PaymentException('无法创建二维码目录');
        }
        if (!class_exists('QRcode', false)) {
            require_once root_path() . self::QRLIB;
        }
        \QRcode::png($text, $file, QR_ECLEVEL_M, self::MODULE_SIZE, self::MARGIN);
        if (!is_file($file)) {
            throw new PaymentException('二维码生成失败');
        }
    }
}
