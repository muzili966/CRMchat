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


use app\models\ApplicationTheme;
use crmeb\basic\BaseDao;

/**
 * 客户端装修配置dao
 * Class ApplicationThemeDao
 * @package app\dao
 */
class ApplicationThemeDao extends BaseDao
{

    /**
     * @return string
     */
    protected function setModel(): string
    {
        return ApplicationTheme::class;
    }

    /**
     * 获取应用的装修配置（一应用一行）
     * @param string $appid
     * @return array|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getByAppid(string $appid)
    {
        return $this->get(['appid' => $appid]);
    }
}
