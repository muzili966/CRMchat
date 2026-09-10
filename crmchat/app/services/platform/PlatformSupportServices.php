<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\platform;

use app\models\Tenant;
use app\services\ApplicationThemeServices;
use crmeb\basic\BaseServices;
use crmeb\services\tenant\TenantContext;
use crmeb\utils\SiteUrl;
use think\facade\Db;

/**
 * 平台客服入口
 *
 * 租户遇到订阅、开票、功能问题时，原先只能翻合同找销售的微信。平台自己就在
 * 卖客服系统，官网早已用自营租户接待访客，这里把同一个会话窗口挂进租户后台，
 * 接待、转接、历史记录复用现成那套，不必另造工单系统。
 *
 * 与官网嵌入的区别是身份：官网来的是匿名访客，这里的人已经登录过后台，
 * 身份是确定的，所以按接入协议带 uid 与签名进去，直接落到该租户的固定档案上。
 * 这样租户换台电脑打开还是同一个会话，平台客服也不必每次都问「您是哪家」。
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
     * 租户在平台客服里的访客 uid 基数
     *
     * 与租户ID相加得到稳定 uid，避开官网访客的自增段，不会撞号。
     */
    const UID_BASE = 3000000;

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
        $tenant = TenantContext::withoutTenant(function () use ($tenantId) {
            return Db::name('tenant')->where('id', $tenantId)->field('name')->find();
        });
        //平台自营租户的应用：会话窗口凭它定位到平台的客服组
        $app = TenantContext::withoutTenant(function () {
            return Db::name('application')
                ->where(['tenant_id' => Tenant::DEFAULT_TENANT_ID, 'is_delete' => 0])
                ->order('id', 'asc')
                ->field('appid,app_secret,token_md5')
                ->find();
        });
        $origin = rtrim(SiteUrl::service(), '/');
        if (!$app || !$app['token_md5'] || !$origin) {
            //平台没建应用或未配置对外地址时，宁可不给入口，也不给一个点不开的按钮
            return $this->disabled();
        }
        //外观随平台自营租户的客户端装修走，与官网上那个入口是同一套配置
        $widget = app()->make(ApplicationThemeServices::class)->getWidgetConfig((string)$app['appid']);
        return [
            'enabled' => true,
            'url' => $origin . self::CHAT_PATH . '?' . http_build_query(
                $this->identity($app, $tenantId, (string)($tenant['name'] ?? ''))
            ),
            'icon' => $this->absoluteUrl((string)($widget['pcIcon'] ?? ''), $origin),
            'theme_color' => (string)($widget['themeColor'] ?? ''),
            'show_tip' => (int)($widget['showTip'] ?? 1),
        ];
    }

    /**
     * 图标补全为绝对地址
     *
     * 装修里存的是相对路径，而后台可能跑在另一个域名下，直接用会 404。
     * @param string $url
     * @param string $origin
     * @return string
     */
    protected function absoluteUrl(string $url, string $origin): string
    {
        if ($url === '' || strpos($url, 'http') === 0 || strpos($url, 'data:') === 0) {
            return $url;
        }
        return $origin . '/' . ltrim($url, '/');
    }

    /**
     * 会话窗口的接入参数：应用定位 + 已登录身份
     *
     * app_secret 只参与服务端算签，不出现在返回值里。
     * @param array $app 平台应用
     * @param int $tenantId
     * @param string $tenantName
     * @return array
     */
    protected function identity(array $app, int $tenantId, string $tenantName): array
    {
        $uid = self::UID_BASE + $tenantId;
        $timestamp = time();
        return [
            //与官网嵌入取同一个字段：token_md5 短、可公开，parseToken 按32位长度识别
            'token' => $app['token_md5'],
            'deviceType' => 'pc',
            'uid' => $uid,
            //接入脚本示例用的是驼峰，服务端 user 事件读的是全小写，两个都给
            'nickname' => $tenantName ?: ('租户' . $tenantId),
            'nickName' => $tenantName ?: ('租户' . $tenantId),
            //签名模式下缺它会被拒；兼容模式下服务端不校验，多带无副作用
            'timestamp' => $timestamp,
            'sign' => md5($app['appid'] . $uid . $timestamp . $app['app_secret']),
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
