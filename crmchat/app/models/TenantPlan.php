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
 * 租户套餐模型
 * Class TenantPlan
 * @package app\models
 */
class TenantPlan extends BaseModel
{
    use ModelTrait;

    /**
     * 停售状态
     */
    const STATUS_OFF = 0;

    /**
     * 在售状态
     */
    const STATUS_ON = 1;

    /**
     * 配额值：不限
     */
    const LIMIT_UNLIMITED = 0;

    /**
     * 功能开关字段清单
     */
    const FEATURE_FIELDS = ['auto_reply', 'brand_custom', 'data_export', 'app_push', 'ai_reply', 'white_label', 'custom_ad', 'custom_domain', 'file_send'];

    /**
     * 配额字段清单
     */
    const QUOTA_FIELDS = ['app_limit', 'seat_limit', 'daily_msg_limit', 'storage_limit_mb', 'record_keep_days', 'daily_ai_limit'];

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'tenant_plan';

    /**
     * 时间字段为int时间戳且由服务层显式写入，全局auto_timestamp会按SQL timestamp类型格式化int值导致TypeError
     * @var bool
     */
    protected $autoWriteTimestamp = false;

    /**
     * 套餐定义为平台级数据，豁免租户隔离
     * @var bool
     */
    protected $tenantScoped = false;

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
     * 是否删除搜索器
     * @param Model $query
     * @param $value
     */
    public function searchIsDeleteAttr($query, $value)
    {
        $query->where('is_delete', $value);
    }

    /**
     * 套餐名称搜索器
     * @param Model $query
     * @param $value
     */
    public function searchNameLikeAttr($query, $value)
    {
        if ($value) {
            $query->whereLike('name', '%' . $value . '%');
        }
    }
}
