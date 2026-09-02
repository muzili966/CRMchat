<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\controller;

use app\dao\ApplicationDao;
use app\models\Tenant;
use app\services\platform\PlatformLeadServices;
use app\services\TenantPlanServices;
use crmeb\services\tenant\TenantContext;
use think\facade\Log;
use crmeb\services\CacheService;
use think\Request;

/**
 * 官网公开接口
 *
 * 面向未登录的公网访客，只提供合作意向提交这一个入口。
 * Class WebsiteController
 * @package app\controller
 */
class WebsiteController
{
    /**
     * 同一来源的提交间隔（秒）：表单在公网上裸奔，不限流会被灌垃圾数据
     */
    const SUBMIT_INTERVAL = 60;

    /**
     * @var Request
     */
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * 官网首页
     *
     * 官网可被解析到独立域名（如 www），届时控制台与客服都不再同源，
     * 故地址一律由服务端按 site_url 注入，而不是写死相对路径。
     * @return \think\Response
     */
    public function index()
    {
        return view('website/index', [
            'plans' => $this->pricing(),
            //去掉末尾斜杠，模板里统一拼 /admin/ 这类路径；留空则退回同源相对路径
            'app_url' => $this->appUrl(),
            'app_origin' => $this->appUrl(),
            'chat_token' => $this->chatToken(),
        ]);
    }

    /**
     * 在售套餐，取不到时官网其余部分照常展示
     * @return array
     */
    protected function pricing(): array
    {
        try {
            /** @var TenantPlanServices $planServices */
            $planServices = app()->make(TenantPlanServices::class);
            return $planServices->getPublicPricing();
        } catch (\Throwable $e) {
            Log::error('官网定价读取失败：' . $e->getMessage());
            return [];
        }
    }

    /**
     * 官网上指向应用的地址
     *
     * 同域时返回空串走相对路径：官网若挂在 https 的 www 上而 site_url 还是
     * http 的内网地址，绝对地址会被浏览器当混合内容拦掉。只有确实跨域
     * （官网被单独解析出去）才需要绝对地址。
     * @return string
     */
    protected function appUrl(): string
    {
        return self::resolveAppUrl((string)sys_config('site_url'), (string)$this->request->host(true));
    }

    /**
     * 同主机返回空串（相对路径），跨主机返回绝对地址
     * @param string $siteUrl
     * @param string $requestHost
     * @return string
     */
    public static function resolveAppUrl(string $siteUrl, string $requestHost): string
    {
        $siteUrl = rtrim($siteUrl, '/');
        if (!$siteUrl) {
            return '';
        }
        $siteHost = parse_url($siteUrl, PHP_URL_HOST);
        return $siteHost && strcasecmp((string)$siteHost, $requestHost) !== 0 ? $siteUrl : '';
    }

    /**
     * 官网自用的客服接入token
     *
     * 写死在模板里会跟着代码进到每个环境，而各环境的应用是各自建的，
     * token 对不上就表现为访客一律进留言页。改为按默认租户实时解析，
     * 解析不到则不渲染客服脚本，避免挂一个必然失败的入口。
     * @return string
     */
    protected function chatToken(): string
    {
        try {
            return TenantContext::withoutTenant(function () {
                /** @var ApplicationDao $dao */
                $dao = app()->make(ApplicationDao::class);
                return (string)$dao->value([
                    'tenant_id' => Tenant::DEFAULT_TENANT_ID,
                    'is_delete' => 0,
                ], 'token_md5');
            });
        } catch (\Throwable $e) {
            Log::error('官网客服token读取失败：' . $e->getMessage());
            return '';
        }
    }
    /**
     * 提交合作意向
     * @return \think\Response
     */
    public function lead()
    {
        //蜜罐字段：正常访客看不到也不会填，被填了基本可判定为脚本提交。
        //直接返回成功而不报错，让脚本以为得手，避免其换策略重试。
        if (trim((string)$this->request->post('website', '')) !== '') {
            return app('json')->success('提交成功，我们会尽快与您联系');
        }
        if (!$this->passRateLimit()) {
            return app('json')->fail('提交过于频繁，请稍后再试');
        }
        $data = $this->request->postMore([
            ['name', ''],
            ['company', ''],
            ['phone', ''],
            ['email', ''],
            ['scale', ''],
            ['intent_plan', ''],
            ['content', ''],
        ]);
        $data['source'] = \app\models\PlatformLead::SOURCE_WEBSITE;
        try {
            /** @var PlatformLeadServices $services */
            $services = app()->make(PlatformLeadServices::class);
            $services->createLead($data);
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
        return app('json')->success('提交成功，我们会尽快与您联系');
    }

    /**
     * 按IP限流，缓存不可用时放行
     *
     * 限流是防灌水的辅助手段，不该因为缓存故障就把正常客户的意向挡在门外
     * @return bool
     */
    protected function passRateLimit(): bool
    {
        try {
            $key = 'website_lead:' . md5((string)$this->request->ip());
            $redis = CacheService::redisHandler();
            if ($redis->get($key)) {
                return false;
            }
            $redis->set($key, 1, self::SUBMIT_INTERVAL);
        } catch (\Throwable $e) {
            return true;
        }
        return true;
    }
}
