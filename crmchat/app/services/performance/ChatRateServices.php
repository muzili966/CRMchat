<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\performance;

use app\models\ChatSession;
use app\services\chat\ChatServiceDialogueRecordServices;
use crmeb\exceptions\AdminException;
use crmeb\services\SwooleTaskService;
use crmeb\services\tenant\TenantContext;
use think\facade\Db;
use think\facade\Log;

/**
 * 满意度评价
 *
 * 邀请由客服发起：超时自动结束时访客多半已经离开，等结束再邀请基本收不到评价。
 * 邀请是一条 msn_type=8 的消息，访客端渲染成星级卡片。
 *
 * 该类型不在客户端可发送的白名单（MSN_TYPE）内，只由服务端插入，
 * 否则访客能自己伪造邀请卡片。
 * Class ChatRateServices
 * @package app\services\performance
 */
class ChatRateServices
{
    /**
     * 同一次接待允许的邀请次数上限，防止客服反复刷屏催评价
     */
    const INVITE_MAX = 3;

    /**
     * 评价留言长度上限
     */
    const REMARK_MAX = 200;

    /**
     * @var ChatSessionServices
     */
    protected $sessionServices;

    /**
     * @param ChatSessionServices $sessionServices
     */
    public function __construct(ChatSessionServices $sessionServices)
    {
        $this->sessionServices = $sessionServices;
    }

    /**
     * 客服发起评价邀请
     * @param string $appid
     * @param int $kefuUserId
     * @param int $visitorUserId
     * @return array 插入的消息
     */
    public function invite(string $appid, int $kefuUserId, int $visitorUserId): array
    {
        $sessionId = $this->sessionServices->openSessionId($kefuUserId, $visitorUserId);
        if (!$sessionId) {
            throw new AdminException('当前没有进行中的会话，无法邀请评价');
        }
        $session = $this->session($sessionId);
        if ((int)$session['rate']) {
            throw new AdminException('访客已评价过本次接待');
        }
        if ($this->inviteCount($sessionId) >= self::INVITE_MAX) {
            throw new AdminException('本次接待的邀请次数已达上限');
        }
        $record = $this->saveInvite($appid, $kefuUserId, $visitorUserId, $sessionId);
        try {
            SwooleTaskService::user()->type('chat')->to($visitorUserId)->data($record)->push();
        } catch (\Throwable $e) {
            //消息已入库，推送失败只是这一刻没送达，访客刷新即可见；
            //不该因此把整个邀请报成失败，否则客服会重复点、刷出一堆卡片
            Log::error('评价邀请推送失败：' . $e->getMessage());
        }
        return $record;
    }

    /**
     * 访客提交评价
     * @param int $visitorUserId 取自登录态，不信任客户端
     * @param array $data session_id/rate/remark
     * @return bool
     */
    public function submit(int $visitorUserId, array $data): bool
    {
        $rate = (int)($data['rate'] ?? 0);
        if ($rate < ChatSession::RATE_MIN || $rate > ChatSession::RATE_MAX) {
            throw new AdminException('评分不合法');
        }
        $session = $this->session((int)($data['session_id'] ?? 0));
        //会话号来自邀请卡片，仍要确认它属于当前访客，否则可给别人的接待打分
        if ((int)$session['visitor_user_id'] !== $visitorUserId) {
            throw new AdminException('会话不存在');
        }
        if ((int)$session['rate']) {
            throw new AdminException('本次接待已评价过');
        }
        return (bool)$this->table()->where('id', $session['id'])->where('rate', 0)->update([
            'rate' => $rate,
            'rate_remark' => mb_substr((string)($data['remark'] ?? ''), 0, self::REMARK_MAX),
            'rate_time' => time(),
        ]);
    }

    /**
     * 当前进行中接待的评价状态，供客服端显隐邀请入口
     * @param int $kefuUserId
     * @param int $visitorUserId
     * @return array
     */
    public function status(int $kefuUserId, int $visitorUserId): array
    {
        $sessionId = $this->sessionServices->openSessionId($kefuUserId, $visitorUserId);
        if (!$sessionId) {
            return ['session_id' => 0, 'rate' => 0, 'invited' => 0, 'can_invite' => 0];
        }
        $session = $this->table()->where('id', $sessionId)->find();
        $invited = $this->inviteCount($sessionId);
        return [
            'session_id' => $sessionId,
            'rate' => (int)($session['rate'] ?? 0),
            'invited' => $invited,
            'can_invite' => (int)(!(int)($session['rate'] ?? 0) && $invited < self::INVITE_MAX),
        ];
    }

    /**
     * 落库邀请消息
     * @param string $appid
     * @param int $kefuUserId
     * @param int $visitorUserId
     * @param int $sessionId
     * @return array
     */
    protected function saveInvite(string $appid, int $kefuUserId, int $visitorUserId, int $sessionId): array
    {
        $now = time();
        //与文件消息同一套路：正文放 base64(JSON)，避免入库前的 strip_tags 破坏结构
        $payload = base64_encode((string)json_encode(['session_id' => $sessionId], JSON_UNESCAPED_UNICODE));
        /** @var ChatServiceDialogueRecordServices $recordServices */
        $recordServices = app()->make(ChatServiceDialogueRecordServices::class);
        $record = $recordServices->save([
            'appid' => $appid,
            'user_id' => $kefuUserId,
            'to_user_id' => $visitorUserId,
            'msn' => $payload,
            'msn_type' => ChatServiceDialogueRecordServices::MSN_TYPE_RATE,
            'other' => '',
            'type' => 0,
            'is_send' => 1,
            'add_time' => $now,
        ]);
        $data = $record->toArray();
        $data['_add_time'] = $data['add_time'];
        $data['add_time'] = is_numeric($data['add_time']) ? (int)$data['add_time'] : strtotime((string)$data['add_time']);
        //同常见问题卡片：客户端直接绑 item.avatar，缺了会渲染成没有 src 的
        //img，而没有 src 就不触发 error 事件，头像兜底也就没机会生效
        /** @var \app\services\chat\ChatUserServices $userService */
        $userService = app()->make(\app\services\chat\ChatUserServices::class);
        $sender = $userService->getUserInfo($kefuUserId, ['nickname', 'avatar']);
        $data['nickname'] = $sender['nickname'] ?? '';
        $data['avatar'] = $sender['avatar'] ?? '';
        return $data;
    }

    /**
     * 本次接待已发出的邀请次数
     * @param int $sessionId
     * @return int
     */
    protected function inviteCount(int $sessionId): int
    {
        $session = $this->table()->where('id', $sessionId)->field('kefu_user_id,visitor_user_id,start_time')->find();
        if (!$session) {
            return 0;
        }
        return (int)Db::name('chat_service_dialogue_record')
            ->where('tenant_id', (int)TenantContext::id())
            ->where('user_id', $session['kefu_user_id'])
            ->where('to_user_id', $session['visitor_user_id'])
            ->where('msn_type', ChatServiceDialogueRecordServices::MSN_TYPE_RATE)
            ->where('add_time', '>=', $session['start_time'])
            ->count();
    }

    /**
     * @param int $id
     * @return array
     */
    protected function session(int $id): array
    {
        $session = $id ? $this->table()->where('id', $id)->find() : null;
        if (!$session) {
            throw new AdminException('会话不存在');
        }
        return $session;
    }

    /**
     * @return \think\db\Query
     */
    protected function table()
    {
        return Db::name('chat_session')->where('tenant_id', (int)TenantContext::id());
    }
}
