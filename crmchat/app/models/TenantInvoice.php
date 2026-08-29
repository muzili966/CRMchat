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
 * 租户发票记录模型
 * Class TenantInvoice
 * @package app\models
 */
class TenantInvoice extends BaseModel
{
    use ModelTrait;

    /**
     * 发票类型：普票
     */
    const TYPE_NORMAL = 1;

    /**
     * 发票类型：专票
     */
    const TYPE_SPECIAL = 2;

    /**
     * 状态：待开具
     */
    const STATUS_PENDING = 0;

    /**
     * 状态：已开具
     */
    const STATUS_ISSUED = 1;

    /**
     * 状态：已驳回
     */
    const STATUS_REJECTED = 2;

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'tenant_invoice';

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
}
