<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\performance;

use app\models\ChatSession;
use crmeb\services\tenant\TenantContext;
use think\facade\Db;

/**
 * 客服绩效
 *
 * 指标在收消息时已增量累计到会话行上，这里只做聚合，不碰消息表。
 * Class PerformanceServices
 * @package app\services\performance
 */
class PerformanceServices
{
    /**
     * 趋势最多返回的天数，防止拉一个超长区间把图表撑爆
     */
    const TREND_MAX_DAYS = 92;

    /**
     * 会话明细每页条数上限
     */
    const MAX_LIMIT = 100;

    /**
     * 聚合字段，概览与客服明细共用同一套口径
     */
    const AGGREGATE = 'COUNT(*) AS sessions,
        SUM(visitor_msg_num) AS visitor_msgs,
        SUM(kefu_msg_num) AS kefu_msgs,
        SUM(IF(first_reply_cost > 0, 1, 0)) AS replied,
        SUM(first_reply_cost) AS first_sum,
        SUM(reply_cost_sum) AS reply_sum,
        SUM(reply_count) AS reply_cnt,
        SUM(IF(rate > 0, 1, 0)) AS rated,
        SUM(rate) AS rate_sum,
        SUM(is_ai) AS ai_sessions,
        SUM(transferred) AS transferred,
        SUM(max_pending_cost) AS wait_sum,
        MAX(max_pending_cost) AS wait_max';

    /**
     * 聚合表达式
     *
     * 「超时未应答会话数」要拿租户配置的阈值现算，故不能写死在常量里。
     * 阈值来自配置且强制转为整型，可以安全拼进 SQL。
     * @return string
     */
    protected function aggregate(): string
    {
        $timeout = (int)app()->make(ReplyAlertServices::class)->timeout();
        if ($timeout <= 0) {
            return self::AGGREGATE . ', 0 AS timeout_sessions';
        }
        return self::AGGREGATE . ', SUM(IF(max_pending_cost >= ' . $timeout . ', 1, 0)) AS timeout_sessions';
    }

    /**
     * 概览指标
     * @param array $where start/end/kefu_user_id
     * @return array
     */
    public function overview(array $where): array
    {
        $row = $this->query($where)->field($this->aggregate())->find();
        return $this->metrics(is_array($row) ? $row : []);
    }

    /**
     * 按客服拆分的绩效
     * @param array $where
     * @return array
     */
    public function agents(array $where): array
    {
        $rows = $this->query($where)
            ->group('kefu_user_id')
            ->field('kefu_user_id, ' . $this->aggregate())
            ->select();
        $rows = is_object($rows) ? $rows->toArray() : (array)$rows;
        $agents = $this->agentMap();
        $list = [];
        foreach ($rows as $row) {
            $agent = $agents[(int)$row['kefu_user_id']] ?? [];
            $list[] = array_merge($this->metrics($row), [
                'kefu_user_id' => (int)$row['kefu_user_id'],
                'nickname' => $agent['nickname'] ?? '已删除客服',
                'avatar' => $agent['avatar'] ?? '',
                'is_ai' => (int)($agent['is_ai'] ?? 0),
            ]);
        }
        //接待量高的排前面，一眼看出负荷分布
        usort($list, function ($a, $b) {
            return $b['sessions'] <=> $a['sessions'];
        });
        return $list;
    }

    /**
     * 按天的趋势
     * @param array $where
     * @return array
     */
    public function trend(array $where): array
    {
        [$start, $end] = $this->range($where);
        $rows = $this->query($where)
            ->group('day')
            ->field('FROM_UNIXTIME(start_time, "%Y-%m-%d") AS day, ' . $this->aggregate())
            ->select();
        $rows = is_object($rows) ? $rows->toArray() : (array)$rows;
        $byDay = [];
        foreach ($rows as $row) {
            $byDay[$row['day']] = $this->metrics($row);
        }
        //没有会话的日子也要出现在图上，否则折线会把空档连成斜线，看着像一直有量
        $list = [];
        for ($day = strtotime(date('Y-m-d', $start)); $day <= $end; $day += 86400) {
            $key = date('Y-m-d', $day);
            $list[] = array_merge(['day' => $key], $byDay[$key] ?? $this->metrics([]));
            if (count($list) >= self::TREND_MAX_DAYS) {
                break;
            }
        }
        return $list;
    }

    /**
     * 会话明细，供从报表下钻
     * @param array $where
     * @return array
     */
    public function sessions(array $where): array
    {
        $query = $this->query($where);
        if (isset($where['rated']) && $where['rated'] !== '') {
            $where['rated'] ? $query->where('rate', '>', 0) : $query->where('rate', 0);
        }
        $count = (clone $query)->count();
        [$page, $limit] = $this->pageValue($where);
        $rows = $query->order('start_time DESC, id DESC')->page($page, $limit)->select();
        $rows = is_object($rows) ? $rows->toArray() : (array)$rows;
        $agents = $this->agentMap();
        $visitors = $this->visitorMap(array_column($rows, 'visitor_user_id'));
        foreach ($rows as &$row) {
            $row = $this->formatSession($row, $agents, $visitors);
        }
        return ['list' => $rows, 'count' => $count];
    }

    /**
     * 把聚合行换算成可读指标
     *
     * 平均首响只除以「有回复的会话数」：把没回复的算成 0 秒会让均值虚低，
     * 未回复应该体现在应答率上。
     * @param array $row
     * @return array
     */
    protected function metrics(array $row): array
    {
        $sessions = (int)($row['sessions'] ?? 0);
        $replied = (int)($row['replied'] ?? 0);
        $replyCnt = (int)($row['reply_cnt'] ?? 0);
        $rated = (int)($row['rated'] ?? 0);
        $aiSessions = (int)($row['ai_sessions'] ?? 0);
        return [
            'sessions' => $sessions,
            'visitor_msgs' => (int)($row['visitor_msgs'] ?? 0),
            'kefu_msgs' => (int)($row['kefu_msgs'] ?? 0),
            'replied' => $replied,
            'reply_rate' => $this->ratio($replied, $sessions),
            'first_reply_avg' => $replied ? (int)round((int)$row['first_sum'] / $replied) : 0,
            'reply_avg' => $replyCnt ? (int)round((int)$row['reply_sum'] / $replyCnt) : 0,
            'rated' => $rated,
            'rate_rate' => $this->ratio($rated, $sessions),
            'rate_avg' => $rated ? round((int)$row['rate_sum'] / $rated, 2) : 0,
            'ai_sessions' => $aiSessions,
            'ai_rate' => $this->ratio($aiSessions, $sessions),
            'transferred' => (int)($row['transferred'] ?? 0),
            //转人工率的分母是AI接待的会话，不是全部会话
            'transfer_rate' => $this->ratio((int)($row['transferred'] ?? 0), $aiSessions),
            //最长等待：一次接待里访客等得最久的那段，超时告警看的就是它
            'wait_avg' => $sessions ? (int)round((int)($row['wait_sum'] ?? 0) / $sessions) : 0,
            'wait_max' => (int)($row['wait_max'] ?? 0),
            'timeout_sessions' => (int)($row['timeout_sessions'] ?? 0),
            'timeout_rate' => $this->ratio((int)($row['timeout_sessions'] ?? 0), $sessions),
        ];
    }

    /**
     * 百分比，分母为0时给0而不是除零
     * @param int $part
     * @param int $total
     * @return float
     */
    protected function ratio(int $part, int $total): float
    {
        return $total > 0 ? round($part * 100 / $total, 1) : 0;
    }

    /**
     * @param array $row
     * @param array $agents
     * @param array $visitors
     * @return array
     */
    protected function formatSession(array $row, array $agents, array $visitors): array
    {
        $agent = $agents[(int)$row['kefu_user_id']] ?? [];
        $visitor = $visitors[(int)$row['visitor_user_id']] ?? [];
        $row['agent_name'] = $agent['nickname'] ?? '已删除客服';
        $row['visitor_name'] = ($visitor['remark_nickname'] ?? '') ?: ($visitor['nickname'] ?? '未命名访客');
        $row['duration'] = max(0, (int)($row['end_time'] ?: $row['last_time']) - (int)$row['start_time']);
        $row['reply_avg'] = (int)$row['reply_count'] ? (int)round((int)$row['reply_cost_sum'] / (int)$row['reply_count']) : 0;
        $row['_start_time'] = $row['start_time'] ? date('Y-m-d H:i:s', (int)$row['start_time']) : '';
        return $row;
    }

    /**
     * 查询起点：显式带租户条件与时间区间
     * @param array $where
     * @return \think\db\Query
     */
    protected function query(array $where)
    {
        [$start, $end] = $this->range($where);
        return Db::name('chat_session')
            ->where('tenant_id', (int)TenantContext::id())
            ->whereBetween('start_time', [$start, $end])
            ->when(!empty($where['kefu_user_id']), function ($q) use ($where) {
                $q->where('kefu_user_id', (int)$where['kefu_user_id']);
            })
            ->when(!empty($where['appid']), function ($q) use ($where) {
                $q->where('appid', $where['appid']);
            });
    }

    /**
     * 时间区间，缺省近7天；上限收口防止拉全表
     * @param array $where
     * @return array [start, end]
     */
    protected function range(array $where): array
    {
        $end = !empty($where['end']) ? strtotime($where['end'] . ' 23:59:59') : strtotime(date('Y-m-d') . ' 23:59:59');
        $start = !empty($where['start']) ? strtotime($where['start'] . ' 00:00:00') : $end - 6 * 86400;
        if ($start > $end) {
            [$start, $end] = [$end - 6 * 86400, $end];
        }
        $maxSpan = self::TREND_MAX_DAYS * 86400;
        if ($end - $start > $maxSpan) {
            $start = $end - $maxSpan;
        }
        return [$start, $end];
    }

    /**
     * 当前租户的客服
     * @return array
     */
    protected function agentMap(): array
    {
        $rows = Db::name('chat_service')
            ->where('tenant_id', (int)TenantContext::id())
            ->field('user_id,nickname,avatar,is_ai')
            ->select();
        $rows = is_object($rows) ? $rows->toArray() : (array)$rows;
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['user_id']] = $row;
        }
        return $map;
    }

    /**
     * @param array $ids
     * @return array
     */
    protected function visitorMap(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $rows = Db::name('chat_user')
            ->where('tenant_id', (int)TenantContext::id())
            ->whereIn('id', $ids)
            ->field('id,nickname,remark_nickname')
            ->select();
        $rows = is_object($rows) ? $rows->toArray() : (array)$rows;
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['id']] = $row;
        }
        return $map;
    }

    /**
     * @param array $where
     * @return array [page, limit]
     */
    protected function pageValue(array $where): array
    {
        $page = max(1, (int)($where['page'] ?? 1));
        $limit = (int)($where['limit'] ?? 20);
        $limit = $limit > 0 ? min($limit, self::MAX_LIMIT) : 20;
        return [$page, $limit];
    }
}
