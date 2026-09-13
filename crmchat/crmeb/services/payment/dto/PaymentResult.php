<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment\dto;

use crmeb\services\payment\PaymentException;

/**
 * 渠道下单结果
 *
 * 收银台只按 type 渲染，不认识具体渠道：二维码就画码，跳转就跳，表单就提交，
 * 人工收款就展示说明。新渠道只要落到这四种之一，前端一行不用改。
 */
final class PaymentResult
{
    /**
     * content 为二维码文本，由收银台画成码
     */
    const TYPE_QRCODE = 'qrcode';

    /**
     * content 为跳转地址
     */
    const TYPE_REDIRECT = 'redirect';

    /**
     * content 为自动提交的表单 html，部分网页支付只给这种形式
     */
    const TYPE_FORM = 'form';

    /**
     * content 为收款说明，data 可带收款码图片等
     */
    const TYPE_MANUAL = 'manual';

    const TYPES = [self::TYPE_QRCODE, self::TYPE_REDIRECT, self::TYPE_FORM, self::TYPE_MANUAL];

    private $type;
    private $content;
    private $data;

    /**
     * @param string $type
     * @param string $content
     * @param array $data
     */
    private function __construct(string $type, string $content, array $data)
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new PaymentException('不支持的下单结果类型');
        }
        if ($content === '') {
            throw new PaymentException('渠道未返回可用的支付内容');
        }
        $this->type = $type;
        $this->content = $content;
        $this->data = $data;
    }

    public static function qrcode(string $content, array $data = []): self
    {
        return new self(self::TYPE_QRCODE, $content, $data);
    }

    public static function redirect(string $url, array $data = []): self
    {
        return new self(self::TYPE_REDIRECT, $url, $data);
    }

    public static function form(string $html, array $data = []): self
    {
        return new self(self::TYPE_FORM, $html, $data);
    }

    public static function manual(string $instruction, array $data = []): self
    {
        return new self(self::TYPE_MANUAL, $instruction, $data);
    }

    public function type(): string
    {
        return $this->type;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function data(): array
    {
        return $this->data;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'content' => $this->content, 'data' => $this->data];
    }
}
