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

namespace crmeb\services\tenant;


/**
 * 租户隔离作用域判定
 * Class TenantScope
 * @package crmeb\services\tenant
 */
class TenantScope
{
    /**
     * 租户隔离字段名
     */
    const FIELD = 'tenant_id';

    /**
     * 判定模型当前是否需要施加租户隔离
     * @param mixed $model
     * @return bool
     */
    public static function applies($model): bool
    {
        if (TenantContext::isBypass()) {
            return false;
        }
        return method_exists($model, 'isTenantScoped') && $model->isTenantScoped();
    }

    /**
     * token租户声明与账号当前归属是否不一致
     * 旧token无租户声明（null）时兼容放行
     * @param mixed $claim token中的租户声明
     * @param int $actual 账号当前归属租户
     * @return bool true=不一致，应拒绝
     */
    public static function tokenTenantMismatch($claim, int $actual): bool
    {
        return !is_null($claim) && (int)$claim !== $actual;
    }

    /**
     * 附件上传目录按租户分目录，平台上下文保持原目录
     * @param string $dir
     * @return string
     */
    public static function uploadDir(string $dir): string
    {
        $tenantId = (int)(TenantContext::get() ?: 0);
        if ($tenantId > 0) {
            return 'tenant/' . $tenantId . '/' . ltrim($dir, '/');
        }
        return $dir;
    }

    /**
     * 批量写入数据时补充租户字段（insertAll 不触发模型事件，需显式填充）
     * @param mixed $model
     * @param array $rows
     * @return array
     */
    public static function fillRows($model, array $rows): array
    {
        if (!self::applies($model)) {
            return $rows;
        }
        $tenantId = TenantContext::must();
        foreach ($rows as &$row) {
            if (is_array($row) && empty($row[self::FIELD])) {
                $row[self::FIELD] = $tenantId;
            }
        }
        return $rows;
    }
}
