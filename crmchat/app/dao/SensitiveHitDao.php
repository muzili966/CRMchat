<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\dao;

use app\models\SensitiveHit;
use crmeb\basic\BaseDao;

/**
 * 敏感词命中记录dao
 * Class SensitiveHitDao
 * @package app\dao
 */
class SensitiveHitDao extends BaseDao
{
    /**
     * @return string
     */
    protected function setModel(): string
    {
        return SensitiveHit::class;
    }

    /**
     * 命中记录列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getHitList(array $where, int $page = 0, int $limit = 0): array
    {
        return $this->search($where)->when($page && $limit, function ($query) use ($page, $limit) {
            $query->page($page, $limit);
        })->order('id DESC')->select()->toArray();
    }
}
