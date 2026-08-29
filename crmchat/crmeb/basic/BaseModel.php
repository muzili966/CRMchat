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

namespace crmeb\basic;

use crmeb\services\tenant\TenantContext;
use crmeb\services\tenant\TenantScope;
use crmeb\traits\ModelTrait;
use think\Model;
use think\db\Query;

/**
 * Class BaseModel
 * @package crmeb\basic
 * @mixin ModelTrait
 * @mixin Query
 */
class BaseModel extends Model
{

    /**
     * 全局查询范围：租户隔离
     * @var array
     */
    protected $globalScope = ['tenant'];

    /**
     * 是否受租户隔离约束；默认约束（新模型默认隔离），
     * 平台级模型（菜单/配置分类/缓存/租户本体等）显式置为 false 豁免
     * @var bool
     */
    protected $tenantScoped = true;

    /**
     * 当前模型是否受租户隔离约束
     * @return bool
     */
    public function isTenantScoped(): bool
    {
        return (bool)$this->tenantScoped;
    }

    /**
     * 租户隔离全局查询范围，模型的所有查询/更新/删除自动附加租户条件
     * @param Query $query
     * @return void
     */
    public function scopeTenant($query)
    {
        if (TenantScope::applies($this)) {
            $query->where(TenantScope::FIELD, TenantContext::must());
        }
    }

    /**
     * 写入前自动填充租户字段，杜绝插入时漏填
     * @param Model $model
     * @return void
     */
    public static function onBeforeInsert($model)
    {
        if (!TenantScope::applies($model)) {
            return;
        }
        $data = $model->getData();
        if (empty($data[TenantScope::FIELD])) {
            $model->setAttr(TenantScope::FIELD, TenantContext::must());
        }
    }
}
