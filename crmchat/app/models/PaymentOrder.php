<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\models;

use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;
use think\Model;

/**
 * 支付单
 *
 * 平台向租户收款的单据，平台侧要跨租户查看与处理，故不受租户隔离约束；
 * 付款方由 tenant_id 标明，查询一律显式带条件。
 * Class PaymentOrder
 * @package app\models
 */
class PaymentOrder extends BaseModel
{
    use ModelTrait;

    /**
     * 来源：客服在会话里发起
     */
    const SOURCE_KEFU = 'kefu';

    /**
     * 来源：平台后台发起
     */
    const SOURCE_ADMIN = 'admin';

    /**
     * @var string
     */
    protected $pk = 'id';

    /**
     * @var string
     */
    protected $name = 'payment_order';

    /**
     * 时间字段为 int 时间戳且由服务层显式写入
     * @var bool
     */
    protected $autoWriteTimestamp = false;

    /**
     * @var bool
     */
    protected $tenantScoped = false;

    /**
     * @param Model $query
     * @param $value
     */
    public function searchStatusAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', (int)$value);
        }
    }

    /**
     * @param Model $query
     * @param $value
     */
    public function searchChannelAttr($query, $value)
    {
        if ($value) {
            $query->where('channel', $value);
        }
    }

    /**
     * @param Model $query
     * @param $value
     */
    public function searchTenantIdAttr($query, $value)
    {
        if ($value) {
            $query->where('tenant_id', (int)$value);
        }
    }

    /**
     * @param Model $query
     * @param $value
     */
    public function searchPayNoAttr($query, $value)
    {
        if ($value) {
            $query->where('pay_no', $value);
        }
    }

    /**
     * @param Model $query
     * @param $value
     */
    public function searchAbnormalAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('abnormal', (int)$value);
        }
    }
}
