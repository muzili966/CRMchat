<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment\channel;

use crmeb\services\payment\dto\NotifyPayload;
use crmeb\services\payment\dto\PaymentNotice;
use crmeb\services\payment\dto\PaymentRequest;
use crmeb\services\payment\dto\PaymentResult;
use crmeb\services\payment\PaymentException;
use crmeb\services\payment\PaymentScene;
use think\facade\Env;

/**
 * 人工收款
 *
 * 就是现在「客服发付款码、人工确认到账」那套做法的线上化：展示收款码与收款账户，
 * 付款人按单号备注转账，平台核对到账后人工确认开通。不需要任何商户凭据。
 * 没有渠道回调，也没有可查的渠道单，到账一律以人工确认为准。
 */
class ManualChannel extends AbstractChannel
{
    const CODE = 'manual';

    public function code(): string
    {
        return self::CODE;
    }

    public function name(): string
    {
        return '人工收款';
    }

    public function scenes(): array
    {
        return [PaymentScene::MANUAL];
    }

    public function available(): bool
    {
        return $this->qrcodes() !== [] || $this->account() !== '';
    }

    /**
     * @param PaymentRequest $request
     * @return PaymentResult
     */
    public function create(PaymentRequest $request): PaymentResult
    {
        if (!$this->available()) {
            throw new PaymentException('人工收款未配置收款码或收款账户');
        }
        //备注单号是人工对账唯一的抓手：没有它，一笔 3000 元的到账对不上是谁付的
        $instruction = sprintf('请支付 %s 元，付款备注填写单号 %s，到账后由平台确认开通。', $request->amount(), $request->payNo());
        return PaymentResult::manual($instruction, [
            'pay_no' => $request->payNo(),
            'amount' => $request->amount(),
            'qrcodes' => $this->qrcodes(),
            'account' => $this->account(),
        ]);
    }

    /**
     * 渠道侧没有单子可查
     * @param string $payNo
     * @return PaymentNotice|null
     */
    public function query(string $payNo): ?PaymentNotice
    {
        return null;
    }

    /**
     * 没有任何渠道会给人工收款发回调，收到就是伪造的
     * @param NotifyPayload $payload
     * @return PaymentNotice|null
     */
    public function parseNotify(NotifyPayload $payload): ?PaymentNotice
    {
        throw new PaymentException('人工收款不接受回调');
    }

    public function notifyAck(bool $success): string
    {
        return '';
    }

    /**
     * 渠道侧没有单子可关
     * @param string $payNo
     * @return void
     */
    public function close(string $payNo): void
    {
    }

    /**
     * 收款码图片地址，配置里可以是数组或逗号分隔的字符串
     * @return string[]
     */
    private function qrcodes(): array
    {
        $raw = $this->config['qrcodes'] ?? [];
        $list = is_array($raw) ? $raw : explode(',', (string)$raw);
        return array_values(array_filter(array_map(function ($item) {
            return trim((string)$item);
        }, $list), 'strlen'));
    }

    /**
     * 收款账户说明，如户名、账号、开户行
     * @return string
     */
    private function account(): string
    {
        return trim((string)($this->config['account'] ?? ''));
    }

    protected static function envConfig(): array
    {
        return [
            'account' => Env::get('PAY_MANUAL_ACCOUNT', ''),
            'qrcodes' => Env::get('PAY_MANUAL_QRCODES', ''),
        ];
    }
}
