<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\dao;

use app\models\ChatSession;
use crmeb\basic\BaseDao;

/**
 * 客服会话dao
 * Class ChatSessionDao
 * @package app\dao
 */
class ChatSessionDao extends BaseDao
{
    /**
     * @return string
     */
    protected function setModel(): string
    {
        return ChatSession::class;
    }

    /**
     * 会话列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getSessionList(array $where, int $page = 0, int $limit = 0): array
    {
        return $this->search($where)->when($page && $limit, function ($query) use ($page, $limit) {
            $query->page($page, $limit);
        })->order('id DESC')->select()->toArray();
    }
}
