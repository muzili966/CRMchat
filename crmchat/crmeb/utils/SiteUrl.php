<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\utils;

use think\facade\Env;

/**
 * 对外地址
 *
 * 这三个地址属于部署拓扑：随环境走、不随租户走、一处填错整批接入代码就失效，
 * 所以放在 .env 里跟数据库凭据一个层级，而不是后台可改的配置项。
 * Class SiteUrl
 * @package crmeb\utils
 */
class SiteUrl
{
    /**
     * 客服服务对外地址：接入代码里的脚本地址与会话窗口地址
     */
    const ENV_SERVICE = 'SERVICE_URL';

    /**
     * 管理控制台地址
     */
    const ENV_CONSOLE = 'CONSOLE_URL';

    /**
     * 官网地址
     */
    const ENV_WEBSITE = 'WEBSITE_URL';

    /**
     * 客服服务地址
     *
     * 接入方把代码贴在自己站点上，脚本地址必须是公网可达的本服务地址；
     * 取 location.origin 拿到的是接入方自己的域名，取后台地址则可能是内网。
     * 未配置时退回 site_url，仍为空则由调用方决定是否走相对路径。
     * @return string
     */
    public static function service(): string
    {
        return self::read(self::ENV_SERVICE) ?: self::siteUrl();
    }

    /**
     * 控制台地址，未配置时与服务地址同源
     * @return string
     */
    public static function console(): string
    {
        return self::read(self::ENV_CONSOLE) ?: self::service();
    }

    /**
     * 官网地址，未配置表示未启用，相关入口不展示
     * @return string
     */
    public static function website(): string
    {
        return self::read(self::ENV_WEBSITE);
    }

    /**
     * 同主机时返回空串（走相对路径），跨主机返回绝对地址
     *
     * 页面挂在 https 域名而配置里还是 http 内网地址时，绝对地址会被浏览器
     * 当混合内容拦掉，相对路径反而是对的。
     * @param string $url
     * @param string $requestHost
     * @return string
     */
    public static function relativeIfSameHost(string $url, string $requestHost): string
    {
        $url = rtrim($url, '/');
        if (!$url) {
            return '';
        }
        $host = parse_url($url, PHP_URL_HOST);
        return $host && strcasecmp((string)$host, $requestHost) !== 0 ? $url : '';
    }

    /**
     * @param string $key
     * @return string
     */
    protected static function read(string $key): string
    {
        return rtrim(trim((string)Env::get($key, '')), '/');
    }

    /**
     * @return string
     */
    protected static function siteUrl(): string
    {
        return rtrim(trim((string)sys_config('site_url')), '/');
    }
}
