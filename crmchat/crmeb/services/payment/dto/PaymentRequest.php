<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment\dto;

use crmeb\services\payment\Money;
use crmeb\services\payment\PaymentException;
use crmeb\services\payment\PaymentScene;

/**
 * 向渠道下单的请求
 *
 * 金额由业务报价算好后传进来，渠道实现只管照单下单、不做任何计价——
 * 否则每接一个渠道就多一处可能算错钱的地方。构造即校验，拿到实例就是合法的。
 */
final class PaymentRequest
{
    /**
     * 支付单号：微信 out_trade_no 上限 32 位，支付宝 64 位，取交集
     */
    const PAY_NO_PATTERN = '/^[A-Za-z0-9_-]{6,32}$/';

    /**
     * 标题字节上限：微信商品描述 127 字节，各渠道取最严的
     */
    const SUBJECT_MAX_BYTES = 127;

    private $payNo;
    private $subject;
    private $amount;
    private $scene;
    private $expireAt;
    private $notifyUrl;
    private $returnUrl;
    private $extra;

    private function __construct()
    {
    }

    /**
     * @param array $data pay_no/subject/amount/scene/expire_at/notify_url/return_url/extra
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $req = new self();
        $req->payNo = self::requirePayNo((string)($data['pay_no'] ?? ''));
        $req->subject = self::requireSubject((string)($data['subject'] ?? ''));
        $req->amount = Money::normalize($data['amount'] ?? '');
        $req->scene = self::requireScene((string)($data['scene'] ?? ''));
        $req->expireAt = (int)($data['expire_at'] ?? 0);
        $req->notifyUrl = (string)($data['notify_url'] ?? '');
        $req->returnUrl = (string)($data['return_url'] ?? '');
        $req->extra = (array)($data['extra'] ?? []);
        if ($req->expireAt <= 0) {
            throw new PaymentException('支付单缺少过期时间');
        }
        //人工收款靠人工确认到账；其余场景没有回调地址，就永远等不到到账通知
        if ($req->scene !== PaymentScene::MANUAL && $req->notifyUrl === '') {
            throw new PaymentException('缺少支付结果回调地址');
        }
        return $req;
    }

    /**
     * @param string $payNo
     * @return string
     */
    private static function requirePayNo(string $payNo): string
    {
        if (!preg_match(self::PAY_NO_PATTERN, $payNo)) {
            throw new PaymentException('支付单号不合法');
        }
        return $payNo;
    }

    /**
     * 标题超长时按字节截断而不是报错：它只用于展示，不值得为此让下单失败
     * @param string $subject
     * @return string
     */
    private static function requireSubject(string $subject): string
    {
        $subject = trim($subject);
        if ($subject === '') {
            throw new PaymentException('支付单缺少标题');
        }
        return mb_strcut($subject, 0, self::SUBJECT_MAX_BYTES, 'UTF-8');
    }

    /**
     * @param string $scene
     * @return string
     */
    private static function requireScene(string $scene): string
    {
        if (!PaymentScene::valid($scene)) {
            throw new PaymentException('不支持的支付场景');
        }
        return $scene;
    }

    public function payNo(): string
    {
        return $this->payNo;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function amount(): string
    {
        return $this->amount;
    }

    public function scene(): string
    {
        return $this->scene;
    }

    public function expireAt(): int
    {
        return $this->expireAt;
    }

    public function notifyUrl(): string
    {
        return $this->notifyUrl;
    }

    public function returnUrl(): string
    {
        return $this->returnUrl;
    }

    public function extra(): array
    {
        return $this->extra;
    }
}
