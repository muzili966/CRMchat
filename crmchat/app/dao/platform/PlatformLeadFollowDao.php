<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\dao\platform;

use app\models\PlatformLeadFollow;
use crmeb\basic\BaseDao;

/**
 * Class PlatformLeadFollowDao
 * @package app\dao\platform
 */
class PlatformLeadFollowDao extends BaseDao
{
    protected function setModel(): string
    {
        return PlatformLeadFollow::class;
    }

    /**
     * 某条线索的跟进记录，最新在前
     * @param int $leadId
     * @return array
     */
    public function getByLead(int $leadId): array
    {
        return $this->search(['lead_id' => $leadId])->order('id DESC')->select()->toArray();
    }
}
