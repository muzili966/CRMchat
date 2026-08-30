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

namespace app\models\system\admin;

use crmeb\basic\BaseModel;
use crmeb\traits\JwtAuthModelTrait;
use crmeb\traits\ModelTrait;
use think\Model;

/**
 * 管理员模型
 * Class SystemAdmin
 * @package app\models\system\admin
 */
class SystemAdmin extends BaseModel
{
    use ModelTrait;
    use JwtAuthModelTrait;

    /**
     * 归属平台侧的管理员。admin_type仅表达归属边界（数据可见范围），
     * 权限大小仍由level/roles决定：平台侧同样可以有仅授权部分菜单的普通运营人员
     */
    const TYPE_PLATFORM = 1;

    /**
     * 归属租户侧的管理员，数据访问锁定在所属租户内
     */
    const TYPE_TENANT = 2;

    /**
     * 租户管理员默认级别（非0，走角色权限校验）
     */
    const TENANT_ADMIN_LEVEL = 1;

    /**
     * 正常状态
     */
    const STATUS_NORMAL = 1;

    /**
     * 切换租户视角的虚拟权限点（对应system_menus中auth_type=2的权限行，
     * 同时也是租户下拉数据接口的真实路由；level=0免校验，其余需角色授予）
     */
    const VIEW_SWITCH_AUTH = 'api/admin/setting/tenant/view_switch';

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'system_admin';

    /**
     * 受租户隔离约束
     * @var bool
     */
    protected $tenantScoped = true;

    protected $insert = ['add_time'];

    /**
     * 权限数据
     * @param $value
     * @return false|string[]
     */
    public static function getRolesAttr($value)
    {
        return explode(',', $value);
    }

    /**
     * 是否平台管理员；admin_type缺失时按租户管理员处理（默认最小权限）
     * @param array|\ArrayAccess $adminInfo
     * @return bool
     */
    public static function isPlatformAdmin($adminInfo): bool
    {
        return ($adminInfo['admin_type'] ?? self::TYPE_TENANT) == self::TYPE_PLATFORM;
    }

    /**
     * 管理员级别搜索器
     * @param Model $query
     * @param $value
     * @param $data
     */
    public function searchLevelAttr($query, $value)
    {
        if (is_array($value)) {
            $query->where('level', $value[0], $value[1]);
        } else {
            $query->where('level', $value);
        }
    }

    /**
     * 管理员账号和姓名搜索器
     * @param Model $query
     * @param $value
     */
    public function searchAccountLikeAttr($query, $value)
    {
        if ($value) {
            $query->whereLike('account|real_name', '%' . $value . '%');
        }
    }

    /**
     * 管理员账号搜索器
     * @param Model $query
     * @param $value
     */
    public function searchAccountAttr($query, $value)
    {
        if ($value) {
            $query->where('account', $value);
        }
    }

    /**
     * 管理员权限搜索器
     * @param Model $query
     * @param $roles
     */
    public function searchRolesAttr($query, $roles)
    {
        if ($roles) {
            $query->where("CONCAT(',',roles,',')  LIKE '%,$roles,%'");
        }
    }

    /**
     * 是否删除搜索器
     * @param Model $query
     * @param $value
     */
    public function searchIsDelAttr($query)
    {
        $query->where('is_del', 0);
    }

    /**
     * 状态搜索器
     * @param Model $query
     * @param $value
     */
    public function searchStatusAttr($query, $value)
    {
        if ($value != '' && $value != null) {
            $query->where('status', $value);
        }
    }

    /**
     * 租户搜索器
     * @param Model $query
     * @param $value
     */
    public function searchTenantIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('tenant_id', $value);
        }
    }

    /**
     * 管理员类型搜索器
     * @param Model $query
     * @param $value
     */
    public function searchAdminTypeAttr($query, $value)
    {
        if ($value) {
            $query->where('admin_type', $value);
        }
    }

}
