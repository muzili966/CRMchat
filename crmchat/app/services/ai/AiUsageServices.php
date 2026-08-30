<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2020 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace app\services\ai;


use app\dao\AiUsageLogDao;
use app\models\AiUsageLog;
use crmeb\basic\BaseServices;
use crmeb\services\tenant\TenantContext;
use think\facade\Log;

/**
 * AI调用用量service
 * Class AiUsageServices
 * @package app\services\ai
 */
class AiUsageServices extends BaseServices
{

    /**
     * 一天的秒数
     */
    const DAY_SECONDS = 86400;

    /**
     * 用量概览默认统计天数
     */
    const SUMMARY_DAYS = 7;

    /**
     * 按天聚合的日期格式，与AiUsageLogDao::DAY_FORMAT对应
     */
    const DAY_KEY_FORMAT = 'Y-m-d';

    /**
     * AiUsageServices constructor.
     * @param AiUsageLogDao $dao
     */
    public function __construct(AiUsageLogDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 记录一条调用流水
     * @param array $data tenant_id/appid/user_id/model/prompt_tokens/completion_tokens/duration_ms/status/error/create_time
     * @return void
     */
    public function record(array $data): void
    {
        try {
            TenantContext::withoutTenant(function () use ($data) {
                $this->dao->save(self::buildLogRow($data));
            });
        } catch (\Throwable $e) {
            //流水是旁路统计，写失败绝不能中断AI回复主流程，仅留痕
            Log::error('AI用量流水写入失败：' . $e->getMessage());
        }
    }

    /**
     * 组装流水字段（纯函数）
     * @param array $data
     * @return array
     */
    public static function buildLogRow(array $data): array
    {
        $error = (string)($data['error'] ?? '');
        return [
            'tenant_id' => (int)($data['tenant_id'] ?? 0),
            'appid' => (string)($data['appid'] ?? ''),
            'user_id' => (int)($data['user_id'] ?? 0),
            'model' => (string)($data['model'] ?? ''),
            'prompt_tokens' => (int)($data['prompt_tokens'] ?? 0),
            'completion_tokens' => (int)($data['completion_tokens'] ?? 0),
            'duration_ms' => (int)($data['duration_ms'] ?? 0),
            'status' => (int)($data['status'] ?? AiUsageLog::STATUS_OK),
            'error' => mb_substr($error, 0, AiUsageLog::MAX_ERROR),
            'create_time' => (int)($data['create_time'] ?? time()),
        ];
    }

    /**
     * 用量流水分页列表（调用方决定视角：平台全局或指定租户）
     * @param array $where tenant_id/appid/status/create_time_between
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getUsageList(array $where): array
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getUsageList($where, $page, $limit);
        $count = $this->dao->count($where);
        foreach ($list as &$item) {
            $item['_create_time'] = date('Y-m-d H:i:s', $item['create_time']);
            $item['_status'] = AiUsageLog::STATUS_TEXT[$item['status']] ?? '';
        }
        return compact('list', 'count');
    }

    /**
     * 租户用量概览
     * @param int $tenantId
     * @param int $days 统计天数（含今天）
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getSummary(int $tenantId, int $days = self::SUMMARY_DAYS): array
    {
        $days = max(1, $days);
        $startTime = self::startOfDay(time() - ($days - 1) * self::DAY_SECONDS);
        $sum = $this->dao->sumTokens([
            'tenant_id' => $tenantId,
            'create_time_between' => [$startTime, time()],
        ]);
        return [
            'total_count' => $sum['count'],
            'prompt_tokens' => $sum['prompt'],
            'completion_tokens' => $sum['completion'],
            'daily' => self::fillDaily($this->dao->countByDay($tenantId, $startTime), $startTime, $days),
        ];
    }

    /**
     * 补齐无调用的日期（纯函数），保证前端折线图横轴连续
     * @param array $counts
     * @param int $startTime
     * @param int $days
     * @return array
     */
    public static function fillDaily(array $counts, int $startTime, int $days): array
    {
        $daily = [];
        for ($i = 0; $i < $days; $i++) {
            $day = date(self::DAY_KEY_FORMAT, $startTime + $i * self::DAY_SECONDS);
            $daily[$day] = (int)($counts[$day] ?? 0);
        }
        return $daily;
    }

    /**
     * 当天零点时间戳
     * @param int $time
     * @return int
     */
    public static function startOfDay(int $time): int
    {
        return (int)strtotime(date('Y-m-d 00:00:00', $time));
    }
}
