<?php

namespace tests\unit;

use app\models\ApplicationTheme;
use app\services\ApplicationThemeServices;
use PHPUnit\Framework\TestCase;

/**
 * 客户端装修归一化测试
 *
 * 边界：轮播广告的json串/数组入参、缺图片与非数组脏数据、伪协议链接、同sort排序、超量截断，
 * 色值的大小写/非法/空值，以及默认装修的字段完整性。
 */
class ApplicationThemeTest extends TestCase
{
    public function testNormalizeBannersFromJsonString()
    {
        $json = json_encode([
            ['image' => '/a.png', 'link' => 'https://a.com', 'sort' => 2],
            ['image' => '/b.png', 'link' => '', 'sort' => 1],
        ]);
        $banners = ApplicationThemeServices::normalizeBanners($json);
        $this->assertCount(2, $banners);
        $this->assertSame('/b.png', $banners[0]['image']);
        $this->assertSame('https://a.com', $banners[1]['link']);
        $this->assertSame(1, $banners[0]['sort']);
    }

    public function testNormalizeBannersFromArray()
    {
        $banners = ApplicationThemeServices::normalizeBanners([
            ['image' => ' /a.png ', 'link' => ' http://a.com ', 'sort' => '3'],
        ]);
        $this->assertSame([['image' => '/a.png', 'link' => 'http://a.com', 'sort' => 3]], $banners);
    }

    public function testNormalizeBannersDropsDirtyRows()
    {
        $banners = ApplicationThemeServices::normalizeBanners([
            ['image' => '', 'link' => 'https://a.com'],
            ['link' => 'https://b.com'],
            'not-an-array',
            ['image' => '/c.png'],
        ]);
        $this->assertSame([['image' => '/c.png', 'link' => '', 'sort' => 0]], $banners);
    }

    public function testNormalizeBannersClearsUnsafeLink()
    {
        $banners = ApplicationThemeServices::normalizeBanners([
            ['image' => '/a.png', 'link' => 'javascript:alert(1)'],
            ['image' => '/b.png', 'link' => ['bad']],
        ]);
        $this->assertSame('', $banners[0]['link']);
        $this->assertSame('', $banners[1]['link']);
    }

    public function testNormalizeBannersInvalidPayloadBecomesEmpty()
    {
        $this->assertSame([], ApplicationThemeServices::normalizeBanners('not-json'));
        $this->assertSame([], ApplicationThemeServices::normalizeBanners(null));
        $this->assertSame([], ApplicationThemeServices::normalizeBanners(''));
    }

    public function testNormalizeBannersSortAscending()
    {
        $banners = ApplicationThemeServices::normalizeBanners([
            ['image' => '/c.png', 'sort' => 9],
            ['image' => '/a.png', 'sort' => -1],
            ['image' => '/b.png', 'sort' => 'x'],
        ]);
        $this->assertSame(['/a.png', '/b.png', '/c.png'], array_column($banners, 'image'));
    }

    public function testNormalizeBannersKeepsSubmitOrderOnSameSort()
    {
        $banners = ApplicationThemeServices::normalizeBanners([
            ['image' => '/a.png', 'sort' => 1],
            ['image' => '/b.png', 'sort' => 1],
            ['image' => '/c.png', 'sort' => 1],
        ]);
        $this->assertSame(['/a.png', '/b.png', '/c.png'], array_column($banners, 'image'));
    }

    public function testNormalizeBannersTruncatedToLimit()
    {
        $rows = [];
        for ($i = 0; $i <= ApplicationTheme::MAX_BANNERS; $i++) {
            $rows[] = ['image' => '/' . $i . '.png', 'sort' => $i];
        }
        $banners = ApplicationThemeServices::normalizeBanners($rows);
        $this->assertCount(ApplicationTheme::MAX_BANNERS, $banners);
        $this->assertSame('/0.png', $banners[0]['image']);
    }

    public function testSanitizeColorKeepsValidValue()
    {
        $this->assertSame('#1a2b3c', ApplicationThemeServices::sanitizeColor('#1a2b3c'));
    }

    public function testSanitizeColorLowercasesAndTrims()
    {
        $this->assertSame('#ff0000', ApplicationThemeServices::sanitizeColor(' #FF0000 '));
    }

    public function testSanitizeColorFallsBackOnInvalidValue()
    {
        foreach (['', 'red', '#fff', '#12345g', 'ff0000', '#ff00001'] as $color) {
            $this->assertSame(ApplicationTheme::DEFAULT_THEME_COLOR, ApplicationThemeServices::sanitizeColor($color));
        }
    }

    public function testDefaultThemeStructure()
    {
        $theme = ApplicationThemeServices::defaultTheme();
        $this->assertSame(
            ['title', 'logo', 'theme_color', 'theme_style', 'bubble_style', 'pc_icon', 'mobile_icon', 'banners', 'custom_html', 'show_platform_brand',
                'show_tip', 'window_style', 'tourist_avatar', 'service_feedback'],
            array_keys($theme)
        );
        //这两项空值代表继承租户全局设置，不能给默认内容
        $this->assertSame([], $theme['tourist_avatar']);
        $this->assertSame('', $theme['service_feedback']);
        $this->assertSame('', $theme['title']);
        $this->assertSame('', $theme['custom_html']);
        $this->assertSame(ApplicationTheme::DEFAULT_THEME_COLOR, $theme['theme_color']);
        $this->assertSame(ApplicationTheme::DEFAULT_THEME_STYLE, $theme['theme_style']);
        $this->assertSame(ApplicationTheme::DEFAULT_BUBBLE_STYLE, $theme['bubble_style']);
        $this->assertSame([], $theme['banners']);
        $this->assertSame(ApplicationTheme::BRAND_SHOW, $theme['show_platform_brand']);
    }

    public function testFormatThemeNormalizesStoredRow()
    {
        $theme = ApplicationThemeServices::formatTheme([
            'id' => 3,
            'tenant_id' => 2,
            'theme_color' => '#ABCDEF',
            'theme_style' => 'midnight',
            'bubble_style' => 'outline',
            'banners' => json_encode([['image' => '/a.png', 'link' => 'javascript:1', 'sort' => 0]]),
            'show_platform_brand' => '0',
        ]);
        $this->assertSame('#abcdef', $theme['theme_color']);
        $this->assertSame('midnight', $theme['theme_style']);
        $this->assertSame('outline', $theme['bubble_style']);
        $this->assertSame('', $theme['banners'][0]['link']);
        $this->assertSame(ApplicationTheme::BRAND_HIDE, $theme['show_platform_brand']);
        $this->assertSame('', $theme['logo']);
    }

    public function testSanitizeThemeStyleAcceptsKnownStyles()
    {
        foreach (ApplicationTheme::THEME_STYLES as $style) {
            $this->assertSame($style, ApplicationThemeServices::sanitizeThemeStyle($style));
        }
    }

    public function testSanitizeThemeStyleFallsBackOnUnknownValue()
    {
        $this->assertSame(
            ApplicationTheme::DEFAULT_THEME_STYLE,
            ApplicationThemeServices::sanitizeThemeStyle('unknown')
        );
    }

    public function testSanitizeBubbleStyleAcceptsKnownStyles()
    {
        foreach (ApplicationTheme::BUBBLE_STYLES as $style) {
            $this->assertSame($style, ApplicationThemeServices::sanitizeBubbleStyle($style));
        }
    }

    public function testSanitizeBubbleStyleFallsBackOnUnknownValue()
    {
        $this->assertSame(
            ApplicationTheme::DEFAULT_BUBBLE_STYLE,
            ApplicationThemeServices::sanitizeBubbleStyle('unknown')
        );
    }

    public function testNormalizeAvatarsAcceptsStringsAndObjects()
    {
        $this->assertSame(['/a.png', '/b.png'], ApplicationThemeServices::normalizeAvatars(['/a.png', ['url' => '/b.png']]));
    }

    public function testNormalizeAvatarsParsesJsonAndDedupes()
    {
        $this->assertSame(['/a.png'], ApplicationThemeServices::normalizeAvatars('["/a.png","/a.png"," "]'));
    }

    public function testNormalizeAvatarsRejectsGarbage()
    {
        $this->assertSame([], ApplicationThemeServices::normalizeAvatars('not-json'));
        $this->assertSame([], ApplicationThemeServices::normalizeAvatars(null));
    }
}
