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
 * AI调用用量流水模型
 * Class AiUsageLog
 * @package app\models
 */
class AiUsageLog extends BaseModel
{
    use ModelTrait;

    /**
     * 调用成功
     */
    const STATUS_OK = 1;

    /**
     * 调用失败
     */
    const STATUS_FAIL = 2;

    /**
     * 调用超时
     */
    const STATUS_TIMEOUT = 3;

    /**
     * 状态文案
     */
    const STATUS_TEXT = [
        self::STATUS_OK => '成功',
        self::STATUS_FAIL => '失败',
        self::STATUS_TIMEOUT => '超时',
    ];

    /**
     * 失败原因长度上限，与DDL varchar(255)一致，超出会被MySQL静默截断
     */
    const MAX_ERROR = 255;

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'ai_usage_log';

    /**
     * 时间字段为int时间戳且由服务层显式写入，全局auto_timestamp会按SQL timestamp类型格式化int值导致TypeError
     * @var bool
     */
    protected $autoWriteTimestamp = false;

    /**
     * 用量流水为平台级成本数据，需跨租户聚合对账，豁免租户隔离
     * @var bool
     */
    protected $tenantScoped = false;

    /**
     * 租户搜索器
     * @param Model $query
     * @param $value
     */
    public function searchTenantIdAttr($query, $value)
    {
        if ($value) {
            $query->where('tenant_id', $value);
        }
    }

    /**
     * 应用搜索器
     * @param Model $query
     * @param $value
     */
    public function searchAppidAttr($query, $value)
    {
        if ($value) {
            $query->where('appid', $value);
        }
    }

    /**
     * 调用状态搜索器
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
     * 调用时间区间搜索器
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
