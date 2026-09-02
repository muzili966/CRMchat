<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\models;

use crmeb\basic\BaseModel;

/**
 * 平台销售线索
 *
 * 这是平台自己的客户（潜在租户），不属于任何租户，故不受租户Scope约束。
 * Class PlatformLead
 * @package app\models
 */
class PlatformLead extends BaseModel
{
    protected $name = 'platform_lead';

    protected $pk = 'id';

    //平台自身数据，不按租户隔离
    protected $tenantScoped = false;

    //表用int存时间戳，全局的timestamp写入方式会写成日期字符串
    protected $autoWriteTimestamp = false;

    /** 新线索：刚进来还没人碰 */
    const STAGE_NEW = 1;

    /** 已联系：接触过但意向未明 */
    const STAGE_CONTACTED = 2;

    /** 意向确认：明确有采购意向，进入方案与报价 */
    const STAGE_INTENT = 3;

    /** 已成交：已开通租户 */
    const STAGE_WON = 4;

    /** 已关闭：明确不成交或长期无响应 */
    const STAGE_CLOSED = 5;

    const STAGES = [
        self::STAGE_NEW => '新线索',
        self::STAGE_CONTACTED => '已联系',
        self::STAGE_INTENT => '意向确认',
        self::STAGE_WON => '已成交',
        self::STAGE_CLOSED => '已关闭',
    ];

    /** 已成交与已关闭视为流程终点，不再计入待跟进 */
    const CLOSED_STAGES = [self::STAGE_WON, self::STAGE_CLOSED];

    /** 来源：官网表单 */
    const SOURCE_WEBSITE = 'website';

    /** 来源：官网客服会话中转化 */
    const SOURCE_CHAT = 'chat';

    /** 来源：手工录入 */
    const SOURCE_MANUAL = 'manual';

    const SOURCES = [
        self::SOURCE_WEBSITE => '官网表单',
        self::SOURCE_CHAT => '客服会话',
        self::SOURCE_MANUAL => '手工录入',
    ];

    const MAX_CONTENT = 1000;

    public function searchStageAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('stage', (int)$value);
        }
    }

    public function searchOwnerIdAttr($query, $value)
    {
        if ($value) {
            $query->where('owner_id', (int)$value);
        }
    }

    public function searchSourceAttr($query, $value)
    {
        if ($value) {
            $query->where('source', $value);
        }
    }

    public function searchKeywordAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where(function ($q) use ($value) {
                $q->whereLike('name', '%' . $value . '%')
                    ->whereOr('company', 'like', '%' . $value . '%')
                    ->whereOr('phone', 'like', '%' . $value . '%');
            });
        }
    }

    public function searchIsDeleteAttr($query, $value)
    {
        $query->where('is_delete', (int)$value);
    }
}
