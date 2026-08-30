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
 * AI客服配置模型
 * Class AiConfig
 * @package app\models
 */
class AiConfig extends BaseModel
{
    use ModelTrait;

    /**
     * 接待模式：真人优先，无真人在线时AI值守
     */
    const MODE_STANDBY = 'standby';

    /**
     * 接待模式：AI优先接待，转人工再交由真人
     */
    const MODE_AI_FIRST = 'ai_first';

    /**
     * 合法接待模式集合
     */
    const MODES = [self::MODE_STANDBY, self::MODE_AI_FIRST];

    /**
     * 关闭AI
     */
    const ENABLE_OFF = 0;

    /**
     * 开启AI
     */
    const ENABLE_ON = 1;

    /**
     * 欢迎语长度上限，与DDL varchar(500)一致，超出会被MySQL静默截断
     */
    const MAX_GREETING = 500;

    /**
     * 转人工关键词串长度上限，与DDL varchar(500)一致
     */
    const MAX_TRANSFER_KEYWORDS = 500;

    /**
     * 转人工关键词分隔符
     */
    const KEYWORD_SEPARATOR = ',';

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'ai_config';

    /**
     * 时间字段为int时间戳且由服务层显式写入，全局auto_timestamp会按SQL timestamp类型格式化int值导致TypeError
     * @var bool
     */
    protected $autoWriteTimestamp = false;

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
}
