<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\dao;

use app\models\SensitiveWord;
use crmeb\basic\BaseDao;

/**
 * 敏感词dao
 * Class SensitiveWordDao
 * @package app\dao
 */
class SensitiveWordDao extends BaseDao
{
    /**
     * @return string
     */
    protected function setModel(): string
    {
        return SensitiveWord::class;
    }

    /**
     * 词条列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getWordList(array $where, int $page = 0, int $limit = 0): array
    {
        return $this->search($where)->when($page && $limit, function ($query) use ($page, $limit) {
            $query->page($page, $limit);
        })->order('id DESC')->select()->toArray();
    }

    /**
     * 构建匹配树所需的启用词：平台级 + 指定租户
     *
     * 一次查两级，避免热路径上查两遍。
     * @param int $tenantId
     * @return array
     */
    public function activeWords(int $tenantId): array
    {
        return $this->getModel()
            ->whereIn('tenant_id', array_unique([SensitiveWord::PLATFORM_TENANT, $tenantId]))
            ->where('status', SensitiveWord::STATUS_ON)
            ->field('id,tenant_id,word,category,action,scope')
            ->select()->toArray();
    }

    /**
     * 已存在的词，用于导入时判重
     * @param int $tenantId
     * @param array $words
     * @return array
     */
    public function existingWords(int $tenantId, array $words): array
    {
        if (!$words) {
            return [];
        }
        return $this->getModel()
            ->where('tenant_id', $tenantId)
            ->whereIn('word', $words)
            ->column('word');
    }
}
