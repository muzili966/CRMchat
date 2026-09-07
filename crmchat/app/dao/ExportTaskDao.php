<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\dao;

use app\models\ExportTask;
use crmeb\basic\BaseDao;

/**
 * 导出任务dao
 * Class ExportTaskDao
 * @package app\dao
 */
class ExportTaskDao extends BaseDao
{
    /**
     * @return string
     */
    protected function setModel(): string
    {
        return ExportTask::class;
    }

    /**
     * 任务列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getTaskList(array $where, int $page = 0, int $limit = 0): array
    {
        return $this->search($where)->when($page && $limit, function ($query) use ($page, $limit) {
            $query->page($page, $limit);
        })->order('id DESC')->select()->toArray();
    }

    /**
     * 未结束的任务数（排队中 + 生成中）
     * @param int $tenantId
     * @return int
     */
    public function countActive(int $tenantId): int
    {
        return (int)$this->getModel()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [ExportTask::STATUS_PENDING, ExportTask::STATUS_RUNNING])
            ->count();
    }

    /**
     * 已过期的任务，只取删除所需字段
     * @param int $now
     * @return array
     */
    public function expired(int $now): array
    {
        return $this->getModel()
            ->where('expire_time', '>', 0)
            ->where('expire_time', '<=', $now)
            ->field('id,file_url')
            ->select()->toArray();
    }

    /**
     * 批量删除
     * @param array $ids
     * @return int
     */
    public function deleteByIds(array $ids): int
    {
        if (!$ids) {
            return 0;
        }
        return (int)$this->getModel()->whereIn('id', $ids)->delete();
    }
}
