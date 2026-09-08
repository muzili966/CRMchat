<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\performance;

use app\dao\ChatSessionDao;
use app\models\ChatSession;
use crmeb\basic\BaseServices;
use crmeb\services\tenant\TenantContext;
use crmeb\utils\SensitiveFilter;
use think\facade\Db;
use think\facade\Log;

/**
 * 会话（一次接待）维护
 *
 * 每条消息进来时把指标增量累计到会话行上：首响、响应耗时、消息数。
 * 不做事后扫描消息表——千万级消息表上算首响不可行，且报表要能即时出数。
 *
 * 任何异常都不得中断消息收发：采集失败只丢指标，不能丢消息。
 * Class ChatSessionServices
 * @package app\services\performance
 */
class ChatSessionServices extends BaseServices
{
    /**
     * 多久无消息视为一次接待结束（秒）
     *
     * 客服系统里访客隔天再来问是新一次接待，不该并进上一次算首响。
     */
    const IDLE_TIMEOUT = 1800;

    /**
     * 超时收尾单次处理的会话数上限
     */
    const CLOSE_BATCH = 500;

    /**
     * ChatSessionServices constructor.
     * @param ChatSessionDao $dao
     */
    public function __construct(ChatSessionDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 记录一条消息，必要时开启新会话
     *
     * @param array $message appid/kefu_user_id/visitor_user_id/from_kefu/add_time/is_ai
     * @return void
     */
    public function track(array $message)
    {
        try {
            $this->doTrack($message);
        } catch (\Throwable $e) {
            //指标采集失败不能拖垮聊天
            Log::error('会话指标采集失败：' . $e->getMessage());
        }
    }

    /**
     * @param array $message
     * @return void
     */
    protected function doTrack(array $message)
    {
        $kefuId = (int)($message['kefu_user_id'] ?? 0);
        $visitorId = (int)($message['visitor_user_id'] ?? 0);
        if (!$kefuId || !$visitorId) {
            return;
        }
        $now = (int)($message['add_time'] ?? time()) ?: time();
        $fromKefu = !empty($message['from_kefu']);
        $session = $this->openSession($kefuId, $visitorId, $now);
        if (!$session) {
            $this->create($message, $now, $fromKefu);
            return;
        }
        $this->accumulate($session, $now, $fromKefu);
    }

    /**
     * 取当前进行中的会话，超时的顺手关掉
     * @param int $kefuId
     * @param int $visitorId
     * @param int $now
     * @return array|null
     */
    protected function openSession(int $kefuId, int $visitorId, int $now)
    {
        $session = $this->table()
            ->where('kefu_user_id', $kefuId)
            ->where('visitor_user_id', $visitorId)
            ->where('status', ChatSession::STATUS_OPEN)
            ->order('id DESC')
            ->find();
        if (!$session) {
            return null;
        }
        if ($now - (int)$session['last_time'] > self::IDLE_TIMEOUT) {
            //跨过静默窗口即属新一次接待，先给上一次收尾
            $this->close((int)$session['id'], ChatSession::END_TIMEOUT, (int)$session['last_time']);
            return null;
        }
        return $session;
    }

    /**
     * 开启新会话
     * @param array $message
     * @param int $now
     * @param bool $fromKefu
     * @return void
     */
    protected function create(array $message, int $now, bool $fromKefu)
    {
        $this->table()->insert([
            'tenant_id' => (int)TenantContext::id(),
            'appid' => (string)($message['appid'] ?? ''),
            'kefu_user_id' => (int)$message['kefu_user_id'],
            'visitor_user_id' => (int)$message['visitor_user_id'],
            'is_ai' => (int)!empty($message['is_ai']),
            'transferred' => 0,
            'start_time' => $now,
            'last_time' => $now,
            'visitor_msg_num' => $fromKefu ? 0 : 1,
            'kefu_msg_num' => $fromKefu ? 1 : 0,
            //客服先开口时没有"等待"可言，首响不计
            'pending_since' => $fromKefu ? 0 : $now,
            'status' => ChatSession::STATUS_OPEN,
        ]);
    }

    /**
     * 把本条消息累计进已有会话
     * @param array $session
     * @param int $now
     * @param bool $fromKefu
     * @return void
     */
    protected function accumulate(array $session, int $now, bool $fromKefu)
    {
        $update = ['last_time' => $now];
        if (!$fromKefu) {
            $update['visitor_msg_num'] = Db::raw('visitor_msg_num + 1');
            //访客连发多条时以最早那条起算等待，不能被后续消息刷新
            if (!(int)$session['pending_since']) {
                $update['pending_since'] = $now;
            }
            $this->table()->where('id', $session['id'])->update($update);
            return;
        }
        $update['kefu_msg_num'] = Db::raw('kefu_msg_num + 1');
        $pending = (int)$session['pending_since'];
        if ($pending) {
            $cost = max(0, $now - $pending);
            $update['reply_cost_sum'] = Db::raw('reply_cost_sum + ' . $cost);
            $update['reply_count'] = Db::raw('reply_count + 1');
            if (!(int)$session['first_reply_cost']) {
                $update['first_reply_cost'] = $cost;
            }
            //回复完即无待回复，下一条访客消息重新起算
            $update['pending_since'] = 0;
        }
        $this->table()->where('id', $session['id'])->update($update);
    }

    /**
     * 标记会话发生过转人工
     * @param int $kefuId 接手的人工客服
     * @param int $visitorId
     * @return void
     */
    public function markTransferred(int $kefuId, int $visitorId)
    {
        try {
            $this->table()
                ->where('kefu_user_id', $kefuId)
                ->where('visitor_user_id', $visitorId)
                ->where('status', ChatSession::STATUS_OPEN)
                ->update(['transferred' => 1]);
        } catch (\Throwable $e) {
            Log::error('标记转人工失败：' . $e->getMessage());
        }
    }

    /**
     * 结束会话
     * @param int $id
     * @param int $endType
     * @param int $endTime 不传则取当前时间
     * @return bool
     */
    public function close(int $id, int $endType, int $endTime = 0): bool
    {
        return (bool)$this->table()->where('id', $id)->where('status', ChatSession::STATUS_OPEN)->update([
            'status' => ChatSession::STATUS_CLOSED,
            'end_type' => $endType,
            'end_time' => $endTime ?: time(),
        ]);
    }

    /**
     * 客服主动结束当前接待
     * @param int $kefuId
     * @param int $visitorId
     * @return bool
     */
    public function closeByKefu(int $kefuId, int $visitorId): bool
    {
        $session = $this->table()
            ->where('kefu_user_id', $kefuId)
            ->where('visitor_user_id', $visitorId)
            ->where('status', ChatSession::STATUS_OPEN)
            ->order('id DESC')
            ->find();
        return $session ? $this->close((int)$session['id'], ChatSession::END_BY_KEFU) : false;
    }

    /**
     * 当前进行中的会话ID，供评价等场景挂载
     * @param int $kefuId
     * @param int $visitorId
     * @return int
     */
    public function openSessionId(int $kefuId, int $visitorId): int
    {
        $session = $this->table()
            ->where('kefu_user_id', $kefuId)
            ->where('visitor_user_id', $visitorId)
            ->where('status', ChatSession::STATUS_OPEN)
            ->order('id DESC')
            ->find();
        return $session ? (int)$session['id'] : 0;
    }

    /**
     * 收尾超时未结束的会话
     *
     * 常驻进程定时调用。不收尾的话进行中的会话会越积越多，
     * 且这些接待永远不进已结束的绩效口径。
     * @return int 收尾条数
     */
    public function closeIdle(): int
    {
        $deadline = time() - self::IDLE_TIMEOUT;
        return (int)TenantContext::withoutTenant(function () use ($deadline) {
            $rows = Db::name('chat_session')
                ->where('status', ChatSession::STATUS_OPEN)
                ->where('last_time', '<', $deadline)
                ->limit(self::CLOSE_BATCH)
                ->field('id,last_time')
                ->select();
            $rows = is_object($rows) ? $rows->toArray() : (array)$rows;
            foreach ($rows as $row) {
                Db::name('chat_session')->where('id', $row['id'])->update([
                    'status' => ChatSession::STATUS_CLOSED,
                    'end_type' => ChatSession::END_TIMEOUT,
                    //结束时间取最后一条消息时刻，而非收尾任务的执行时刻
                    'end_time' => (int)$row['last_time'],
                ]);
            }
            return count($rows);
        });
    }

    /**
     * 会话表查询起点，显式带租户条件
     * @return \think\db\Query
     */
    protected function table()
    {
        return Db::name('chat_session')->where('tenant_id', (int)TenantContext::id());
    }
}
