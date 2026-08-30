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

namespace app\dao;


use app\models\AiConfig;
use crmeb\basic\BaseDao;

/**
 * AI客服配置dao
 * Class AiConfigDao
 * @package app\dao
 */
class AiConfigDao extends BaseDao
{

    /**
     * @return string
     */
    protected function setModel(): string
    {
        return AiConfig::class;
    }

    /**
     * 获取租户的AI配置（一租户一行）
     * @param int $tenantId
     * @return array|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getByTenantId(int $tenantId)
    {
        return $this->get(['tenant_id' => $tenantId]);
    }
}
