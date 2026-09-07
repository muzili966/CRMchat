<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\chat;

use crmeb\exceptions\AdminException;
use crmeb\services\tenant\TenantContext;
use crmeb\utils\ExportFile;
use think\facade\Db;

/**
 * 历史对话
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
     * 单次导出的消息条数上限
     *
     * 导出要把整段会话读进内存再拼表，不设上限时一段超长会话足以打爆内存；
     * 超出部分按时间正序保留最早的 N 条，并在末行注明被截断。
     */
    const EXPORT_MAX = 5000;

    /**
     * 导出时每批读取条数，避免一次 select 拉回全部消息
     */
    const EXPORT_CHUNK = 500;

    /**
     * 全局导出的会话个数上限
     */
    const EXPORT_SESSION_MAX = 2000;

    /**
     * 全局导出的消息总条数上限
     *
     * 导出走下载中心异步执行，已无请求超时约束，这里只为单进程内存兜底：
     * 实测 5 万行 xlsx 峰值约 72MB，再往上要先改成流式写入。
     */
    const EXPORT_TOTAL_MAX = 50000;

    /**
     * 导出表格的消息列表头
     */
    const MESSAGE_HEADER = ['时间', '发送者', '身份', '类型', '内容'];

    /**
     * 全局导出额外的会话归属列，拼在消息列之前
     */
    const SESSION_HEADER = ['接待客服', '访客'];

    /**
     * 消息类型的中文名，导出表格用
     */
    const TYPE_TEXT = [
        ChatServiceDialogueRecordServices::MSN_TYPE_TXT => '文本',
        ChatServiceDialogueRecordServices::MSN_TYPE_EMOT => '表情',
        ChatServiceDialogueRecordServices::MSN_TYPE_IME => '图片',
        ChatServiceDialogueRecordServices::MSN_TYPE_VOICE => '语音',
        ChatServiceDialogueRecordServices::MSN_TYPE_GOODS => '商品',
        ChatServiceDialogueRecordServices::MSN_TYPE_ORDER => '订单',
        ChatServiceDialogueRecordServices::MSN_TYPE_FILE => '文件',
    ];

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
        [$agent, $visitor] = $this->transcriptParty($params);
        if (!$agent) {
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
     * 导出一段会话的完整对话
     * @param array $params agent_user_id/visitor_user_id/format
     * @return string 可下载的相对URL
     */
    public function exportTranscript(array $params): string
    {
        [$agent, $visitor] = $this->transcriptParty($params);
        if (!$agent) {
            throw new AdminException('会话不存在或无权访问');
        }
        $records = $this->collectRecords($agent, $visitor);
        if (!$records) {
            throw new AdminException('该会话没有可导出的内容');
        }
        $format = ExportFile::normalizeFormat($params['format'] ?? '');
        $prefix = 'chat_' . $agent . '_' . $visitor . '_';
        return ExportFile::write($prefix, $this->exportRows($records), $format, '对话记录');
    }

    /**
     * 全局导出的行数据：把当前筛选条件下的所有会话摊成一份对话明细
     *
     * 一行一条消息，前两列标明归属哪次接待，这样一份文件即可覆盖
     * 一段时间/某个客服的全部往来，不必逐个会话点开导。
     *
     * 只产出行数据，落盘交给下载中心的任务执行器，便于异步跑与统一保留期。
     * @param array $where 与列表相同的筛选条件
     * @return array 二维数组，首行为表头
     */
    public function sessionExportRows(array $where): array
    {
        $agents = $this->agentMap();
        if (!$agents) {
            throw new AdminException('当前没有客服，无可导出的会话');
        }
        $sessions = $this->sessionQuery($where, array_keys($agents))
            ->order('update_time DESC, id DESC')
            //多取一条用于判断是否超出会话上限
            ->limit(self::EXPORT_SESSION_MAX + 1)
            ->field('user_id,to_user_id,nickname')
            ->select();
        $sessions = is_object($sessions) ? $sessions->toArray() : (array)$sessions;
        if (!$sessions) {
            throw new AdminException('当前筛选条件下没有会话');
        }
        $sessionCut = count($sessions) > self::EXPORT_SESSION_MAX;
        $rows = $this->sessionRows(array_slice($sessions, 0, self::EXPORT_SESSION_MAX), $agents, $msgCut);
        if (count($rows) <= 1) {
            throw new AdminException('当前筛选条件下没有对话内容');
        }
        //宁可少给也不能悄悄少给：两种截断都要在文件里看得见
        if ($sessionCut) {
            $rows[] = $this->noteRow('仅导出最近的 ' . self::EXPORT_SESSION_MAX . ' 个会话，其余已截断');
        }
        if ($msgCut) {
            $rows[] = $this->noteRow('已达 ' . self::EXPORT_TOTAL_MAX . ' 条消息上限，其余已截断');
        }
        return $rows;
    }

    /**
     * 逐个会话取消息并拼成带归属列的表格
     * @param array $sessions
     * @param array $agents
     * @param bool $msgCut 出参：是否触达消息总数上限
     * @return array
     */
    protected function sessionRows(array $sessions, array $agents, &$msgCut): array
    {
        $msgCut = false;
        $rows = [array_merge(self::SESSION_HEADER, self::MESSAGE_HEADER)];
        $total = 0;
        foreach ($sessions as $session) {
            $agentName = $agents[(int)$session['user_id']]['nickname'] ?? '已删除客服';
            $visitorName = $session['nickname'] ?: '未命名访客';
            foreach ($this->collectRecords((int)$session['user_id'], (int)$session['to_user_id']) as $item) {
                if ($total >= self::EXPORT_TOTAL_MAX) {
                    $msgCut = true;
                    return $rows;
                }
                $rows[] = array_merge([$agentName, $visitorName], $this->messageRow($item));
                $total++;
            }
        }
        return $rows;
    }

    /**
     * 截断说明行：内容落在最后一列
     * @param string $note
     * @return array
     */
    protected function noteRow(string $note): array
    {
        $width = count(self::SESSION_HEADER) + count(self::MESSAGE_HEADER);
        return array_merge(array_fill(0, $width - 1, ''), ['（' . $note . '）']);
    }

    /**
     * 分批取整段会话，并夹在导出上限内
     * @param int $agent
     * @param int $visitor
     * @return array
     */
    protected function collectRecords(int $agent, int $visitor): array
    {
        /** @var ChatServiceDialogueRecordServices $recordServices */
        $recordServices = app()->make(ChatServiceDialogueRecordServices::class);
        $where = ['chat' => [$agent, $visitor]];
        $records = [];
        //多读一个批次越过上限才能确知是否真的被截断，否则「恰好等于上限」会误报
        for ($page = 1; ; $page++) {
            $rows = $recordServices->getMessageList($where, $page, self::EXPORT_CHUNK);
            $records = array_merge($records, $rows);
            if (count($rows) < self::EXPORT_CHUNK || count($records) > self::EXPORT_MAX) {
                break;
            }
        }
        $truncated = count($records) > self::EXPORT_MAX;
        $records = $this->formatRecords(array_slice($records, 0, self::EXPORT_MAX), $agent, $visitor);
        if ($truncated) {
            //宁可少给也不能悄悄少给：导出被截断必须在文件里看得见
            $records[] = [
                'add_time' => 0,
                'nickname' => '',
                'is_agent' => 0,
                'msn_type' => 0,
                'msn' => '（仅导出最早的 ' . self::EXPORT_MAX . ' 条消息，其余已截断）',
            ];
        }
        return $records;
    }

    /**
     * 组装导出表格：首行表头
     * @param array $records
     * @return array
     */
    protected function exportRows(array $records): array
    {
        $rows = [self::MESSAGE_HEADER];
        foreach ($records as $item) {
            $rows[] = $this->messageRow($item);
        }
        return $rows;
    }

    /**
     * 单条消息的表格行
     *
     * 截断说明行没有时间，不能被当成真实消息渲染出身份和类型。
     * @param array $item
     * @return array
     */
    protected function messageRow(array $item): array
    {
        $time = (int)$item['add_time'];
        return [
            $time ? date('Y-m-d H:i:s', $time) : '',
            $item['nickname'],
            $time ? ($item['is_agent'] ? '客服' : '访客') : '',
            $time ? (self::TYPE_TEXT[(int)$item['msn_type']] ?? '其他') : '',
            $this->plainContent((int)$item['msn_type'], (string)$item['msn']),
        ];
    }

    /**
     * 消息正文转成一行可读文本
     *
     * 表格里没有富文本：图片/语音留URL，文件留文件名与URL，
     * 文本去掉标签并压平换行，避免撑破单元格。
     * @param int $type
     * @param string $msn
     * @return string
     */
    protected function plainContent(int $type, string $msn): string
    {
        if ($type === ChatServiceDialogueRecordServices::MSN_TYPE_FILE) {
            $json = base64_decode(trim($msn), true);
            $file = $json === false ? null : json_decode($json, true);
            if (is_array($file)) {
                return trim(($file['name'] ?? '文件') . ' ' . ($file['url'] ?? ''));
            }
            return '[文件]';
        }
        $text = html_entity_decode(strip_tags($msn), ENT_QUOTES, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * 解析并校验会话双方
     * @param array $params
     * @return array [agentUserId, visitorUserId]；无权访问时返回 [0, 0]
     */
    protected function transcriptParty(array $params): array
    {
        $agent = (int)($params['agent_user_id'] ?? 0);
        $visitor = (int)($params['visitor_user_id'] ?? 0);
        //越权防线：会话必须属于当前租户的客服
        if (!$agent || !$visitor || !isset($this->agentMap()[$agent])) {
            return [0, 0];
        }
        return [$agent, $visitor];
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
