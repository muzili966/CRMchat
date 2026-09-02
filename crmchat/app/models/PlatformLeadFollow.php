<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\models;

use crmeb\basic\BaseModel;

/**
 * 线索跟进记录
 *
 * 每次跟进与阶段变更都留痕，线索详情按时间倒序展示，
 * 便于交接时快速了解这条线索经历过什么。
 * Class PlatformLeadFollow
 * @package app\models
 */
class PlatformLeadFollow extends BaseModel
{
    protected $name = 'platform_lead_follow';

    protected $pk = 'id';

    protected $tenantScoped = false;

    protected $autoWriteTimestamp = false;

    const MAX_CONTENT = 1000;

    public function searchLeadIdAttr($query, $value)
    {
        $query->where('lead_id', (int)$value);
    }
}
