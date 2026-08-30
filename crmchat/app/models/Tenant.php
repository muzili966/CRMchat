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
 * 租户模型
 * Class Tenant
 * @package app\models
 */
class Tenant extends BaseModel
{
    use ModelTrait;

    /**
     * 禁用状态
     */
    const STATUS_DISABLE = 0;

    /**
     * 正常状态
     */
    const STATUS_NORMAL = 1;

    /**
     * 存量数据迁移归属的默认租户ID
     */
    const DEFAULT_TENANT_ID = 1;

    /**
     * 平台（无租户）上下文标识
     */
    const PLATFORM_TENANT_ID = 0;

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'tenant';

    /**
     * 时间字段为int时间戳且由服务层显式写入，全局auto_timestamp会按SQL timestamp类型格式化int值导致TypeError
     * @var bool
     */
    protected $autoWriteTimestamp = false;

    /**
     * 租户本体为平台级数据，豁免租户隔离
     * @var bool
     */
    protected $tenantScoped = false;

    /**
     * 租户名称搜索器
     * @param Model $query
     * @param $value
     */
    public function searchNameLikeAttr($query, $value)
    {
        if ($value) {
            $query->whereLike('name', '%' . $value . '%');
        }
    }

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
}
