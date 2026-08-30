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

namespace app\dao;


use app\models\AiUsageLog;
use crmeb\basic\BaseDao;

/**
 * AI调用用量流水dao
 * Class AiUsageLogDao
 * @package app\dao
 */
class AiUsageLogDao extends BaseDao
{

    /**
     * 按天聚合的日期格式（MySQL侧格式化，避免把全部流水拉回PHP再分组）
     */
    const DAY_FORMAT = '%Y-%m-%d';

    /**
     * @return string
     */
    protected function setModel(): string
    {
        return AiUsageLog::class;
    }

    /**
     * 用量流水列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getUsageList(array $where, int $page = 0, int $limit = 0)
    {
        return $this->search($where)->when($page && $limit, function ($query) use ($page, $limit) {
            $query->page($page, $limit);
        })->order('id DESC')->select()->toArray();
    }

    /**
     * 汇总token消耗与调用次数
     * @param array $where
     * @return array prompt、completion、count
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function sumTokens(array $where): array
    {
        $row = $this->search($where)->field([
            'SUM(prompt_tokens) as prompt',
            'SUM(completion_tokens) as completion',
            'COUNT(*) as count',
        ])->select()->toArray()[0] ?? [];
        return [
            'prompt' => (int)($row['prompt'] ?? 0),
            'completion' => (int)($row['completion'] ?? 0),
            'count' => (int)($row['count'] ?? 0),
        ];
    }

    /**
     * 按天聚合调用次数
     * @param int $tenantId
     * @param int $startTime
     * @return array [Y-m-d => 次数]
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function countByDay(int $tenantId, int $startTime): array
    {
        $rows = $this->getModel()->where('tenant_id', $tenantId)
            ->where('create_time', '>=', $startTime)
            ->field(["FROM_UNIXTIME(create_time,'" . self::DAY_FORMAT . "') as day", 'COUNT(*) as number'])
            ->group('day')->order('day ASC')
            ->select()->toArray();
        return array_map('intval', array_column($rows, 'number', 'day'));
    }
}
