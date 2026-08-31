<?php

namespace tests\unit;

use app\models\ApplicationTheme;
use app\services\ApplicationThemeServices;
use PHPUnit\Framework\TestCase;

/**
 * 装修字段的订阅门禁测试
 *
 * 门禁从整页下沉到字段后，免费租户也能提交表单；此时前端不展示的受限字段
 * 会带着默认空值一起提交，必须沿用已存值，否则一次保存就会把付费期间配好的
 * LOGO、广告与去水印设置全部抹掉。
 */
class ThemeFieldEntitlementTest extends TestCase
{
    /**
     * 全部能力都有时按提交值原样写入
     */
    public function testAllEntitledKeepsSubmittedValues()
    {
        $payload = $this->payload(['logo' => '/new.png', 'banners' => '[{"image":"/b.png"}]']);
        $result = ApplicationThemeServices::applyEntitlement($payload, $this->stored(), function () {
            return true;
        });
        $this->assertSame('/new.png', $result['logo']);
        $this->assertSame('[{"image":"/b.png"}]', $result['banners']);
    }

    /**
     * 无品牌能力时，LOGO与风格类字段沿用已存值而不是被空值覆盖
     */
    public function testBrandFieldsFallBackToStoredWhenNotEntitled()
    {
        $payload = $this->payload(['logo' => '', 'theme_style' => 'modern', 'bubble_style' => 'soft']);
        $result = ApplicationThemeServices::applyEntitlement($payload, $this->stored(), function ($feature) {
            return $feature !== ApplicationThemeServices::FEATURE_BRAND_CUSTOM;
        });
        $this->assertSame('/old-logo.png', $result['logo']);
        $this->assertSame('midnight', $result['theme_style']);
        $this->assertSame('card', $result['bubble_style']);
    }

    /**
     * 不设门槛的字段任何套餐都能改
     */
    public function testFreeFieldsAlwaysApplied()
    {
        $payload = $this->payload(['title' => '新标题', 'theme_color' => '#112233']);
        $result = ApplicationThemeServices::applyEntitlement($payload, $this->stored(), function () {
            return false;
        });
        $this->assertSame('新标题', $result['title']);
        $this->assertSame('#112233', $result['theme_color']);
    }

    public function testAdFieldsFallBackWhenNotEntitled()
    {
        $payload = $this->payload(['banners' => '[]', 'custom_html' => '']);
        $result = ApplicationThemeServices::applyEntitlement($payload, $this->stored(), function ($feature) {
            return $feature !== ApplicationThemeServices::FEATURE_CUSTOM_AD;
        });
        $this->assertSame('[{"image":"/old.png"}]', $result['banners']);
        $this->assertSame('<p>old</p>', $result['custom_html']);
    }

    /**
     * 曾付费隐藏了平台标识、后降级的租户，保存时不应被改回显示
     */
    public function testBrandFlagFallsBackWhenNotEntitled()
    {
        $payload = $this->payload(['show_platform_brand' => ApplicationTheme::BRAND_SHOW]);
        $result = ApplicationThemeServices::applyEntitlement($payload, $this->stored(), function ($feature) {
            return $feature !== ApplicationThemeServices::FEATURE_WHITE_LABEL;
        });
        $this->assertSame(ApplicationTheme::BRAND_HIDE, $result['show_platform_brand']);
    }

    /**
     * 从未配置过的应用没有已存值，回落到字段默认值而非报错
     */
    public function testMissingStoredFallsBackToDefault()
    {
        $payload = $this->payload(['logo' => '/x.png', 'banners' => '[{"image":"/y.png"}]']);
        $result = ApplicationThemeServices::applyEntitlement($payload, [], function () {
            return false;
        });
        $this->assertSame('', $result['logo']);
        $this->assertSame('[]', $result['banners']);
        $this->assertSame(ApplicationTheme::BRAND_SHOW, $result['show_platform_brand']);
    }

    /**
     * 每项能力只判定一次，避免逐字段重复查询套餐
     */
    public function testFeatureIsResolvedOncePerFeature()
    {
        $calls = [];
        ApplicationThemeServices::applyEntitlement($this->payload(), $this->stored(), function ($feature) use (&$calls) {
            $calls[] = $feature;
            return false;
        });
        $this->assertSame(count(array_unique($calls)), count($calls), '同一能力不应被重复判定');
    }

    protected function payload(array $override = []): array
    {
        return array_merge([
            'title' => '标题',
            'logo' => '/submit.png',
            'theme_color' => '#00b894',
            'theme_style' => 'modern',
            'bubble_style' => 'soft',
            'pc_icon' => '',
            'mobile_icon' => '',
            'banners' => '[]',
            'custom_html' => '',
            'show_platform_brand' => ApplicationTheme::BRAND_SHOW,
        ], $override);
    }

    protected function stored(): array
    {
        return [
            'logo' => '/old-logo.png',
            'theme_style' => 'midnight',
            'bubble_style' => 'card',
            'pc_icon' => '/old-pc.png',
            'mobile_icon' => '/old-mb.png',
            'banners' => '[{"image":"/old.png"}]',
            'custom_html' => '<p>old</p>',
            'show_platform_brand' => ApplicationTheme::BRAND_HIDE,
        ];
    }
}
