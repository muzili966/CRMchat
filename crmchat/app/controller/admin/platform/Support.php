<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\controller\admin\platform;

use app\controller\admin\AuthController;
use app\services\platform\PlatformSupportServices;

/**
 * 平台客服入口
 *
 * 租户后台的悬浮入口靠它取会话窗口地址，返回内容仅够渲染这个入口。
 * Class Support
 * @package app\controller\admin\platform
 */
class Support extends AuthController
{
    /**
     * @param PlatformSupportServices $services
     */
    public function __construct(PlatformSupportServices $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    /**
     * 入口配置
     * @return mixed
     */
    public function entry()
    {
        return $this->success($this->services->entry());
    }
}
