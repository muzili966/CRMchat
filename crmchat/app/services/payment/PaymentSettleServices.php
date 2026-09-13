<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\payment;

use app\dao\PaymentOrderDao;
use crmeb\basic\BaseServices;
use crmeb\services\CacheService;
use crmeb\services\payment\channel\ManualChannel;
use crmeb\services\payment\dto\NotifyPayload;
use crmeb\services\payment\dto\PaymentNotice;
use crmeb\services\payment\PaymentException;
use crmeb\services\payment\PaymentSettlement;
use crmeb\services\payment\PaymentStatus;
use think\facade\Config;
use think\facade\Log;

/**
 * 到账处理
 *
 * 三条入口：渠道回调、主动查单（收银台轮询与过期关单时）、平台人工确认。
 * 三条入口最终都走 settle，决策统一交给 PaymentSettlement。
 * Class PaymentSettleServices
 * @package app\services\payment
 */
class PaymentSettleServices extends BaseServices
{
    /**
     * 收银台每几秒轮询一次，查单打到渠道的频率要压住，免得被限流
     */
    const SYNC_INTERVAL = 10;

    const SYNC_CACHE_PREFIX = 'payment_sync:';

    /**
     * 每轮过期关单的处理量：每张单可能要调一次渠道接口，一次处理太多会拖住定时任务
     */
    const EXPIRE_BATCH = 50;

    const ABNORMAL_REASON_MAX = 255;

    /**
     * 人工确认未填流水号时的交易号前缀
     */
    const MANUAL_TRADE_PREFIX = 'MANUAL-';

    /**
     * @var PaymentServices
     */
    protected $payment;

    /**
     * @param PaymentOrderDao $dao
     * @param PaymentServices $payment
     */
    public function __construct(PaymentOrderDao $dao, PaymentServices $payment)
    {
        $this->dao = $dao;
        $this->payment = $payment;
    }

    /**
     * @param string $code
     * @return bool
     */
    public function hasChannel(string $code): bool
    {
        return array_key_exists($code, (array)Config::get('payment.channels', []));
    }

    /**
     * 渠道回调
     * @param string $code
     * @param NotifyPayload $payload
     * @return array ok/body 应答是否成功与应答正文
     */
    public function handleNotify(string $code, NotifyPayload $payload): array
    {
        $channel = $this->payment->manager()->channel($code);
        try {
            $notice = $channel->parseNotify($payload);
            if ($notice !== null) {
                $this->settle($code, $notice);
            }
            return ['ok' => true, 'body' => $channel->notifyAck(true)];
        } catch (\Throwable $e) {
            //验签失败可能是伪造请求，落库失败则需要渠道稍后重发，两种都应答失败
            Log::error("支付回调处理失败[{$code}]：" . $e->getMessage());
            return ['ok' => false, 'body' => $channel->notifyAck(false)];
        }
    }

    /**
     * 处理一笔已确认的到账
     * @param string $channel
     * @param PaymentNotice $notice
     * @return string PaymentSettlement 的动作
     */
    public function settle(string $channel, PaymentNotice $notice): string
    {
        $row = $this->payment->mustFind($notice->payNo());
        $decision = PaymentSettlement::decide(
            (int)$row['status'],
            $notice->matchesAmount((string)$row['amount']),
            self::sameTrade($row, $channel, $notice)
        );
        if ($decision['action'] === PaymentSettlement::DUPLICATE) {
            return PaymentSettlement::DUPLICATE;
        }
        //金额不符也先翻成已支付：钱确实到了，状态要如实反映，是否开通交给平台判断
        if ((int)$row['status'] === PaymentStatus::PENDING && !$this->markPaid($row, $channel, $notice)) {
            //并发下另一条通知抢先完成了翻转，由那条负责后续
            return PaymentSettlement::DUPLICATE;
        }
        if ($decision['action'] === PaymentSettlement::ABNORMAL) {
            $this->markAbnormal((int)$row['id'], $decision['reason'], $notice);
            return PaymentSettlement::ABNORMAL;
        }
        return $this->fulfill($this->payment->mustFind($notice->payNo()), $notice);
    }

    /**
     * 支付单尚未记录交易时视为同一笔
     * @param array $row
     * @param string $channel
     * @param PaymentNotice $notice
     * @return bool
     */
    public static function sameTrade(array $row, string $channel, PaymentNotice $notice): bool
    {
        if ((string)$row['trade_no'] === '') {
            return true;
        }
        return (string)$row['channel'] === $channel && (string)$row['trade_no'] === $notice->tradeNo();
    }

    /**
     * @param array $row
     * @param string $channel
     * @param PaymentNotice $notice
     * @return bool
     */
    protected function markPaid(array $row, string $channel, PaymentNotice $notice): bool
    {
        return $this->dao->updateWhenStatus((int)$row['id'], PaymentStatus::PENDING, [
            'status' => PaymentStatus::PAID,
            'channel' => $channel,
            'trade_no' => $notice->tradeNo(),
            'paid_amount' => $notice->amount(),
            'paid_time' => $notice->paidAt(),
            'notify_raw' => json_encode($notice->raw(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'update_time' => time(),
        ]);
    }

    /**
     * 开通失败不回滚支付状态：钱已经收了，标异常后由平台修好原因再补开通
     * @param array $row
     * @param PaymentNotice $notice
     * @return string
     */
    protected function fulfill(array $row, PaymentNotice $notice): string
    {
        try {
            $this->payment->manager()->payable((string)$row['biz_type'])->fulfill(PaymentServices::toOrder($row), $notice);
            $this->dao->markFulfilled((int)$row['id'], time());
            return PaymentSettlement::FULFILL;
        } catch (\Throwable $e) {
            $this->markAbnormal((int)$row['id'], '开通失败：' . $e->getMessage(), null);
            return PaymentSettlement::ABNORMAL;
        }
    }

    /**
     * @param int $id
     * @param string $reason
     * @param PaymentNotice|null $notice 附上交易号与金额，平台退款时要用
     * @return void
     */
    protected function markAbnormal(int $id, string $reason, ?PaymentNotice $notice): void
    {
        if ($notice) {
            $reason .= sprintf('（%s 交易号 %s，金额 %s）', $notice->payNo(), $notice->tradeNo(), $notice->amount());
        }
        $this->dao->update($id, [
            'abnormal' => 1,
            'abnormal_reason' => mb_substr($reason, 0, self::ABNORMAL_REASON_MAX),
            'update_time' => time(),
        ]);
        Log::error("支付单异常[{$id}]：{$reason}");
    }

    /**
     * 平台人工确认到账：人工收款没有回调，靠核对银行流水后在后台确认
     * @param int $id
     * @param array $data trade_no/paid_amount/remark/operator
     * @return string PaymentSettlement 的动作
     */
    public function confirmManual(int $id, array $data): string
    {
        $row = $this->payment->mustFindById($id);
        if ((int)$row['status'] !== PaymentStatus::PENDING) {
            throw new PaymentException('只有待支付的单可以确认到账');
        }
        $tradeNo = trim((string)($data['trade_no'] ?? ''));
        $notice = PaymentNotice::fromArray([
            'pay_no' => $row['pay_no'],
            'trade_no' => $tradeNo !== '' ? $tradeNo : self::MANUAL_TRADE_PREFIX . $row['pay_no'],
            'amount' => (string)($data['paid_amount'] ?? ''),
            'paid_at' => time(),
            'raw' => ['operator' => (string)($data['operator'] ?? ''), 'remark' => (string)($data['remark'] ?? '')],
        ]);
        return $this->settle(ManualChannel::CODE, $notice);
    }

    /**
     * 主动查单：回调丢了（服务重启、网络抖动）时不能让单子一直停在待支付
     * @param array $row
     * @param bool $throttle 收银台轮询要限频，平台手动同步与关单前核对不限
     * @return bool 是否查到已支付并完成了处理
     */
    public function sync(array $row, bool $throttle = true): bool
    {
        $code = (string)$row['channel'];
        if ((int)$row['status'] !== PaymentStatus::PENDING || $code === '' || $code === ManualChannel::CODE) {
            return false;
        }
        $key = self::SYNC_CACHE_PREFIX . $row['pay_no'];
        if ($throttle && CacheService::has($key)) {
            return false;
        }
        CacheService::set($key, 1, self::SYNC_INTERVAL);
        $notice = $this->payment->manager()->channel($code)->query((string)$row['pay_no']);
        if ($notice === null) {
            return false;
        }
        $this->settle($code, $notice);
        return true;
    }

    /**
     * 平台后台手动同步
     * @param int $id
     * @return bool
     */
    public function syncById(int $id): bool
    {
        return $this->sync($this->payment->mustFindById($id), false);
    }

    /**
     * 已支付未开通的单补开通
     * @param int $id
     * @return void
     */
    public function refulfill(int $id): void
    {
        $row = $this->payment->mustFindById($id);
        if ((int)$row['status'] !== PaymentStatus::PAID || (int)$row['fulfilled_time'] > 0) {
            throw new PaymentException('只有已支付且未开通的支付单可以补开通');
        }
        $notice = PaymentNotice::fromArray([
            'pay_no' => $row['pay_no'],
            'trade_no' => $row['trade_no'],
            'amount' => (string)$row['paid_amount'],
            'paid_at' => (int)$row['paid_time'],
        ]);
        //平台手动触发，失败原因直接抛给操作人看
        $this->payment->manager()->payable((string)$row['biz_type'])->fulfill(PaymentServices::toOrder($row), $notice);
        $this->dao->markFulfilled($id, time());
    }

    /**
     * 平台后台关闭待支付单
     * @param int $id
     * @return void
     */
    public function close(int $id): void
    {
        $row = $this->payment->mustFindById($id);
        if ((int)$row['status'] !== PaymentStatus::PENDING) {
            throw new PaymentException('只有待支付的单可以关闭');
        }
        //先核对渠道：付款人可能刚付完而回调还在路上，此时关单会让这笔钱变成异常
        if ($this->sync($row, false)) {
            throw new PaymentException('该单在渠道侧已支付，已同步为已支付，未关闭');
        }
        $this->closeChannel($row);
        $this->dao->updateWhenStatus($id, PaymentStatus::PENDING, ['status' => PaymentStatus::CLOSED, 'update_time' => time()]);
    }

    /**
     * 过期关单，由定时任务调用
     * @param int $now
     * @return int 本轮关闭的张数
     */
    public function closeExpired(int $now): int
    {
        return array_sum(array_map(function (array $row) {
            return (int)$this->expireOne($row);
        }, $this->dao->expiredPending($now, self::EXPIRE_BATCH)));
    }

    /**
     * @param array $row
     * @return bool
     */
    protected function expireOne(array $row): bool
    {
        try {
            if ($this->sync($row, false)) {
                return false;
            }
        } catch (\Throwable $e) {
            //查不到渠道状态时不关：宁可下一轮再试，也不把可能已付的单关掉
            Log::error("过期支付单查单失败[{$row['pay_no']}]：" . $e->getMessage());
            return false;
        }
        try {
            $this->closeChannel($row);
        } catch (\Throwable $e) {
            //下单时已把同一个过期时间交给渠道，渠道侧此时也已不可支付，本地照常关单
            Log::warning("过期支付单渠道关单失败[{$row['pay_no']}]：" . $e->getMessage());
        }
        return $this->dao->updateWhenStatus((int)$row['id'], PaymentStatus::PENDING, [
            'status' => PaymentStatus::CLOSED,
            'update_time' => time(),
        ]);
    }

    /**
     * @param array $row
     * @return void
     */
    protected function closeChannel(array $row): void
    {
        $code = (string)$row['channel'];
        if ($code === '' || $code === ManualChannel::CODE) {
            return;
        }
        $this->payment->manager()->channel($code)->close((string)$row['pay_no']);
    }
}
