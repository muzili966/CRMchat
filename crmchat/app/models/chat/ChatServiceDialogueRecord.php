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

namespace app\models\chat;


use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;
use think\Model;

/**
 * 对话记录
 * Class ChatServiceDialogueRecord
 * @package app\models\chat
 */
class ChatServiceDialogueRecord extends BaseModel
{
    use ModelTrait;

    /**
     * 消息来源：人工坐席
     */
    const SOURCE_HUMAN = 0;

    /**
     * 消息来源：关键词自动回复
     */
    const SOURCE_KEYWORD = 1;

    /**
     * 消息来源：AI正文回复
     */
    const SOURCE_AI = 2;

    /**
     * 消息来源：AI兜底话术（调用失败/不支持的消息类型）
     */
    const SOURCE_AI_FALLBACK = 3;

    /**
     * 消息来源：配额或限频降级话术
     */
    const SOURCE_AI_LIMITED = 4;

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'chat_service_dialogue_record';

    public function getAddTimeAttr($value)
    {
        return $value ? date('Y-m-d H:i:s', $value) : '';
    }

    /**
     * @param $value
     * @return mixed|object
     */
    public function getOtherAttr($value)
    {
        return $value ? json_decode($value, true) : (object)[];
    }

    /**
     * 一对一关联
     * @return mixed
     */
    public function service()
    {
        return $this->hasOne(ChatService::class, 'uid', 'uid')->field(['uid', 'nickname', 'avatar'])->bind([
            'nickname' => 'nickname',
            'avatar' => 'avatar'
        ]);
    }

    /**
     * 客服用户关联
     * @return \think\model\relation\HasOne
     */
    public function user()
    {
        return $this->hasOne(ChatUser::class, 'id', 'to_user_id')->field(['id', 'nickname', 'remark_nickname']);
    }

    /**
     * @return \think\model\relation\HasOne
     */
    public function userThis()
    {
        return $this->hasOne(ChatUser::class, 'id', 'user_id')
            ->field(['id', 'nickname', 'avatar']);
    }

    /**
     * @return \think\model\relation\HasOne
     */
    public function userTo()
    {
        return $this->hasOne(ChatUser::class, 'id', 'to_user_id')
            ->field(['id', 'nickname', 'avatar']);
    }

    /**
     * uid搜索器
     * @param Model $query
     * @param $value
     */
    public function searchUserIdAttr($query, $value)
    {
        $query->where('user_id|to_user_id', $value);
    }

    /**
     * 聊天记录搜索器
     * @param Model $query
     * @param $value
     */
    public function searchChatAttr($query, $value)
    {
        $query->whereIn('user_id', $value)->whereIn('to_user_id', $value);
    }

    /**
     * 某访客与本租户任一客服的往来
     *
     * 与 chat 的区别：chat 锁定一对一，这里的客服端是集合，
     * 用于把访客先后找过的多个客服（含AI）合并成一条时间线。
     * @param Model $query
     * @param array $value [访客id, 客服id数组]
     */
    public function searchVisitorAttr($query, $value)
    {
        [$visitorId, $agentIds] = $value;
        $visitorId = (int)$visitorId;
        $agentIds = array_map('intval', (array)$agentIds);
        if (!$agentIds) {
            $query->whereRaw('1=0');
            return;
        }
        $query->where(function ($q) use ($visitorId, $agentIds) {
            $q->where(function ($sub) use ($visitorId, $agentIds) {
                $sub->where('user_id', $visitorId)->whereIn('to_user_id', $agentIds);
            })->whereOr(function ($sub) use ($visitorId, $agentIds) {
                $sub->where('to_user_id', $visitorId)->whereIn('user_id', $agentIds);
            });
        });
    }

    /**
     * @param Model $query
     * @param $value
     */
    public function searchTypeAttr($query, $value)
    {
        $query->where('type', $value);
    }

    /**
     * @param Model $query
     * @param $value
     */
    public function searchIsTouristAttr($query, $value)
    {
        $query->where('is_tourist', $value);
    }
}

