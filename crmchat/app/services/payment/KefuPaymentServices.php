<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\payment;

use app\dao\TenantDao;
use app\models\PaymentOrder;
use app\models\Tenant;
use app\models\TenantPlan;
use app\services\chat\ChatUserServices;
use app\services\payment\delivery\ChatCardDelivery;
use app\services\platform\PlatformSupportServices;
use crmeb\services\payment\PaymentException;
use crmeb\services\tenant\TenantContext;
use think\facade\Db;

/**
 * 客服在会话里向租户发续费卡片
 *
 * 只在平台自营租户的客服工作台可用，对方必须是经租户后台悬浮入口进来的访客：
 * 那条入口带着租户身份，访客 uid 能反推出是哪个租户，卡片才知道向谁收钱。
 * Class KefuPaymentServices
 * @package app\services\payment
 */
class KefuPaymentServices
{
    /**
     * @var PaymentServices
     */
    protected $payment;

    /**
     * @param PaymentServices $payment
     */
    public function __construct(PaymentServices $payment)
    {
        $this->payment = $payment;
    }

    /**
     * 当前访客对应的租户与可选套餐；不是租户访客时 tenant_id 为 0，前端据此隐藏入口
     * @param int $visitorUserId
     * @param string $appid
     * @return array
     */
    public function target(int $visitorUserId, string $appid): array
    {
        $tenantId = $this->tenantIdOfVisitor($visitorUserId, $appid);
        $tenant = $tenantId ? app()->make(TenantDao::class)->get($tenantId) : null;
        if (!$tenant || $tenant['is_delete']) {
            return ['tenant_id' => 0, 'plans' => []];
        }
        return [
            'tenant_id' => $tenantId,
            'tenant_name' => (string)$tenant['name'],
            'plan_id' => (int)$tenant['plan_id'],
            '_expire_time' => (int)$tenant['expire_time'] > 0 ? date('Y-m-d', (int)$tenant['expire_time']) : '永久',
            'plans' => $this->plans(),
        ];
    }

    /**
     * 下单并以聊天卡片发给访客
     * @param array $data user_id/plan_id/months/remark
     * @param array $kefuInfo 取自登录态
     * @return array record/pay_no/url
     */
    public function sendCard(array $data, array $kefuInfo): array
    {
        $appid = (string)($kefuInfo['appid'] ?? '');
        $visitorUserId = (int)($data['user_id'] ?? 0);
        $tenantId = $this->tenantIdOfVisitor($visitorUserId, $appid);
        if (!$tenantId) {
            throw new PaymentException('该访客不是经租户后台进入的平台租户，不能发送续费卡片');
        }
        $kefuUserId = (int)($kefuInfo['user_id'] ?? 0);
        $row = $this->payment->create([
            'tenant_id' => $tenantId,
            'biz_type' => TenantPlanPayable::BIZ_TYPE,
            'params' => ['plan_id' => (int)($data['plan_id'] ?? 0), 'months' => (int)($data['months'] ?? 0)],
            'source' => PaymentOrder::SOURCE_KEFU,
            'creator_id' => $kefuUserId,
            'creator_name' => $this->nickname($kefuUserId),
            'remark' => (string)($data['remark'] ?? ''),
        ]);
        $record = $this->payment->deliver($row, ChatCardDelivery::CODE, [
            'appid' => $appid,
            'kefu_user_id' => $kefuUserId,
            'visitor_user_id' => $visitorUserId,
        ]);
        return ['record' => $record, 'pay_no' => (string)$row['pay_no'], 'url' => $this->payment->cashierUrl($row)];
    }

    /**
     * 访客 uid 在接入协议里未签名时可被伪造：伪造者最多是替某个租户付钱，
     * 看到的也只是一张续费卡片，所以这里不额外校验签名，由客服结合对话判断
     * @param int $visitorUserId
     * @param string $appid
     * @return int
     */
    protected function tenantIdOfVisitor(int $visitorUserId, string $appid): int
    {
        //只有平台自营租户在接待租户，其他租户的客服没有向谁收平台费用的立场
        if ((int)TenantContext::id() !== Tenant::DEFAULT_TENANT_ID || $visitorUserId <= 0) {
            return 0;
        }
        $user = app()->make(ChatUserServices::class)->getUserInfo($visitorUserId, ['uid', 'appid']);
        if (!$user || (string)$user['appid'] !== $appid) {
            return 0;
        }
        return PlatformSupportServices::tenantIdOf((int)$user['uid']);
    }

    /**
     * 在售的付费套餐
     * @return array
     */
    protected function plans(): array
    {
        return Db::name('tenant_plan')
            ->where(['is_delete' => 0, 'status' => TenantPlan::STATUS_ON])
            ->where('price', '>', 0)
            ->order('sort', 'asc')->order('id', 'asc')
            ->field('id,name,price')
            ->select()->toArray();
    }

    /**
     * @param int $userId
     * @return string
     */
    protected function nickname(int $userId): string
    {
        $user = app()->make(ChatUserServices::class)->getUserInfo($userId, ['nickname']);
        return (string)($user['nickname'] ?? '');
    }
}
