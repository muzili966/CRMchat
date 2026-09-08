<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\models;

use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;
use think\Model;

/**
 * 敏感词命中记录模型
 *
 * 留痕是敏感词功能的主要价值：过滤本身挡不住有心绕过，
 * 但"谁在什么时候发了什么、系统怎么处置的"必须可查可举证。
 * Class SensitiveHit
 * @package app\models
 */
class SensitiveHit extends BaseModel
{
    use ModelTrait;

    const HANDLED_NO = 0;

    const HANDLED_YES = 1;

    /**
     * 留痕的原文最大字符数，与表结构一致
     */
    const CONTENT_MAX_LEN = 500;

    protected $name = 'sensitive_hit';

    protected $pk = 'id';

    protected $autoWriteTimestamp = false;

    /**
     * @param Model $query
     * @param $value
     */
    public function searchHandledAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('handled', (int)$value);
        }
    }

    /**
     * @param Model $query
     * @param $value
     */
    public function searchScopeAttr($query, $value)
    {
        if ($value) {
            $query->where('scope', (int)$value);
        }
    }

    /**
     * @param Model $query
     * @param $value
     */
    public function searchKeywordAttr($query, $value)
    {
        if ($value) {
            $query->where('word', 'like', '%' . $value . '%');
        }
    }

    /**
     * @param Model $query
     * @param $value
     */
    public function searchTimeBetweenAttr($query, $value)
    {
        if (is_array($value) && count($value) === 2 && $value[0] && $value[1]) {
            $query->whereBetween('create_time', [$value[0], $value[1]]);
        }
    }
}
