<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\performance;

use app\models\ChatSession;
use crmeb\services\SwooleTaskService;
use crmeb\services\SystemConfigService;
use crmeb\services\tenant\TenantContext;
use think\facade\Db;
use think\facade\Log;

/**
 * 无人应答提醒
 *
 * 访客发问后客服迟迟不回是真实的服务事故。原料本就躺在会话行的 pending_since 上，
 * 此前只用来算响应时长，没人拿它做告警。
 *
 * 只算人工坐席：AI 是秒回的，AI 会话上谈"无人应答"没有意义；
 * AI 自己不回属于可用性问题，该由 AI 用量与失败率去看。
 * Class ReplyAlertServices
 * @package app\services\performance
 */
class ReplyAlertServices
{
    /**
     * 阈值配置项，0=关闭
     */
    const CONFIG_KEY = 'reply_timeout';

    /**
     * 默认阈值（秒）
     */
    const DEFAULT_TIMEOUT = 180;

    /**
     * 阈值下限，防止配得过小把告警刷成噪音
     */
    const MIN_TIMEOUT = 30;

    /**
     * 同一会话两次告警的最小间隔（秒）
     *
     * 不去重的话扫描每跑一轮就推一次，客服端会被同一条会话刷屏。
     */
    const ALERT_INTERVAL = 300;

    /**
     * 单轮扫描处理的会话数上限
     */
    const SCAN_BATCH = 200;

    /**
     * 扫描全部租户的超时会话并推送提醒
     * @return int 本轮告警条数
     */
    public function scan(): int
    {
        try {
            return $this->doScan();
        } catch (\Throwable $e) {
            Log::error('无人应答扫描失败：' . $e->getMessage());
            return 0;
        }
    }

    /**
     * @return int
     */
    protected function doScan(): int
    {
        $now = time();
        $rows = TenantContext::withoutTenant(function () use ($now) {
            return Db::name('chat_session')
                ->where('status', ChatSession::STATUS_OPEN)
                ->where('pending_since', '>', 0)
                //AI 会话不参与：AI 秒回，其未回属可用性问题而非服务事故
                ->where('is_ai', 0)
                ->where('pending_since', '<', $now - self::MIN_TIMEOUT)
                ->order('pending_since ASC')
                ->limit(self::SCAN_BATCH)
                ->field('id,tenant_id,appid,kefu_user_id,visitor_user_id,pending_since,alerted_at')
                ->select();
        });
        $rows = is_object($rows) ? $rows->toArray() : (array)$rows;
        if (!$rows) {
            return 0;
        }
        $alerted = 0;
        //阈值按租户配置，同租户的会话共用一次读取
        $timeouts = [];
        foreach ($rows as $row) {
            $tenantId = (int)$row['tenant_id'];
            if (!isset($timeouts[$tenantId])) {
                $timeouts[$tenantId] = $this->timeoutOf($tenantId);
            }
            $timeout = $timeouts[$tenantId];
            if (!$timeout) {
                continue;
            }
            $waited = $now - (int)$row['pending_since'];
            if ($waited < $timeout) {
                continue;
            }
            //最长等待要随扫描一起推进，否则一直没人回的会话在报表里等待时长为0
            $this->touch((int)$row['id'], $waited);
            if ((int)$row['alerted_at'] && $now - (int)$row['alerted_at'] < self::ALERT_INTERVAL) {
                continue;
            }
            $this->alert($row, $waited, $now);
            $alerted++;
        }
        return $alerted;
    }

    /**
     * 当前租户的阈值
     * @return int
     */
    public function timeout(): int
    {
        return $this->timeoutOf((int)TenantContext::id());
    }

    /**
     * 租户的阈值，0 表示该租户关闭了提醒
     * @param int $tenantId
     * @return int
     */
    protected function timeoutOf(int $tenantId): int
    {
        $value = TenantContext::runAs($tenantId, function () {
            return SystemConfigService::get(self::CONFIG_KEY, self::DEFAULT_TIMEOUT);
        });
        $value = (int)$value;
        if ($value <= 0) {
            return 0;
        }
        return max(self::MIN_TIMEOUT, $value);
    }

    /**
     * 推进最长等待
     * @param int $sessionId
     * @param int $waited
     * @return void
     */
    protected function touch(int $sessionId, int $waited)
    {
        TenantContext::withoutTenant(function () use ($sessionId, $waited) {
            Db::name('chat_session')->where('id', $sessionId)->update([
                'max_pending_cost' => Db::raw('GREATEST(max_pending_cost, ' . $waited . ')'),
            ]);
        });
    }

    /**
     * 推送提醒给接待客服
     * @param array $row
     * @param int $waited
     * @param int $now
     * @return void
     */
    protected function alert(array $row, int $waited, int $now)
    {
        TenantContext::withoutTenant(function () use ($row, $now) {
            Db::name('chat_session')->where('id', $row['id'])->update(['alerted_at' => $now]);
        });
        try {
            //推送要在任务所属租户上下文里发，否则取不到该租户的连接注册表
            TenantContext::runAs((int)$row['tenant_id'], function () use ($row, $waited) {
                SwooleTaskService::kefu()->type('reply_alert')->to((int)$row['kefu_user_id'])->data([
                    'session_id' => (int)$row['id'],
                    'user_id' => (int)$row['visitor_user_id'],
                    'waited' => $waited,
                ])->push();
            });
        } catch (\Throwable $e) {
            //推不到不该让扫描中断：alerted_at 已落库，下轮不会重复刷
            Log::error('无人应答提醒推送失败：' . $e->getMessage());
        }
    }

    /**
     * 当前租户下超时未应答的会话，供后台查看
     * @param int $limit
     * @return array
     */
    public function pendingList(int $limit = 50): array
    {
        $tenantId = (int)TenantContext::id();
        $timeout = $this->timeoutOf($tenantId);
        if (!$timeout) {
            return ['timeout' => 0, 'list' => [], 'count' => 0];
        }
        $now = time();
        $query = Db::name('chat_session')
            ->where('tenant_id', $tenantId)
            ->where('status', ChatSession::STATUS_OPEN)
            ->where('is_ai', 0)
            ->where('pending_since', '>', 0)
            ->where('pending_since', '<=', $now - $timeout);
        $count = (clone $query)->count();
        $rows = $query->order('pending_since ASC')
            ->limit(max(1, min($limit, 200)))
            ->field('id,kefu_user_id,visitor_user_id,pending_since,visitor_msg_num,kefu_msg_num')
            ->select();
        $rows = is_object($rows) ? $rows->toArray() : (array)$rows;
        $agents = $this->nameMap('chat_service', 'user_id', $tenantId);
        $visitors = $this->nameMap('chat_user', 'id', $tenantId);
        foreach ($rows as &$row) {
            $row['waited'] = $now - (int)$row['pending_since'];
            $row['agent_name'] = $agents[(int)$row['kefu_user_id']] ?? '已删除客服';
            $row['visitor_name'] = $visitors[(int)$row['visitor_user_id']] ?? '未命名访客';
            $row['_pending_since'] = date('Y-m-d H:i:s', (int)$row['pending_since']);
        }
        return ['timeout' => $timeout, 'list' => $rows, 'count' => $count];
    }

    /**
     * 取 id => nickname 映射
     * @param string $table
     * @param string $key
     * @param int $tenantId
     * @return array
     */
    protected function nameMap(string $table, string $key, int $tenantId): array
    {
        $rows = Db::name($table)->where('tenant_id', $tenantId)->field($key . ',nickname')->select();
        $rows = is_object($rows) ? $rows->toArray() : (array)$rows;
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row[$key]] = $row['nickname'];
        }
        return $map;
    }
}
