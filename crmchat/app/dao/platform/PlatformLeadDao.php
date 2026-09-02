<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\dao\platform;

use app\models\PlatformLead;
use crmeb\basic\BaseDao;

/**
 * Class PlatformLeadDao
 * @package app\dao\platform
 */
class PlatformLeadDao extends BaseDao
{
    protected function setModel(): string
    {
        return PlatformLead::class;
    }

    /**
     * 线索列表：待跟进的排在前面，其中逾期的最优先
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getLeadList(array $where, int $page, int $limit): array
    {
        return $this->search($where)
            ->when($page && $limit, function ($query) use ($page, $limit) {
                $query->page($page, $limit);
            })
            //已成交与已关闭沉底，其余按下次跟进时间由近及远
            ->orderRaw('FIELD(stage, 4, 5) ASC')
            ->order('next_follow_time ASC, id DESC')
            ->select()->toArray();
    }

    /**
     * 各阶段的线索数量，用于列表页的概览
     * @return array
     */
    public function countByStage(): array
    {
        return $this->getModel()::where('is_delete', 0)
            ->group('stage')->column('COUNT(*) as num', 'stage');
    }
}
