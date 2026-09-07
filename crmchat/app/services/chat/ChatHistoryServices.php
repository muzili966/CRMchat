<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\chat;

use crmeb\services\tenant\TenantContext;
use think\facade\Db;

/**
 * 历史会话
 *
 * 后台此前只有接口没有页面，管理者看不到任何历史对话——而套餐里却在卖
 * 「记录保留天数」，卖了保留却不给看，这里补上。
 *
 * 数据取自 eb_chat_service_record（会话索引表，一个会话对两行、双方各一个
 * 视角），只取客服那一侧即「一会话一行」，且该行的 nickname/is_tourist 正好
 * 描述对方（访客）。不去 GROUP BY 会越来越大的消息表。
 * Class ChatHistoryServices
 * @package app\services\chat
 */
class ChatHistoryServices
{
    /**
     * 列表每页条数上限，防止前端传入过大值拖垮查询
     */
    const MAX_LIMIT = 100;

    /**
     * 会话视角：一行一个「客服 × 访客」会话
     * @param array $where keyword/kefu_user_id/appid/start/end/page/limit
     * @return array
     */
    public function getSessionList(array $where): array
    {
        $agents = $this->agentMap();
        if (!$agents) {
            return ['list' => [], 'count' => 0];
        }
        $query = $this->sessionQuery($where, array_keys($agents));
        $count = (clone $query)->count();
        [$page, $limit] = $this->pageValue($where);
        $list = $query->order('update_time DESC, id DESC')
            ->page($page, $limit)
            ->field('id,user_id,to_user_id,appid,nickname,avatar,is_tourist,mssage_num,message,message_type,update_time,add_time')
            ->select();
        $list = is_object($list) ? $list->toArray() : (array)$list;
        foreach ($list as &$row) {
            $row = $this->formatSession($row, $agents);
        }
        return ['list' => $list, 'count' => $count];
    }

    /**
     * 访客视角：一行一个访客，聚合其全部会话
     * @param array $where
     * @return array
     */
    public function getVisitorList(array $where): array
    {
        $agents = $this->agentMap();
        if (!$agents) {
            return ['list' => [], 'count' => 0];
        }
        $base = $this->sessionQuery($where, array_keys($agents));
        //按访客聚合：接待过几个客服、共多少条消息、最后活跃时间
        $count = (clone $base)->group('to_user_id')->count();
        [$page, $limit] = $this->pageValue($where);
        $list = $base->group('to_user_id')
            ->order('last_time DESC')
            ->page($page, $limit)
            ->field('to_user_id, COUNT(DISTINCT user_id) AS agent_num, SUM(mssage_num) AS msg_num, MAX(update_time) AS last_time, MAX(id) AS last_id')
            ->select();
        $list = is_object($list) ? $list->toArray() : (array)$list;
        $visitors = $this->visitorMap(array_column($list, 'to_user_id'));
        foreach ($list as &$row) {
            $v = $visitors[(int)$row['to_user_id']] ?? [];
            $row['visitor_id'] = (int)$row['to_user_id'];
            $row['visitor_name'] = $v['remark_nickname'] ?: ($v['nickname'] ?? '');
            $row['avatar'] = $v['avatar'] ?? '';
            $row['phone'] = $v['phone'] ?? '';
            $row['is_tourist'] = (int)($v['is_tourist'] ?? 0);
            $row['agent_num'] = (int)$row['agent_num'];
            $row['msg_num'] = (int)$row['msg_num'];
            $row['_last_time'] = $row['last_time'] ? date('Y-m-d H:i', (int)$row['last_time']) : '';
        }
        return ['list' => $list, 'count' => $count];
    }

    /**
     * 某访客的全部会话（访客视角点进去看）
     * @param int $visitorId
     * @return array
     */
    public function getVisitorSessions(int $visitorId): array
    {
        $agents = $this->agentMap();
        if (!$agents) {
            return [];
        }
        $list = $this->recordTable()
            ->where('to_user_id', $visitorId)
            ->whereIn('user_id', array_keys($agents))
            ->order('update_time DESC')
            ->field('id,user_id,to_user_id,appid,nickname,avatar,is_tourist,mssage_num,message,message_type,update_time,add_time')
            ->select();
        $list = is_object($list) ? $list->toArray() : (array)$list;
        foreach ($list as &$row) {
            $row = $this->formatSession($row, $agents);
        }
        return $list;
    }

    /**
     * 对话内容：复用消息表的 chat 双向检索
     * @param array $params agent_user_id/visitor_user_id/page/limit
     * @return array
     */
    public function getTranscript(array $params): array
    {
        $agent = (int)($params['agent_user_id'] ?? 0);
        $visitor = (int)($params['visitor_user_id'] ?? 0);
        if (!$agent || !$visitor) {
            return ['list' => [], 'count' => 0];
        }
        //越权防线：会话必须属于当前租户的客服
        if (!isset($this->agentMap()[$agent])) {
            return ['list' => [], 'count' => 0];
        }
        /** @var ChatServiceDialogueRecordServices $recordServices */
        $recordServices = app()->make(ChatServiceDialogueRecordServices::class);
        [$page, $limit] = $this->pageValue($params);
        $where = ['chat' => [$agent, $visitor]];
        $list = $recordServices->getMessageList($where, $page, $limit);
        return [
            'list' => $this->formatRecords($list, $agent, $visitor),
            'count' => $recordServices->getMessageCount($where),
        ];
    }

    /**
     * 补齐消息的发送者信息
     *
     * 不走 tidyChat：那个方法依赖 userThis/userTO 关联预加载，而后台历史里
     * 客服与访客本就已知，直接按 user_id 归属即可，也省一次关联查询。
     * @param array $list
     * @param int $agentUserId
     * @param int $visitorUserId
     * @return array
     */
    protected function formatRecords(array $list, int $agentUserId, int $visitorUserId): array
    {
        $agent = $this->agentMap()[$agentUserId] ?? [];
        $visitor = $this->visitorMap([$visitorUserId])[$visitorUserId] ?? [];
        $agentName = $agent['nickname'] ?? '客服';
        $visitorName = ($visitor['remark_nickname'] ?? '') ?: ($visitor['nickname'] ?? '访客');
        foreach ($list as &$item) {
            $isAgent = (int)$item['user_id'] === $agentUserId;
            $item['msn_type'] = (int)$item['msn_type'];
            $item['_add_time'] = $item['add_time'];
            $item['add_time'] = is_numeric($item['add_time']) ? (int)$item['add_time'] : strtotime((string)$item['add_time']);
            $item['is_agent'] = (int)$isAgent;
            $item['nickname'] = $isAgent ? $agentName : $visitorName;
            $item['avatar'] = $isAgent ? ($agent['avatar'] ?? '') : ($visitor['avatar'] ?? '');
        }
        return $list;
    }

    /**
     * 会话查询：仅取客服那一侧，即一会话一行
     * @param array $where
     * @param array $agentIds
     * @return \think\db\Query
     */
    protected function sessionQuery(array $where, array $agentIds)
    {
        return $this->recordTable()
            ->whereIn('user_id', $agentIds)
            ->when(!empty($where['kefu_user_id']), function ($q) use ($where) {
                $q->where('user_id', (int)$where['kefu_user_id']);
            })
            ->when(!empty($where['appid']), function ($q) use ($where) {
                $q->where('appid', $where['appid']);
            })
            ->when(!empty($where['keyword']), function ($q) use ($where) {
                $q->where('nickname', 'like', '%' . $where['keyword'] . '%');
            })
            ->when(!empty($where['start']), function ($q) use ($where) {
                $q->where('update_time', '>=', strtotime($where['start'] . ' 00:00:00'));
            })
            ->when(!empty($where['end']), function ($q) use ($where) {
                $q->where('update_time', '<=', strtotime($where['end'] . ' 23:59:59'));
            });
    }

    /**
     * 会话行补齐客服信息与展示字段
     * @param array $row
     * @param array $agents
     * @return array
     */
    protected function formatSession(array $row, array $agents): array
    {
        $agent = $agents[(int)$row['user_id']] ?? [];
        $row['agent_user_id'] = (int)$row['user_id'];
        $row['agent_name'] = $agent['nickname'] ?? '已删除客服';
        $row['agent_is_ai'] = (int)($agent['is_ai'] ?? 0);
        $row['visitor_id'] = (int)$row['to_user_id'];
        $row['visitor_name'] = $row['nickname'];
        $row['mssage_num'] = (int)$row['mssage_num'];
        $row['_update_time'] = $row['update_time'] ? date('Y-m-d H:i', (int)$row['update_time']) : '';
        return $row;
    }

    /**
     * 当前租户的客服：user_id => {nickname,is_ai}
     * @return array
     */
    protected function agentMap(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $rows = Db::name('chat_service')
            ->where('tenant_id', (int)TenantContext::id())
            ->field('user_id,nickname,avatar,is_ai')
            ->select();
        $rows = is_object($rows) ? $rows->toArray() : (array)$rows;
        $cache = [];
        foreach ($rows as $row) {
            $cache[(int)$row['user_id']] = $row;
        }
        return $cache;
    }

    /**
     * 访客资料
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
            ->field('id,nickname,remark_nickname,avatar,phone,is_tourist')
            ->select();
        $rows = is_object($rows) ? $rows->toArray() : (array)$rows;
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['id']] = $row;
        }
        return $map;
    }

    /**
     * 会话表查询起点，显式带租户条件（不依赖模型全局Scope）
     * @return \think\db\Query
     */
    protected function recordTable()
    {
        return Db::name('chat_service_record')->where('tenant_id', (int)TenantContext::id());
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
