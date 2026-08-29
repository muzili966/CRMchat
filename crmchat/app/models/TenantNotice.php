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

namespace app\models;


use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;
use think\Model;

/**
 * 租户通知模型
 * Class TenantNotice
 * @package app\models
 */
class TenantNotice extends BaseModel
{
    use ModelTrait;

    /**
     * 通知类型：即将到期
     */
    const TYPE_EXPIRE_WARN = 'expire_warn';

    /**
     * 通知类型：已到期
     */
    const TYPE_EXPIRED = 'expired';

    /**
     * 通知类型：订购/续费成功
     */
    const TYPE_RENEW = 'renew';

    /**
     * 未读
     */
    const UNREAD = 0;

    /**
     * 已读
     */
    const READ = 1;

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'tenant_notice';

    /**
     * 是否已读搜索器
     * @param Model $query
     * @param $value
     */
    public function searchIsReadAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('is_read', $value);
        }
    }

    /**
     * 通知类型搜索器
     * @param Model $query
     * @param $value
     */
    public function searchTypeAttr($query, $value)
    {
        if ($value) {
            $query->where('type', $value);
        }
    }
}
