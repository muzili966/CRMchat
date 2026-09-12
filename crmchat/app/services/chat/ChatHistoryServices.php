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
     * 访客全量对话的导出表头：多一列接待方，用于看出中途换人或AI转人工
     */
    const VISITOR_HEADER = ['时间', '接待方', '发送方', '内容'];

    /**
     * 全局导出额外的会话归属列，拼在消息列之前
     */
    const SESSION_HEADER = ['接待客服', '访客'];

    /**
     * 分包导出时单个访客文件的额外列
     *
     * 整份文件就是一个访客，故只需标明每条消息属于哪位客服的接待。
     */
    const BUNDLE_HEADER = ['接待客服'];

    /**
     * 分包索引的表头，末列由打包器补上文件名
     */
    const BUNDLE_INDEX_HEADER = ['序号', '访客', '手机号', '接待客服数', '消息数', '首条时间', '末条时间', '文件名'];

    /**
     * 分包导出的访客个数上限
     */
    const EXPORT_BUNDLE_MAX = 2000;

    /**
     * 分包导出的消息总条数上限
     *
     * 分包是写一个文件释放一个，内存只与单个访客的往来量有关，
     * 故上限可以远高于合成一张大表的做法。
     */
    const EXPORT_BUNDLE_TOTAL_MAX = 200000;

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
        ChatServiceDialogueRecordServices::MSN_TYPE_RATE => '评价邀请',
        ChatServiceDialogueRecordServices::MSN_TYPE_FAQ => '常见问题',
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
            //档案可能已被清理而消息还在，此处 ?: 不容忍缺键，少一层保护整张列表就500
            $row['visitor_name'] = ($v['remark_nickname'] ?? '') ?: ($v['nickname'] ?? '已删除访客');
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
     * 某访客的全量对话：把他与所有客服的往来按时间合并成一条时间线
     *
     * 按客服拆开看有个实际问题：一个访客先后找过不同客服，还可能先由AI接待
     * 再转人工，要还原他到底经历了什么就得逐个会话点开对照时间。这里合并成
     * 一条流，每条消息标明由谁应答，接待方换人时前端可据此分段。
     * @param array $params visitor_user_id/page/limit
     * @return array
     */
    public function getVisitorTranscript(array $params): array
    {
        $visitorId = (int)($params['visitor_user_id'] ?? 0);
        $agents = $this->agentMap();
        if (!$visitorId || !$agents) {
            return ['list' => [], 'count' => 0];
        }
        //越权防线：该访客必须与本租户的客服有过往来
        $exists = $this->recordTable()->where('to_user_id', $visitorId)
            ->whereIn('user_id', array_keys($agents))->count();
        if (!$exists) {
            return ['list' => [], 'count' => 0];
        }
        /** @var ChatServiceDialogueRecordServices $recordServices */
        $recordServices = app()->make(ChatServiceDialogueRecordServices::class);
        $where = ['visitor' => [$visitorId, array_keys($agents)]];
        [$page, $limit] = $this->pageValue($params);
        return [
            'list' => $this->formatVisitorRecords($recordServices->getVisitorMessageList($where, $page, $limit), $visitorId),
            'count' => $recordServices->getVisitorMessageCount($where),
        ];
    }

    /**
     * 导出访客的全量对话
     * @param array $params visitor_user_id/format
     * @return string 可下载的相对URL
     */
    public function exportVisitorTranscript(array $params): string
    {
        $visitorId = (int)($params['visitor_user_id'] ?? 0);
        $agents = $this->agentMap();
        if (!$visitorId || !$agents) {
            throw new AdminException('访客不存在或无权访问');
        }
        $records = $this->collectVisitorRecords($visitorId, array_keys($agents));
        if (!$records) {
            throw new AdminException('该访客没有可导出的内容');
        }
        return ExportFile::write('visitor_' . $visitorId . '_', $this->visitorExportRows($records),
            ExportFile::normalizeFormat($params['format'] ?? ''), '访客全量对话');
    }

    /**
     * 分批取完某访客的全部消息，并标注好接待方
     * @param int $visitorId
     * @param array $agentIds
     * @return array
     */
    protected function collectVisitorRecords(int $visitorId, array $agentIds): array
    {
        /** @var ChatServiceDialogueRecordServices $recordServices */
        $recordServices = app()->make(ChatServiceDialogueRecordServices::class);
        $where = ['visitor' => [$visitorId, $agentIds]];
        $records = [];
        //多读一个批次越过上限才能确知是否真的被截断，否则「恰好等于上限」会误报
        for ($page = 1; ; $page++) {
            $rows = $recordServices->getVisitorMessageList($where, $page, self::EXPORT_CHUNK);
            $records = array_merge($records, $rows);
            if (count($rows) < self::EXPORT_CHUNK || count($records) > self::EXPORT_MAX) {
                break;
            }
        }
        $truncated = count($records) > self::EXPORT_MAX;
        $records = $this->formatVisitorRecords(array_slice($records, 0, self::EXPORT_MAX), $visitorId);
        if ($truncated) {
            //宁可少给也不能悄悄少给：导出被截断必须在文件里看得见
            $records[] = [
                'add_time' => 0,
                'agent_name' => '',
                'is_ai' => 0,
                'is_agent' => 0,
                'msn_type' => 0,
                'msn' => '（仅导出最早的 ' . self::EXPORT_MAX . ' 条消息，其余已截断）',
            ];
        }
        return $records;
    }

    /**
     * 合并流的展示字段：标出每条由谁应答
     * @param array $list
     * @param int $visitorId
     * @return array
     */
    protected function formatVisitorRecords(array $list, int $visitorId): array
    {
        $agents = $this->agentMap();
        $visitor = $this->visitorMap([$visitorId])[$visitorId] ?? [];
        $visitorName = ($visitor['remark_nickname'] ?? '') ?: ($visitor['nickname'] ?? '访客');
        foreach ($list as &$item) {
            $isAgent = (int)$item['user_id'] !== $visitorId;
            //客服发的取发送方，访客发的取接收方，这样每条都能标明本轮的接待方
            $agentId = $isAgent ? (int)$item['user_id'] : (int)$item['to_user_id'];
            $agent = $agents[$agentId] ?? [];
            $item['is_agent'] = $isAgent ? 1 : 0;
            $item['agent_user_id'] = $agentId;
            $item['agent_name'] = $agent['nickname'] ?? '已删除客服';
            $item['is_ai'] = (int)($agent['is_ai'] ?? 0);
            $item['nickname'] = $isAgent ? $item['agent_name'] : $visitorName;
            $item['avatar'] = $isAgent ? ($agent['avatar'] ?? '') : ($visitor['avatar'] ?? '');
            $item['msn_type'] = (int)$item['msn_type'];
            $this->normalizeTime($item);
        }
        return $list;
    }

    /**
     * 导出行：比单客服导出多一列接待方，便于看出中途换人或AI转人工
     *
     * 入参须是 collectVisitorRecords 标注过的记录，这里只负责排版。
     * @param array $records
     * @return array
     */
    protected function visitorExportRows(array $records): array
    {
        $rows = [self::VISITOR_HEADER];
        foreach ($records as $item) {
            //截断说明行没有时间，不能被当成真实消息渲染出接待方和身份
            $time = (int)$item['add_time'];
            $rows[] = [
                $time ? date('Y-m-d H:i:s', $time) : '',
                $time ? $item['agent_name'] . ($item['is_ai'] ? '(AI)' : '') : '',
                $time ? ($item['is_agent'] ? '客服' : '访客') : '',
                $this->plainContent((int)$item['msn_type'], (string)$item['msn']),
            ];
        }
        return $rows;
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
     * 分包导出：按访客拆成一份份表格，逐个产出交给打包器
     *
     * 用生成器而非一次性返回：写一份释放一份，内存只与单个访客的往来量有关。
     * 举证与交接要的是「这个客户的全部往来」，故按访客而非按会话拆——
     * 同一客户被多个客服接待过时，按会话拆会把它割裂成几份。
     * @param array $where 与列表相同的筛选条件
     * @return \Generator
     */
    public function visitorExportBundle(array $where): \Generator
    {
        $agents = $this->agentMap();
        if (!$agents) {
            return;
        }
        $sessions = $this->bundleSessions($where, array_keys($agents));
        $seq = 0;
        $total = 0;
        foreach ($sessions as $visitorId => $group) {
            if (++$seq > self::EXPORT_BUNDLE_MAX || $total >= self::EXPORT_BUNDLE_TOTAL_MAX) {
                break;
            }
            $file = $this->visitorFile($seq, (int)$visitorId, $group, $agents);
            if (!$file) {
                $seq--;
                continue;
            }
            $total += count($file['rows']) - 1;
            yield $file;
        }
    }

    /**
     * 按访客归组的会话，键为访客ID
     * @param array $where
     * @param array $agentIds
     * @return array
     */
    protected function bundleSessions(array $where, array $agentIds): array
    {
        $rows = $this->sessionQuery($where, $agentIds)
            ->order('update_time DESC, id DESC')
            //一个访客可能有多个会话，按会话数放宽取数上限
            ->limit(self::EXPORT_BUNDLE_MAX * 5)
            ->field('user_id,to_user_id,nickname')
            ->select();
        $rows = is_object($rows) ? $rows->toArray() : (array)$rows;
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int)$row['to_user_id']][] = $row;
        }
        return $grouped;
    }

    /**
     * 组装单个访客的表格与索引行
     * @param int $seq
     * @param int $visitorId
     * @param array $group 该访客的全部会话
     * @param array $agents
     * @return array|null 无对话内容时返回null
     */
    protected function visitorFile(int $seq, int $visitorId, array $group, array $agents)
    {
        $rows = [array_merge(self::BUNDLE_HEADER, self::MESSAGE_HEADER)];
        $times = [];
        foreach ($group as $session) {
            $agentName = $agents[(int)$session['user_id']]['nickname'] ?? '已删除客服';
            foreach ($this->collectRecords((int)$session['user_id'], $visitorId) as $item) {
                if ((int)$item['add_time']) {
                    $times[] = (int)$item['add_time'];
                }
                $rows[] = array_merge([$agentName], $this->messageRow($item));
            }
        }
        if (count($rows) <= 1) {
            return null;
        }
        //多个会话各自按时间正序，合到一份文件里要重新按时间排，否则读起来是乱的
        $rows = $this->sortBundleRows($rows);
        $visitor = $this->visitorMap([$visitorId])[$visitorId] ?? [];
        $name = ($visitor['remark_nickname'] ?? '') ?: ($visitor['nickname'] ?? '');
        $name = $name ?: ($group[0]['nickname'] ?? '');
        return [
            'name' => $seq . '_' . ($name ?: '未命名访客'),
            'rows' => $rows,
            'index' => [
                $seq,
                $name ?: '未命名访客',
                $visitor['phone'] ?? '',
                count($group),
                count($rows) - 1,
                $times ? date('Y-m-d H:i:s', min($times)) : '',
                $times ? date('Y-m-d H:i:s', max($times)) : '',
            ],
        ];
    }

    /**
     * 表头保持首行，其余按时间列升序
     * @param array $rows
     * @return array
     */
    protected function sortBundleRows(array $rows): array
    {
        $header = array_shift($rows);
        //时间列紧跟归属列之后
        $timeIndex = count(self::BUNDLE_HEADER);
        usort($rows, function ($a, $b) use ($timeIndex) {
            return strcmp((string)$a[$timeIndex], (string)$b[$timeIndex]);
        });
        array_unshift($rows, $header);
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
        if ($type === ChatServiceDialogueRecordServices::MSN_TYPE_RATE) {
            //邀请卡片的正文是内部结构，导出成 base64 串对人没有意义
            return '[邀请评价]';
        }
        if ($type === ChatServiceDialogueRecordServices::MSN_TYPE_FAQ) {
            //导出成可读的问题清单，比一串 base64 有用
            $json = base64_decode(trim($msn), true);
            $card = $json === false ? null : json_decode($json, true);
            $titles = array_column(is_array($card['list'] ?? null) ? $card['list'] : [], 'title');
            return $titles ? '[常见问题] ' . implode('、', $titles) : '[常见问题]';
        }
        if ($type === ChatServiceDialogueRecordServices::MSN_TYPE_IME) {
            //只给一串路径，读表的人不知道那是图片；文件与卡片都有标识，图片不该例外
            return '[图片] ' . trim($msn);
        }
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
    /**
     * 归一化一条消息的时间字段
     *
     * 模型有 getAddTimeAttr 访问器，toArray 后 add_time 是 'Y-m-d H:i:s' 字符串，
     * 直接 (int) 会得到年份。这里统一成：_add_time 留展示串，add_time 回到时间戳，
     * 供前端排序与导出格式化使用。
     * @param array $item
     * @return void
     */
    protected function normalizeTime(array &$item): void
    {
        $item['_add_time'] = $item['add_time'];
        $item['add_time'] = is_numeric($item['add_time']) ? (int)$item['add_time'] : (int)strtotime((string)$item['add_time']);
    }
    protected function formatRecords(array $list, int $agentUserId, int $visitorUserId): array
    {
        $agent = $this->agentMap()[$agentUserId] ?? [];
        $visitor = $this->visitorMap([$visitorUserId])[$visitorUserId] ?? [];
        $agentName = $agent['nickname'] ?? '客服';
        $visitorName = ($visitor['remark_nickname'] ?? '') ?: ($visitor['nickname'] ?? '访客');
        foreach ($list as &$item) {
            $isAgent = (int)$item['user_id'] === $agentUserId;
            $item['msn_type'] = (int)$item['msn_type'];
            $this->normalizeTime($item);
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
        //按租户分键缓存：Swoole 是常驻进程，方法级 static 会跨请求存活，
        //平台管理员切换租户视角时会读到上一个租户的客服映射，
        //表现为历史对话列表为空或客服名张冠李戴
        $tenantId = (int)TenantContext::id();
        static $cache = [];
        if (isset($cache[$tenantId])) {
            return $cache[$tenantId];
        }
        $rows = Db::name('chat_service')
            ->where('tenant_id', $tenantId)
            ->field('user_id,nickname,avatar,is_ai')
            ->select();
        $rows = is_object($rows) ? $rows->toArray() : (array)$rows;
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['user_id']] = $row;
        }
        $cache[$tenantId] = $map;
        return $map;
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
