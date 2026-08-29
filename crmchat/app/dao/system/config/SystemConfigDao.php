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

namespace app\dao\system\config;

use crmeb\basic\BaseDao;
use app\models\system\config\SystemConfig;

/**
 * 系统配置
 * Class SystemConfigDao
 * @package app\dao\system\config
 */
class SystemConfigDao extends BaseDao
{
    /**
     * 设置模型
     * @return string
     */
    protected function setModel(): string
    {
        return SystemConfig::class;
    }

    /**
     * 获取某个系统配置（平台默认层）
     * @param string $configNmae
     * @return mixed
     */
    public function getConfigValue(string $configNmae)
    {
        return $this->withSearchSelect(['menu_name'], ['menu_name' => $configNmae])->where('tenant_id', 0)->value('value');
    }

    /**
     * 获取所有配置（平台默认层）
     * @return array
     */
    public function getConfigAll(array $configName = [])
    {
        if ($configName) {
            return $this->withSearchSelect(['menu_name'], ['menu_name' => $configName])->where('tenant_id', 0)->column('value', 'menu_name');
        } else {
            return $this->getModel()->where('tenant_id', 0)->column('value', 'menu_name');
        }
    }

    /**
     * 获取租户覆盖层的配置值映射
     * @param array $configName
     * @param int $tenantId
     * @return array
     */
    public function getTenantValueMap(array $configName, int $tenantId)
    {
        return $this->getModel()->where('tenant_id', $tenantId)
            ->whereIn('menu_name', $configName)
            ->column('value', 'menu_name');
    }

    /**
     * 获取租户覆盖行
     * @param string $configName
     * @param int $tenantId
     * @return \think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getTenantRow(string $configName, int $tenantId)
    {
        return $this->getModel()->where('menu_name', $configName)->where('tenant_id', $tenantId)->find();
    }

    /**
     * 更新平台默认层的配置值
     * @param string $configName
     * @param string $value json编码后的值
     * @return mixed
     */
    public function updatePlatformValue(string $configName, string $value)
    {
        return $this->getModel()->where('menu_name', $configName)->where('tenant_id', 0)->update(['value' => $value]);
    }

    /**
     * 获取配置列表分页
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getConfigList(array $where, int $page, int $limit)
    {
        return $this->search($where)->where('tenant_id', 0)->page($page, $limit)->order('sort desc,id desc')->select()->toArray();
    }

    /**
     * 获取某些分类配置下的配置列表
     * @param int $tabId
     * @param int $status
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getConfigTabAllList(int $tabId, int $status = 1)
    {
        $where['tab_id'] = $tabId;
        if ($status == 1) $where['status'] = $status;
        return $this->search($where)->where('tenant_id', 0)->order('sort desc')->select()->toArray();
    }

    /**
     * 获取上传配置中的上传类型
     * @param string $configName
     * @return array
     */
    public function getUploadTypeList(string $configName)
    {
        return $this->search(['menu_name' => $configName])->where('tenant_id', 0)->column('upload_type', 'type');
    }
}
