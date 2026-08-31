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

namespace app\controller\admin\chat;


use app\controller\admin\AuthController;
use app\dao\ApplicationDao;
use app\models\ApplicationTheme;
use app\services\ApplicationThemeServices;
use app\services\TenantPlanServices;
use app\services\TenantServices;
use crmeb\services\tenant\TenantContext;

/**
 * 客户端装修（租户视角）
 * Class AppTheme
 * @package app\controller\admin\chat
 */
class AppTheme extends AuthController
{

    /**
     * AppTheme constructor.
     * @param ApplicationThemeServices $services
     */
    public function __construct(ApplicationThemeServices $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    /**
     * 装修配置详情与套餐能力
     * @return mixed
     */
    public function index()
    {
        $where = $this->request->getMore([
            ['appid', ''],
        ]);
        $appid = (string)$where['appid'];
        if ($error = $this->checkAppOwner($appid)) {
            return $this->fail($error);
        }
        return $this->success([
            'theme' => $this->services->getTheme($appid),
            'plan' => $this->planFeatures(),
            'app' => ['name' => $this->appName($appid)],
        ]);
    }

    /**
     * 保存装修配置
     * @return mixed
     */
    public function save()
    {
        $data = $this->request->postMore([
            ['appid', ''],
            ['title', ''],
            ['logo', ''],
            ['theme_color', ApplicationTheme::DEFAULT_THEME_COLOR],
            ['theme_style', ApplicationTheme::DEFAULT_THEME_STYLE],
            ['bubble_style', ApplicationTheme::DEFAULT_BUBBLE_STYLE],
            ['pc_icon', ''],
            ['mobile_icon', ''],
            [['banners', 'a'], []],
            //富文本广告位，服务层会剥离脚本后入库
            ['custom_html', ''],
            [['show_platform_brand', 'd'], ApplicationTheme::BRAND_SHOW],
            [['tourist_avatar', 'a'], []],
            ['service_feedback', ''],
        ]);
        $appid = (string)$data['appid'];
        if ($error = $this->checkAppOwner($appid) ?: $this->checkPlan($data)) {
            return $this->fail($error);
        }
        $this->services->saveTheme($appid, $data);
        return $this->success('保存成功');
    }

    /**
     * 校验应用归属，返回空串表示通过
     * @param string $appid
     * @return string
     */
    protected function checkAppOwner(string $appid): string
    {
        if (!$appid) {
            return '请选择需要装修的应用';
        }
        $tenantId = TenantContext::id();
        if (!$tenantId) {
            return '平台账号请切换到租户视角后配置客户端装修';
        }
        /** @var TenantServices $tenantServices */
        $tenantServices = app()->make(TenantServices::class);
        $appTenantId = $tenantServices->tenantIdByAppid($appid);
        if (!$appTenantId) {
            return '所选应用不存在';
        }
        return $appTenantId == $tenantId ? '' : '所选应用不属于当前租户';
    }

    /**
     * 校验套餐能力，返回空串表示通过
     * @param array $data
     * @return string
     */
    protected function checkPlan(array $data): string
    {
        $planServices = $this->planServices();
        $tenantId = TenantContext::id();
        if (!$planServices->hasFeature($tenantId, ApplicationThemeServices::FEATURE_BRAND_CUSTOM)) {
            return '当前套餐不支持客户端装修，请升级套餐';
        }
        $hideBrand = (int)$data['show_platform_brand'] === ApplicationTheme::BRAND_HIDE;
        if ($hideBrand && !$planServices->hasFeature($tenantId, ApplicationThemeServices::FEATURE_WHITE_LABEL)) {
            return '当前套餐不支持隐藏平台标识，请升级套餐';
        }
        //未开通自定义广告的租户，访客端展示平台默认广告，此处直接拒绝写入避免"配了却不生效"
        $hasAd = !empty($data['banners']) || trim((string)($data['custom_html'] ?? '')) !== '';
        if ($hasAd && !$planServices->hasFeature($tenantId, ApplicationThemeServices::FEATURE_CUSTOM_AD)) {
            return '当前套餐不支持自定义广告位，升级后可投放自有广告';
        }
        return '';
    }

    /**
     * 当前租户的装修相关套餐能力
     * @return array
     */
    protected function planFeatures(): array
    {
        $planServices = $this->planServices();
        $tenantId = TenantContext::id();
        return [
            'brand_custom' => $planServices->hasFeature($tenantId, ApplicationThemeServices::FEATURE_BRAND_CUSTOM),
            'white_label' => $planServices->hasFeature($tenantId, ApplicationThemeServices::FEATURE_WHITE_LABEL),
            'custom_ad' => $planServices->hasFeature($tenantId, ApplicationThemeServices::FEATURE_CUSTOM_AD),
        ];
    }

    /**
     * 应用名称，作为窗口标题的缺省值
     * @param string $appid
     * @return string
     */
    protected function appName(string $appid): string
    {
        /** @var ApplicationDao $applicationDao */
        $applicationDao = app()->make(ApplicationDao::class);
        return (string)$applicationDao->value(['appid' => $appid], 'name');
    }

    /**
     * @return TenantPlanServices
     */
    protected function planServices(): TenantPlanServices
    {
        return app()->make(TenantPlanServices::class);
    }
}
