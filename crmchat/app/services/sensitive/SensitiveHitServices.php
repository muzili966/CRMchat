<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\sensitive;

use app\dao\SensitiveHitDao;
use app\models\SensitiveHit;
use crmeb\basic\BaseServices;
use crmeb\exceptions\AdminException;
use crmeb\utils\SensitiveFilter;

/**
 * 敏感词命中记录
 * Class SensitiveHitServices
 * @package app\services\sensitive
 */
class SensitiveHitServices extends BaseServices
{
    /**
     * 列表每页条数上限
     */
    const MAX_LIMIT = 100;

    const SCOPE_TEXT = [
        SensitiveFilter::SCOPE_VISITOR => '访客',
        SensitiveFilter::SCOPE_AGENT => '客服',
        SensitiveFilter::SCOPE_AI => 'AI',
    ];

    const ACTION_TEXT = [
        SensitiveFilter::ACTION_BLOCK => '已拦截',
        SensitiveFilter::ACTION_REPLACE => '已替换',
        SensitiveFilter::ACTION_WARN => '仅告警',
    ];

    /**
     * SensitiveHitServices constructor.
     * @param SensitiveHitDao $dao
     */
    public function __construct(SensitiveHitDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 命中记录列表
     * @param array $where
     * @return array
     */
    public function getList(array $where): array
    {
        $search = [
            'handled' => $where['handled'] ?? '',
            'scope' => $where['scope'] ?? '',
            'keyword' => $where['keyword'] ?? '',
            'timeBetween' => $this->timeRange($where),
        ];
        [$page, $limit] = $this->pageValue($where);
        return [
            'list' => array_map([$this, 'format'], $this->dao->getHitList($search, $page, $limit)),
            'count' => $this->dao->count($search),
        ];
    }

    /**
     * 标记为已处理
     * @param int $id
     * @return bool
     */
    public function handle(int $id): bool
    {
        if (!$this->dao->get($id)) {
            throw new AdminException('记录不存在');
        }
        $this->dao->update($id, ['handled' => SensitiveHit::HANDLED_YES]);
        return true;
    }

    /**
     * 待处理条数，供页面角标提示
     * @return int
     */
    public function pendingCount(): int
    {
        return $this->dao->count(['handled' => SensitiveHit::HANDLED_NO]);
    }

    /**
     * @param array $where
     * @return array
     */
    protected function timeRange(array $where): array
    {
        $start = (string)($where['start'] ?? '');
        $end = (string)($where['end'] ?? '');
        if (!$start || !$end) {
            return [];
        }
        return [strtotime($start . ' 00:00:00'), strtotime($end . ' 23:59:59')];
    }

    /**
     * @param array $hit
     * @return array
     */
    protected function format(array $hit): array
    {
        $hit['scope_text'] = self::SCOPE_TEXT[(int)$hit['scope']] ?? '未知';
        $hit['action_text'] = self::ACTION_TEXT[(int)$hit['action']] ?? '未知';
        $hit['_create_time'] = $hit['create_time'] ? date('Y-m-d H:i:s', (int)$hit['create_time']) : '';
        return $hit;
    }

    /**
     * @param array $where
     * @return array [page, limit]
     */
    protected function pageValue(array $where): array
    {
        $page = max(1, (int)($where['page'] ?? 1));
        $limit = (int)($where['limit'] ?? 20);
        $limit = $limit > 0 ? min($limit, self::MAX_LIMIT) : 20;
        return [$page, $limit];
    }
}
