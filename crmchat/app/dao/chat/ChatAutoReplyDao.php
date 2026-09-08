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

namespace app\dao\chat;


use app\models\chat\ChatAutoReply;
use crmeb\basic\BaseDao;
use crmeb\utils\KeywordMatcher;

/**
 * Class ChatAutoReplyDao
 * @package app\dao\chat
 */
class ChatAutoReplyDao extends BaseDao
{
    /**
     * @return string
     */
    protected function setModel(): string
    {
        return ChatAutoReply::class;
    }

    /**
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getReply(array $where, int $page, int $limit)
    {
        return $this->search($where)->page($page, $limit)->select()->toArray();
    }

    /**
     * 获取回复列表
     * @param array $where
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    /**
     * 常见问题的查询起点（全站通用条目）
     *
     * 不走 search()：那是模型搜索器机制，只对定义了 searchXxxAttr 的字段生效，
     * is_faq / id 没有搜索器会被静默忽略，条件丢了却查得到数据。这里显式写条件。
     * @param string $appid
     * @return \think\db\Query
     */
    public function faqQuery(string $appid)
    {
        return $this->getModel()
            ->where('appid', $appid)
            ->where('user_id', \app\services\chat\ChatFaqServices::SCOPE_GLOBAL);
    }

    /**
     * 单次参与匹配的候选条数上限
     *
     * 匹配在 PHP 里做（见 KeywordMatcher），SQL 无法按「关键词出现在消息中」筛选，
     * 只能先把候选取回来。关键词回复是运营手工维护的，量级在几十到几百条，
     * 这个上限足够覆盖；超出部分按 sort 截断，与列表页展示优先级一致。
     */
    const CANDIDATE_LIMIT = 500;

    /**
     * 返回的匹配结果条数，与改造前保持一致
     */
    const REPLY_LIMIT = 5;

    public function getReplyList(array $where)
    {
        $message = trim((string)($where['message'] ?? ''));
        if ($message === '') {
            return [];
        }
        $rows = $this->getModel()->when(isset($where['appid']), function ($query) use ($where) {
            $query->where('appid', $where['appid']);
        })->when(isset($where['user_id']), function ($query) use ($where) {
            //0 是全站通用（常见问题），与客服私有的关键词回复一并参与匹配，
            //否则会出现「点卡片有答案、打同样的字没答案」的不一致
            $query->whereIn('user_id', [\app\services\chat\ChatFaqServices::SCOPE_GLOBAL, (int)$where['user_id']]);
        })->limit(self::CANDIDATE_LIMIT)->order('sort desc,id desc')->select()->toArray();

        return KeywordMatcher::match($message, $rows, self::REPLY_LIMIT);
    }
}
