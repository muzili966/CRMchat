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
 * 客户端装修配置模型
 * Class ApplicationTheme
 * @package app\models
 */
class ApplicationTheme extends BaseModel
{
    use ModelTrait;

    /**
     * 默认主题色
     */
    const DEFAULT_THEME_COLOR = '#2d8cf0';

    /**
     * 显示平台标识
     */
    const BRAND_SHOW = 1;

    /**
     * 隐藏平台标识（白标，需套餐支持）
     */
    const BRAND_HIDE = 0;

    /**
     * 轮播广告条数上限
     */
    const MAX_BANNERS = 5;

    /**
     * 窗口标题长度上限，与DDL varchar(50)一致，超出会被MySQL静默截断
     */
    const MAX_TITLE = 50;

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'application_theme';

    /**
     * 时间字段为int时间戳且由服务层显式写入，全局auto_timestamp会按SQL timestamp类型格式化int值导致TypeError
     * @var bool
     */
    protected $autoWriteTimestamp = false;

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
}
