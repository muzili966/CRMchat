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

namespace app\models;


use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;
use think\Model;

/**
 * 租户套餐订购对账模型
 * Class TenantPlanOrder
 * @package app\models
 */
class TenantPlanOrder extends BaseModel
{
    use ModelTrait;

    /**
     * 支付方式：后台开通
     */
    const PAY_TYPE_BACKEND = 1;

    /**
     * 支付方式：线下转账
     */
    const PAY_TYPE_OFFLINE = 2;

    /**
     * 订单状态：已生效
     */
    const STATUS_EFFECTIVE = 1;

    /**
     * 订单状态：已作废
     */
    const STATUS_VOID = 2;

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'tenant_plan_order';

    /**
     * 时间字段为int时间戳且由服务层显式写入，全局auto_timestamp会按SQL timestamp类型格式化int值导致TypeError
     * @var bool
     */
    protected $autoWriteTimestamp = false;

    /**
     * 状态搜索器
     * @param Model $query
     * @param $value
     */
    public function searchStatusAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }

    /**
     * 套餐搜索器
     * @param Model $query
     * @param $value
     */
    public function searchPlanIdAttr($query, $value)
    {
        if ($value) {
            $query->where('plan_id', $value);
        }
    }

    /**
     * 对账单号搜索器
     * @param Model $query
     * @param $value
     */
    public function searchOrderNoAttr($query, $value)
    {
        if ($value) {
            $query->where('order_no', $value);
        }
    }

    /**
     * 下单时间区间搜索器
     * @param Model $query
     * @param $value
     */
    public function searchCreateTimeBetweenAttr($query, $value)
    {
        if (is_array($value) && count($value) == 2 && $value[0] && $value[1]) {
            $query->whereBetween('create_time', [$value[0], $value[1]]);
        }
    }
}
