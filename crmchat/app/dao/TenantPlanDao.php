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


use app\models\TenantPlan;
use crmeb\basic\BaseDao;

/**
 * 租户套餐dao
 * Class TenantPlanDao
 * @package app\dao
 */
class TenantPlanDao extends BaseDao
{

    /**
     * @return string
     */
    protected function setModel(): string
    {
        return TenantPlan::class;
    }

    /**
     * 套餐列表
     * @param array $where
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getPlanList(array $where)
    {
        return $this->search($where)->order('sort ASC,id ASC')->select()->toArray();
    }

    /**
     * 价格最低的在售套餐（新租户默认套餐）
     * @return \think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getDefaultPlan()
    {
        return $this->getModel()->where('status', TenantPlan::STATUS_ON)
            ->where('is_delete', 0)
            ->order('price ASC,id ASC')
            ->find();
    }
}
