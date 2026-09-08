<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\chat;

use app\dao\chat\ChatAutoReplyDao;
use crmeb\basic\BaseServices;
use crmeb\exceptions\AdminException;

/**
 * 常见问题
 *
 * 访客进入新会话时弹卡片，点问题直接得到答案。
 *
 * 数据复用 eb_chat_auto_reply：它本就是「问题→答案」存储且已接入收消息
 * 链路，只是后台一直没有配置页面、表里 0 行。同一份数据既供点卡片也供
 * 打字命中关键词，避免两处维护、答案打架。
 *
 * 与既有自动回复的分工靠 user_id：
 *   user_id > 0  客服私有的关键词回复（既有语义，不动）
 *   user_id = 0  全站通用，FAQ 卡片只取这类
 * 因为卡片是站点级导航，不该因为访客被分配到哪个客服而不同。
 * Class ChatFaqServices
 * @package app\services\chat
 */
class ChatFaqServices extends BaseServices
{
    /**
     * 全站通用的归属标记，区别于客服私有的关键词回复
     */
    const SCOPE_GLOBAL = 0;

    /**
     * 卡片最多展示的问题数
     *
     * 卡片是引导而非目录，多了访客反而不看；也避免首屏被撑开。
     */
    const CARD_LIMIT = 8;

    /**
     * 问题标题长度上限，与 varchar(255) 对齐
     */
    const TITLE_MAX = 255;

    /**
     * ChatFaqServices constructor.
     * @param ChatAutoReplyDao $dao
     */
    public function __construct(ChatAutoReplyDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 访客端卡片：只出全站通用且已勾选上卡的问题
     * @param string $appid
     * @return array
     */
    public function getCardList(string $appid): array
    {
        $list = $this->dao->faqQuery($appid)
            ->where('is_faq', 1)
            ->order('sort DESC, id ASC')
            ->limit(self::CARD_LIMIT)
            ->field('id,title')
            ->select();
        $list = is_object($list) ? $list->toArray() : (array)$list;
        //标题为空的行是从既有关键词回复升级来的，卡片上无从展示，直接滤掉
        return array_values(array_filter($list, function ($item) {
            return trim((string)$item['title']) !== '';
        }));
    }

    /**
     * 按 id 直取答案
     *
     * 不走关键词匹配：匹配未必命中，且整条自动回复链路受
     * 客服个人的 auto_reply 开关约束；卡片点击是访客的明确意图，不该被
     * 这两者影响可用性。
     * @param int $id
     * @param string $appid
     * @return array 命中返回 ['title'=>, 'content'=>]，未命中返回 []
     */
    public function getAnswer(int $id, string $appid): array
    {
        if ($id <= 0) {
            return [];
        }
        $row = $this->dao->faqQuery($appid)
            ->where('id', $id)
            ->where('is_faq', 1)
            ->field('id,title,content')
            ->find();
        if (!$row) {
            return [];
        }
        $row = $row->toArray();
        $content = trim((string)$row['content']);
        if ($content === '') {
            return [];
        }
        return ['title' => (string)$row['title'], 'content' => $content];
    }

    /**
     * 后台列表
     * @param array $where appid/page/limit
     * @return array
     */
    public function getAdminList(array $where): array
    {
        $query = $this->dao->faqQuery((string)$where['appid']);
        $count = (clone $query)->count();
        $list = $query->order('sort DESC, id ASC')
            ->page(max(1, (int)($where['page'] ?? 1)), max(1, (int)($where['limit'] ?? 20)))
            ->field('id,title,keyword,content,is_faq,sort')
            ->select();
        return [
            'list' => is_object($list) ? $list->toArray() : (array)$list,
            'count' => $count,
        ];
    }

    /**
     * 新增/编辑
     * @param int $id 0 表示新增
     * @param array $data title/keyword/content/is_faq/sort
     * @param string $appid
     * @return bool
     */
    public function saveFaq(int $id, array $data, string $appid): bool
    {
        $save = $this->buildSaveData($data, $appid);
        if ($id > 0) {
            //限定在全站通用范围内更新，避免越权改到客服私有的关键词回复
            $exists = $this->dao->faqQuery($appid)->where('id', $id)->count();
            if (!$exists) {
                throw new AdminException('常见问题不存在');
            }
            unset($save['add_time']);
            return false !== $this->dao->update($id, $save);
        }
        //必须走 create()：Query::insert() 不触发 onBeforeInsert，tenant_id 会漏填
        return false !== $this->dao->save($save);
    }

    /**
     * 删除
     * @param int $id
     * @param string $appid
     * @return bool
     */
    public function deleteFaq(int $id, string $appid): bool
    {
        $rows = $this->dao->faqQuery($appid)->where('id', $id)->delete();
        if (!$rows) {
            throw new AdminException('常见问题不存在');
        }
        return true;
    }

    /**
     * 批量排序
     * @param array $ids 按展示先后排列的ID
     * @param string $appid
     * @return bool
     */
    public function updateSort(array $ids, string $appid): bool
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return true;
        }
        //倒序赋值，使数组首位排最前（列表按 sort DESC）
        $sort = count($ids);
        foreach ($ids as $id) {
            $this->dao->faqQuery($appid)->where('id', $id)->update(['sort' => $sort]);
            $sort--;
        }
        return true;
    }

    /**
     * 组装入库字段并校验
     * @param array $data
     * @param string $appid
     * @return array
     */
    protected function buildSaveData(array $data, string $appid): array
    {
        $title = trim((string)($data['title'] ?? ''));
        $content = trim((string)($data['content'] ?? ''));
        if ($title === '') {
            throw new AdminException('请填写问题');
        }
        if (mb_strlen($title) > self::TITLE_MAX) {
            throw new AdminException('问题不能超过' . self::TITLE_MAX . '个字');
        }
        if ($content === '') {
            throw new AdminException('请填写答案');
        }
        //关键词留空时用问题兜底，保证访客打字也能命中同一条答案
        $keyword = trim((string)($data['keyword'] ?? ''));
        return [
            'title' => $title,
            'keyword' => $keyword !== '' ? $keyword : $title,
            'content' => $content,
            'is_faq' => (int)($data['is_faq'] ?? 1) ? 1 : 0,
            'sort' => (int)($data['sort'] ?? 0),
            'user_id' => self::SCOPE_GLOBAL,
            'appid' => $appid,
            'add_time' => time(),
        ];
    }
}
