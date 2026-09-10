<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\platform;

use app\models\Tenant;
use crmeb\basic\BaseServices;
use crmeb\services\tenant\TenantContext;
use crmeb\utils\SiteUrl;
use think\facade\Db;

/**
 * 平台客服入口
 *
 * 租户遇到订阅、开票、功能问题时，原先只能翻合同找销售的微信。平台自己就在
 * 卖客服系统，没道理不用自己的：把平台自营租户的会话窗口挂进租户后台，租户
 * 点开就是一次普通接待，平台侧在客服工作台照常回，历史记录、转接、质检全都
 * 复用现成的那一套，不必另造一个工单系统。
 * Class PlatformSupportServices
 * @package app\services\platform
 */
class PlatformSupportServices extends BaseServices
{
    /**
     * 会话窗口的路由，与嵌入脚本 customerServer.js 保持一致
     */
    const CHAT_PATH = '/chat';

    /**
     * 入口配置
     *
     * 平台自营租户不展示：它是被联系的一方，给它挂个联系自己的按钮没有意义。
     * @return array
     */
    public function entry(): array
    {
        $tenantId = (int)TenantContext::id();
        if ($tenantId <= Tenant::DEFAULT_TENANT_ID) {
            return $this->disabled();
        }
        $tenantName = (string)TenantContext::withoutTenant(function () use ($tenantId) {
            return Db::name('tenant')->where('id', $tenantId)->value('name');
        });
        //与官网嵌入取同一个字段：token_md5 短、可公开，parseToken 按32位长度识别
        $token = (string)TenantContext::withoutTenant(function () {
            return Db::name('application')
                ->where(['tenant_id' => Tenant::DEFAULT_TENANT_ID, 'is_delete' => 0])
                ->order('id', 'asc')->value('token_md5');
        });
        $origin = rtrim(SiteUrl::service(), '/');
        if (!$token || !$origin) {
            //平台没建应用或未配置对外地址时，宁可不给入口，也不给一个点不开的按钮
            return $this->disabled();
        }
        return [
            'enabled' => true,
            'url' => $origin . self::CHAT_PATH . '?' . http_build_query([
                'token' => $token,
                'deviceType' => 'pc',
                //让平台客服一眼看出是哪家找来的，省掉每次都要问「您是哪个租户」
                'nickname' => $tenantName ?: ('租户' . $tenantId),
                'tenant_id' => $tenantId,
            ]),
        ];
    }

    /**
     * @return array
     */
    protected function disabled(): array
    {
        return ['enabled' => false, 'url' => ''];
    }
}
