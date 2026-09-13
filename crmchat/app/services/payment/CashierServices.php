<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\payment;

use crmeb\services\payment\dto\PaymentRequest;
use crmeb\services\payment\PaymentException;
use crmeb\services\payment\PaymentScene;
use crmeb\services\payment\PaymentStatus;
use think\facade\Config;
use think\facade\Log;

/**
 * 收银台
 *
 * 面向不登录的付款人，唯一凭证是链接上的签名；金额、标题一律读库，不从链接取。
 * Class CashierServices
 * @package app\services\payment
 */
class CashierServices
{
    /**
     * @var PaymentServices
     */
    protected $payment;

    /**
     * @var PaymentSettleServices
     */
    protected $settle;

    /**
     * @param PaymentServices $payment
     * @param PaymentSettleServices $settle
     */
    public function __construct(PaymentServices $payment, PaymentSettleServices $settle)
    {
        $this->payment = $payment;
        $this->settle = $settle;
    }

    /**
     * 支付单摘要与可选的支付方式
     * @param string $payNo
     * @param string $signature
     * @param bool $mobile
     * @return array
     */
    public function info(string $payNo, string $signature, bool $mobile): array
    {
        $row = $this->verified($payNo, $signature);
        $payable = PaymentServices::toOrder($row)->isPayable(time());
        return $this->summary($row) + ['channels' => $payable ? array_values($this->channels($mobile)) : []];
    }

    /**
     * 选定渠道下单
     * @param array $credential pay_no/signature/channel
     * @param array $client mobile/client_ip
     * @return array PaymentResult::toArray
     */
    public function pay(array $credential, array $client): array
    {
        $row = $this->verified((string)($credential['pay_no'] ?? ''), (string)($credential['signature'] ?? ''));
        if (!PaymentServices::toOrder($row)->isPayable(time())) {
            throw new PaymentException('支付单已支付、已关闭或已过期');
        }
        $code = (string)($credential['channel'] ?? '');
        $scene = $this->channels((bool)($client['mobile'] ?? false))[$code]['scene'] ?? '';
        if ($scene === '') {
            throw new PaymentException('该支付方式当前不可用');
        }
        $this->assertPreviousUnpaid($row, $code);
        $request = $this->request($row, ['channel' => $code, 'scene' => $scene, 'client_ip' => (string)($client['client_ip'] ?? '')]);
        $result = $this->payment->manager()->channel($code)->create($request);
        $this->payment->recordChannel($row, $code, $scene);
        return $result->toArray();
    }

    /**
     * 轮询支付状态，顺带限频查单兜住丢失的回调
     * @param string $payNo
     * @param string $signature
     * @return array
     */
    public function status(string $payNo, string $signature): array
    {
        $row = $this->verified($payNo, $signature);
        try {
            if ($this->settle->sync($row)) {
                $row = $this->payment->mustFind($payNo);
            }
        } catch (\Throwable $e) {
            //查单失败不影响页面展示当前状态，回调仍可能随后到达
            Log::warning("收银台查单失败[{$payNo}]：" . $e->getMessage());
        }
        return $this->summary($row);
    }

    /**
     * 单号不存在与签名不对给同一句话，不让人拿单号试探哪些单真实存在
     * @param string $payNo
     * @param string $signature
     * @return array
     */
    protected function verified(string $payNo, string $signature): array
    {
        $row = $this->payment->findByPayNo($payNo);
        if (!$row || !$this->payment->verifyLink($row, $signature)) {
            throw new PaymentException('支付链接无效');
        }
        return $row;
    }

    /**
     * 换渠道前先确认上一个渠道没付过，避免同一张单在两个渠道各付一次
     * @param array $row
     * @param string $code
     * @return void
     */
    protected function assertPreviousUnpaid(array $row, string $code): void
    {
        $previous = (string)$row['channel'];
        if ($previous === '' || $previous === $code) {
            return;
        }
        if ($this->settle->sync($row, false)) {
            throw new PaymentException('该单已经支付成功，请勿重复支付');
        }
    }

    /**
     * @param array $row
     * @param array $options channel/scene/client_ip
     * @return PaymentRequest
     */
    protected function request(array $row, array $options): PaymentRequest
    {
        return PaymentRequest::fromArray([
            'pay_no' => $row['pay_no'],
            'subject' => $row['subject'],
            'amount' => (string)$row['amount'],
            'scene' => $options['scene'],
            'expire_at' => (int)$row['expire_time'],
            'notify_url' => $this->payment->notifyUrl($options['channel']),
            'return_url' => $this->payment->cashierUrl($row),
            'extra' => ['client_ip' => $options['client_ip']],
        ]);
    }

    /**
     * 当前终端可用的支付方式，按注册顺序
     * @param bool $mobile
     * @return array 渠道编码 => code/name/scene
     */
    protected function channels(bool $mobile): array
    {
        $manager = $this->payment->manager();
        $list = [];
        foreach (array_keys((array)Config::get('payment.channels', [])) as $code) {
            $channel = $manager->channel($code);
            $scene = $channel->available() ? PaymentScene::preferred($channel->scenes(), $mobile) : '';
            if ($scene !== '') {
                $list[$code] = ['code' => $code, 'name' => $channel->name(), 'scene' => $scene];
            }
        }
        return $list;
    }

    /**
     * 只给付款人需要看的字段，渠道交易号、异常原因等内部信息不外露
     * @param array $row
     * @return array
     */
    protected function summary(array $row): array
    {
        $status = (int)$row['status'];
        $payload = json_decode((string)$row['payload'], true) ?: [];
        return [
            'pay_no' => (string)$row['pay_no'],
            'subject' => (string)$row['subject'],
            'payer' => (string)($payload['tenant_name'] ?? ''),
            'amount' => (string)$row['amount'],
            'status' => $status,
            'status_text' => PaymentServices::STATUS_TEXT[$status] ?? '',
            'expired' => $status === PaymentStatus::PENDING && (int)$row['expire_time'] <= time(),
            'expire_time' => (int)$row['expire_time'],
            '_expire_time' => PaymentServices::formatTime((int)$row['expire_time']),
            '_paid_time' => PaymentServices::formatTime((int)$row['paid_time']),
            'fulfilled' => (int)$row['fulfilled_time'] > 0,
        ];
    }
}
