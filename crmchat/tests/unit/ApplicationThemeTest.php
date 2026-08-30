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
            ['title', 'logo', 'theme_color', 'pc_icon', 'mobile_icon', 'banners', 'show_platform_brand'],
            array_keys($theme)
        );
        $this->assertSame('', $theme['title']);
        $this->assertSame(ApplicationTheme::DEFAULT_THEME_COLOR, $theme['theme_color']);
        $this->assertSame([], $theme['banners']);
        $this->assertSame(ApplicationTheme::BRAND_SHOW, $theme['show_platform_brand']);
    }

    public function testFormatThemeNormalizesStoredRow()
    {
        $theme = ApplicationThemeServices::formatTheme([
            'id' => 3,
            'tenant_id' => 2,
            'theme_color' => '#ABCDEF',
            'banners' => json_encode([['image' => '/a.png', 'link' => 'javascript:1', 'sort' => 0]]),
            'show_platform_brand' => '0',
        ]);
        $this->assertSame('#abcdef', $theme['theme_color']);
        $this->assertSame('', $theme['banners'][0]['link']);
        $this->assertSame(ApplicationTheme::BRAND_HIDE, $theme['show_platform_brand']);
        $this->assertSame('', $theme['logo']);
    }
}
