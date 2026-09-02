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

    /** 默认布局风格 */
    const DEFAULT_THEME_STYLE = 'modern';

    /** 为兼容既有字段保留theme命名，实际值控制布局密度 */
    const THEME_STYLES = ['modern', 'minimal', 'soft', 'midnight'];

    /** 默认消息气泡风格 */
    const DEFAULT_BUBBLE_STYLE = 'soft';

    /** 允许使用的消息气泡风格 */
    const BUBBLE_STYLES = ['soft', 'clean', 'pill', 'outline', 'card'];

    /**
     * 显示平台标识
     */
    /** 显示悬浮客服按钮 */
    const TIP_SHOW = 1;

    /** 隐藏悬浮客服按钮：接入方自行放置入口时使用 */
    const TIP_HIDE = 0;

    /** 窗口形态：右下角悬浮对话框 */
    const WINDOW_FLOAT = 'float';

    /** 窗口形态：居中弹窗，带遮罩与广告位 */
    const WINDOW_CENTER = 'center';

    const WINDOW_STYLES = [self::WINDOW_FLOAT, self::WINDOW_CENTER];

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
     * 自定义广告HTML长度上限，字段为text（64KB），按utf8mb4最坏4字节/字留足余量
     */
    const MAX_CUSTOM_HTML = 10000;

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
