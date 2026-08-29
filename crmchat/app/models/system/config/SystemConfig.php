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

namespace app\models\system\config;

use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;
use think\Model;

/**
 * 系统配置模型
 * Class SystemConfig
 * @package app\models\system\config
 */
class SystemConfig extends BaseModel
{
    use ModelTrait;

    /**
     * 隐藏状态（租户覆盖影子行使用，不参与平台配置结构展示）
     */
    const STATUS_HIDDEN = 0;

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'system_config';

    /**
     * 配置表采用「平台默认+租户覆盖」两层读取，租户维度由服务层显式处理，
     * 不走自动Scope（自动Scope会破坏miss回落平台默认的语义）
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
        if ($value !== '') {
            $query->where('tenant_id', $value);
        }
    }

    /**
     * 菜单名搜索器
     * @param Model $query
     * @param $value
     */
    public function searchMenuNameAttr($query, $value)
    {
        if (is_array($value)) {
            $query->whereIn('menu_name', $value);
        } else {
            $query->where('menu_name', $value);
        }
    }

    /**
     * tab id 搜索
     * @param Model $query
     * @param $value
     */
    public function searchTabIdAttr($query, $value)
    {
        $query->where('config_tab_id', $value);
    }

    /**
     * 状态搜索器
     * @param Model $query
     * @param $value
     */
    public function searchStatusAttr($query, $value)
    {
        $query->where('status', $value ?: 1);
    }

    /**
     * value搜索器
     * @param Model $query
     * @param $value
     */
    public function searchValueAttr($query, $value)
    {
        $query->where('value', $value);
    }
}
