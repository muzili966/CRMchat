<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\models;

use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;
use think\Model;

/**
 * 客服会话（一次接待）
 *
 * eb_chat_service_record 是「客服×访客」的持久索引行，表达不了「一次接待」；
 * 绩效与满意度都需要这个粒度，故单列一张表。
 * Class ChatSession
 * @package app\models
 */
class ChatSession extends BaseModel
{
    use ModelTrait;

    const STATUS_OPEN = 1;

    const STATUS_CLOSED = 2;

    /**
     * 结束方式：超时自动
     */
    const END_TIMEOUT = 1;

    /**
     * 结束方式：客服主动结束
     */
    const END_BY_KEFU = 2;

    /**
     * 满意度分值范围
     */
    const RATE_MIN = 1;

    const RATE_MAX = 5;

    protected $name = 'chat_session';

    protected $pk = 'id';

    protected $autoWriteTimestamp = false;

    /**
     * @param Model $query
     * @param $value
     */
    public function searchKefuUserIdAttr($query, $value)
    {
        if ($value) {
            $query->where('kefu_user_id', (int)$value);
        }
    }

    /**
     * @param Model $query
     * @param $value
     */
    public function searchStatusAttr($query, $value)
    {
        if ($value) {
            $query->where('status', (int)$value);
        }
    }

    /**
     * @param Model $query
     * @param $value
     */
    public function searchStartBetweenAttr($query, $value)
    {
        if (is_array($value) && count($value) === 2 && $value[0] && $value[1]) {
            $query->whereBetween('start_time', [$value[0], $value[1]]);
        }
    }

    /**
     * 只看已评价的会话
     * @param Model $query
     * @param $value
     */
    public function searchRatedAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $value ? $query->where('rate', '>', 0) : $query->where('rate', 0);
        }
    }
}
