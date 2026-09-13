<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\payment\delivery;

use app\services\chat\ChatServiceDialogueRecordServices;
use app\services\chat\ChatUserServices;
use crmeb\services\payment\contract\DeliveryInterface;
use crmeb\services\payment\dto\PaymentOrder;
use crmeb\services\payment\PaymentException;
use crmeb\services\SwooleTaskService;
use think\facade\Log;

/**
 * 聊天支付卡片：客服在会话里直接发给租户
 *
 * 比发一张收款码图片多了两样东西：卡片上写明付的是哪张单、多少钱，
 * 点开是带签名的收银台，付完自动开通，不用再截图回来让人工核对。
 * Class ChatCardDelivery
 * @package app\services\payment\delivery
 */
class ChatCardDelivery implements DeliveryInterface
{
    const CODE = 'chat_card';

    /**
     * 会话上下文里必须有的字段
     */
    const CONTEXT_KEYS = ['appid', 'kefu_user_id', 'visitor_user_id'];

    /**
     * @return string
     */
    public function code(): string
    {
        return self::CODE;
    }

    /**
     * @param PaymentOrder $order
     * @param array $context cashier_url/appid/kefu_user_id/visitor_user_id
     * @return array 聊天记录，结构与客户端收到的推送一致
     */
    public function deliver(PaymentOrder $order, array $context): array
    {
        $msn = self::encode($order, LinkDelivery::requireUrl($context));
        $record = $this->save($msn, $context);
        try {
            SwooleTaskService::user()->type('chat')->to((int)$context['visitor_user_id'])->data($record)->push();
        } catch (\Throwable $e) {
            //消息已入库，推送失败只是这一刻没送达，访客刷新即可见；不该报成发送失败让客服重发出第二张单
            Log::error('支付卡片推送失败：' . $e->getMessage());
        }
        return $record;
    }

    /**
     * 卡片正文：与其他卡片同一套路，base64(JSON) 避免入库前的标签过滤破坏结构
     * @param PaymentOrder $order
     * @param string $url
     * @return string
     */
    public static function encode(PaymentOrder $order, string $url): string
    {
        return base64_encode((string)json_encode([
            'pay_no' => $order->payNo(),
            'subject' => $order->subject(),
            'amount' => $order->amount(),
            'expire_at' => $order->expireAt(),
            'url' => $url,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param string $msn
     * @param array $context
     * @return array
     */
    protected function save(string $msn, array $context): array
    {
        foreach (self::CONTEXT_KEYS as $key) {
            if (empty($context[$key])) {
                throw new PaymentException('发送支付卡片缺少会话信息：' . $key);
            }
        }
        $record = app()->make(ChatServiceDialogueRecordServices::class)->save([
            'appid' => (string)$context['appid'],
            'user_id' => (int)$context['kefu_user_id'],
            'to_user_id' => (int)$context['visitor_user_id'],
            'msn' => $msn,
            'msn_type' => ChatServiceDialogueRecordServices::MSN_TYPE_PAY,
            'other' => '',
            'type' => 0,
            'is_send' => 1,
            'add_time' => time(),
        ]);
        return $this->withSender($record->toArray(), (int)$context['kefu_user_id']);
    }

    /**
     * 补齐客户端渲染要的字段：缺头像会渲染成没有 src 的 img，兜底头像也不会生效
     * @param array $data
     * @param int $kefuUserId
     * @return array
     */
    protected function withSender(array $data, int $kefuUserId): array
    {
        $data['_add_time'] = $data['add_time'];
        $data['add_time'] = is_numeric($data['add_time']) ? (int)$data['add_time'] : strtotime((string)$data['add_time']);
        $sender = app()->make(ChatUserServices::class)->getUserInfo($kefuUserId, ['nickname', 'avatar']);
        $data['nickname'] = $sender['nickname'] ?? '';
        $data['avatar'] = $sender['avatar'] ?? '';
        return $data;
    }
}
