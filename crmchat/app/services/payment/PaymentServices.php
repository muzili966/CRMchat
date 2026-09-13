<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\payment;

use app\dao\PaymentOrderDao;
use app\models\PaymentOrder as PaymentOrderModel;
use app\services\payment\delivery\LinkDelivery;
use app\services\payment\delivery\QrcodeDelivery;
use crmeb\basic\BaseServices;
use crmeb\services\payment\CashierLink;
use crmeb\services\payment\dto\PaymentOrder;
use crmeb\services\payment\PaymentException;
use crmeb\services\payment\PaymentManager;
use crmeb\services\payment\PaymentStatus;
use crmeb\services\tenant\TenantContext;
use crmeb\utils\SiteUrl;
use think\facade\Config;
use think\facade\Db;
use think\facade\Env;

/**
 * 支付单：下单、签发收银台链接、投递、查询
 *
 * 到账处理在 PaymentSettleServices，收银台在 CashierServices，
 * 这里只管支付单本身，三者共用同一个组件管理器。
 * Class PaymentServices
 * @package app\services\payment
 */
class PaymentServices extends BaseServices
{
    const PAY_NO_PREFIX = 'PY';

    const PAY_NO_RANDOM_LENGTH = 8;

    /**
     * 未配置有效期时的兜底：续费常要走内部审批，两小时太短
     */
    const DEFAULT_TTL = 259200;

    const REMARK_MAX = 255;

    const CREATOR_NAME_MAX = 50;

    /**
     * 回调地址前缀，与 route/pay.php 一致
     */
    const NOTIFY_PATH = '/api/pay/notify/';

    const TIME_FORMAT = 'Y-m-d H:i:s';

    const STATUS_TEXT = [
        PaymentStatus::PENDING => '待支付',
        PaymentStatus::PAID => '已支付',
        PaymentStatus::CLOSED => '已关闭',
        PaymentStatus::REFUNDED => '已退款',
    ];

    /**
     * @var PaymentManager|null
     */
    protected $manager;

    /**
     * @param PaymentOrderDao $dao
     */
    public function __construct(PaymentOrderDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * @return PaymentManager
     */
    public function manager(): PaymentManager
    {
        if (!$this->manager) {
            $this->manager = new PaymentManager((array)Config::get('payment', []));
        }
        return $this->manager;
    }

    /**
     * 下单：由业务方报价，金额与业务参数在此刻锁定
     * @param array $data tenant_id/biz_type/params/source/creator_id/creator_name/remark
     * @return array
     */
    public function create(array $data): array
    {
        $tenantId = (int)($data['tenant_id'] ?? 0);
        $bizType = (string)($data['biz_type'] ?? '');
        $quote = $this->manager()->payable($bizType)->quote($tenantId, (array)($data['params'] ?? []));
        $now = time();
        return $this->dao->save([
            'tenant_id' => $tenantId,
            'pay_no' => self::buildPayNo($now),
            'biz_type' => $bizType,
            'payload' => json_encode($quote->payload(), JSON_UNESCAPED_UNICODE),
            'subject' => $quote->subject(),
            'amount' => $quote->amount(),
            'status' => PaymentStatus::PENDING,
            'expire_time' => $now + (int)Config::get('payment.pay_ttl', self::DEFAULT_TTL),
            'source' => (string)($data['source'] ?? ''),
            'creator_id' => (int)($data['creator_id'] ?? 0),
            'creator_name' => mb_substr((string)($data['creator_name'] ?? ''), 0, self::CREATOR_NAME_MAX),
            'remark' => mb_substr((string)($data['remark'] ?? ''), 0, self::REMARK_MAX),
            'create_time' => $now,
            'update_time' => $now,
        ])->toArray();
    }

    /**
     * 单号即渠道侧商户订单号：时间前缀便于人工对账时按日期定位，随机段防止被顺序猜到
     * @param int $now
     * @return string
     */
    public static function buildPayNo(int $now): string
    {
        $random = str_pad((string)random_int(0, 10 ** self::PAY_NO_RANDOM_LENGTH - 1), self::PAY_NO_RANDOM_LENGTH, '0', STR_PAD_LEFT);
        return self::PAY_NO_PREFIX . date('YmdHis', $now) . $random;
    }

    /**
     * @param string $payNo
     * @return array|null
     */
    public function findByPayNo(string $payNo): ?array
    {
        return $payNo === '' ? null : $this->dao->findByPayNo($payNo);
    }

    /**
     * @param string $payNo
     * @return array
     */
    public function mustFind(string $payNo): array
    {
        $row = $this->findByPayNo($payNo);
        if (!$row) {
            throw new PaymentException('支付单不存在：' . $payNo);
        }
        return $row;
    }

    /**
     * @param int $id
     * @return array
     */
    public function mustFindById(int $id): array
    {
        $row = $this->dao->findById($id);
        if (!$row) {
            throw new PaymentException('支付单不存在');
        }
        return $row;
    }

    /**
     * 库表行转成交给业务方与投递方的只读对象
     * @param array $row
     * @return PaymentOrder
     */
    public static function toOrder(array $row): PaymentOrder
    {
        return PaymentOrder::fromArray($row + ['expire_at' => (int)($row['expire_time'] ?? 0)]);
    }

    /**
     * @param array $row
     * @return string
     */
    public function cashierUrl(array $row): string
    {
        $signature = CashierLink::sign((string)$row['pay_no'], (int)$row['expire_time'], self::linkKey());
        return CashierLink::url(SiteUrl::service(), (string)$row['pay_no'], $signature);
    }

    /**
     * @param array $row
     * @param string $signature
     * @return bool
     */
    public function verifyLink(array $row, string $signature): bool
    {
        return CashierLink::verify((string)$row['pay_no'], (int)$row['expire_time'], [
            'signature' => $signature,
            'key' => self::linkKey(),
        ]);
    }

    /**
     * 复用应用密钥：它本就是服务端独有的签名密钥，另起一个只会多一处要配、要轮换的东西
     * @return string
     */
    protected static function linkKey(): string
    {
        return trim((string)Env::get('app_key', ''));
    }

    /**
     * @param string $channel
     * @return string
     */
    public function notifyUrl(string $channel): string
    {
        $origin = rtrim(SiteUrl::service(), '/');
        if ($origin === '') {
            throw new PaymentException('未配置对外地址 SERVICE_URL，支付渠道无法回调');
        }
        return $origin . self::NOTIFY_PATH . $channel;
    }

    /**
     * 投递：各方式都落到同一个签名收银台
     * @param array $row
     * @param string $code 投递方式
     * @param array $context 投递方式需要的额外信息
     * @return array
     */
    public function deliver(array $row, string $code, array $context = []): array
    {
        $order = self::toOrder($row);
        if (!$order->isPayable(time())) {
            throw new PaymentException('支付单已支付、已关闭或已过期，不能再发送');
        }
        $context['cashier_url'] = $this->cashierUrl($row);
        return $this->manager()->delivery($code)->deliver($order, $context);
    }

    /**
     * 付款人在收银台选定渠道后记下来，查单与关单要知道去哪个渠道
     * @param array $row
     * @param string $channel
     * @param string $scene
     * @return bool
     */
    public function recordChannel(array $row, string $channel, string $scene): bool
    {
        return $this->dao->updateWhenStatus((int)$row['id'], PaymentStatus::PENDING, [
            'channel' => $channel,
            'scene' => $scene,
            'update_time' => time(),
        ]);
    }

    /**
     * 平台后台创建支付单并返回链接与二维码
     * @param array $data tenant_id/plan_id/months/remark
     * @param array $adminInfo
     * @return array
     */
    public function createForAdmin(array $data, array $adminInfo): array
    {
        $row = $this->create([
            'tenant_id' => (int)($data['tenant_id'] ?? 0),
            'biz_type' => TenantPlanPayable::BIZ_TYPE,
            'params' => ['plan_id' => (int)($data['plan_id'] ?? 0), 'months' => (int)($data['months'] ?? 0)],
            'source' => PaymentOrderModel::SOURCE_ADMIN,
            'creator_id' => (int)($adminInfo['id'] ?? 0),
            'creator_name' => (string)($adminInfo['real_name'] ?? ($adminInfo['account'] ?? '')),
            'remark' => (string)($data['remark'] ?? ''),
        ]);
        return $this->links((int)$row['id']);
    }

    /**
     * 待支付单的链接与二维码，供复制或下载发给租户
     * @param int $id
     * @return array
     */
    public function links(int $id): array
    {
        $row = $this->mustFindById($id);
        return [
            'id' => (int)$row['id'],
            'pay_no' => (string)$row['pay_no'],
            'subject' => (string)$row['subject'],
            'amount' => (string)$row['amount'],
            '_expire_time' => self::formatTime((int)$row['expire_time']),
            'url' => $this->deliver($row, LinkDelivery::CODE)['url'],
            'qrcode' => $this->deliver($row, QrcodeDelivery::CODE)['qrcode'],
        ];
    }

    /**
     * @param array $where status/channel/tenant_id/pay_no/abnormal
     * @return array
     */
    public function getPaymentList(array $where): array
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getPaymentList($where, $page, $limit);
        $names = $this->tenantNames(array_column($list, 'tenant_id'));
        $now = time();
        return [
            'list' => array_map(function (array $row) use ($names, $now) {
                return $this->present($row, $names, $now);
            }, $list),
            'count' => $this->dao->count($where),
        ];
    }

    /**
     * 后台筛选与操作要用的选项
     * @return array
     */
    public function options(): array
    {
        $manager = $this->manager();
        $channels = array_map(function (string $code) use ($manager) {
            $channel = $manager->channel($code);
            return ['code' => $code, 'name' => $channel->name(), 'available' => $channel->available()];
        }, array_keys((array)Config::get('payment.channels', [])));
        $statuses = array_map(function ($value, $label) {
            return ['value' => $value, 'label' => $label];
        }, array_keys(self::STATUS_TEXT), self::STATUS_TEXT);
        return ['channels' => $channels, 'statuses' => $statuses];
    }

    /**
     * 列表行：去掉通知原文这种大字段，补上可读字段
     * @param array $row
     * @param array $tenantNames
     * @param int $now
     * @return array
     */
    protected function present(array $row, array $tenantNames, int $now): array
    {
        $payload = json_decode((string)$row['payload'], true) ?: [];
        $payable = self::toOrder($row)->isPayable($now);
        unset($row['notify_raw'], $row['payload']);
        return $row + [
            'tenant_name' => $tenantNames[$row['tenant_id']] ?? (string)($payload['tenant_name'] ?? ''),
            'plan_name' => (string)($payload['plan_name'] ?? ''),
            'months' => (int)($payload['months'] ?? 0),
            'payable' => $payable,
            '_status' => self::STATUS_TEXT[(int)$row['status']] ?? '',
            '_create_time' => self::formatTime((int)$row['create_time']),
            '_expire_time' => self::formatTime((int)$row['expire_time']),
            '_paid_time' => self::formatTime((int)$row['paid_time']),
        ];
    }

    /**
     * @param array $ids
     * @return array
     */
    protected function tenantNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        return TenantContext::withoutTenant(function () use ($ids) {
            return Db::name('tenant')->whereIn('id', $ids)->column('name', 'id');
        });
    }

    /**
     * @param int $time
     * @return string
     */
    public static function formatTime(int $time): string
    {
        return $time > 0 ? date(self::TIME_FORMAT, $time) : '';
    }
}
