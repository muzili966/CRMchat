<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\models;

use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;
use think\Model;

/**
 * 敏感词模型
 *
 * tenant_id=0 为平台级合规词，对所有租户生效；>0 为租户自定义业务词。
 * 因需同时读取平台级与本租户的词，查询由 Dao 显式给条件，不走租户全局Scope。
 * Class SensitiveWord
 * @package app\models
 */
class SensitiveWord extends BaseModel
{
    use ModelTrait;

    /**
     * 平台级词库的租户标识
     */
    const PLATFORM_TENANT = 0;

    const STATUS_OFF = 0;

    const STATUS_ON = 1;

    /**
     * 单次批量导入的词条上限
     */
    const IMPORT_MAX = 500;

    /**
     * 词条长度上限，与表结构一致
     */
    const WORD_MAX_LEN = 64;

    protected $name = 'sensitive_word';

    protected $pk = 'id';

    //时间字段一律存整型时间戳
    protected $autoWriteTimestamp = false;

    /**
     * 平台与租户词库要分别管理，隔离条件由调用方显式给出
     * @var bool
     */
    protected $tenantScoped = false;

    /**
     * @param Model $query
     * @param $value
     */
    public function searchTenantIdAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('tenant_id', (int)$value);
        }
    }

    /**
     * @param Model $query
     * @param $value
     */
    public function searchStatusAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', (int)$value);
        }
    }

    /**
     * @param Model $query
     * @param $value
     */
    public function searchCategoryAttr($query, $value)
    {
        if ($value) {
            $query->where('category', $value);
        }
    }

    /**
     * @param Model $query
     * @param $value
     */
    public function searchKeywordAttr($query, $value)
    {
        if ($value) {
            $query->where('word', 'like', '%' . $value . '%');
        }
    }
}
