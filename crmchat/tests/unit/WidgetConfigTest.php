<?php

namespace tests\unit;

use app\models\ApplicationTheme;
use app\services\ApplicationThemeServices;
use PHPUnit\Framework\TestCase;

/**
 * 悬浮挂件配置测试
 *
 * 挂件配置会被嵌入脚本用来渲染接入方页面上的客服入口，
 * 非法值必须有确定的回落，否则接入方页面上的按钮会直接消失或错乱。
 */
class WidgetConfigTest extends TestCase
{
    public function testWindowStyleAcceptsKnownValues()
    {
        $this->assertSame(ApplicationTheme::WINDOW_FLOAT, ApplicationThemeServices::sanitizeWindowStyle('float'));
        $this->assertSame(ApplicationTheme::WINDOW_CENTER, ApplicationThemeServices::sanitizeWindowStyle('center'));
    }

    public function testWindowStyleTrimsWhitespace()
    {
        $this->assertSame(ApplicationTheme::WINDOW_CENTER, ApplicationThemeServices::sanitizeWindowStyle('  center  '));
    }

    /**
     * 非法值一律回落悬浮对话框，不能让接入方页面拿到无法识别的形态
     */
    public function testWindowStyleFallsBackOnUnknownValue()
    {
        foreach (['', 'bogus', 'CENTER', 'popup', '<script>'] as $value) {
            $this->assertSame(
                ApplicationTheme::WINDOW_FLOAT,
                ApplicationThemeServices::sanitizeWindowStyle($value),
                var_export($value, true)
            );
        }
    }

    /**
     * 默认装修必须带上挂件相关字段，否则新应用取不到会渲染成空
     */
    public function testDefaultThemeCarriesWidgetFields()
    {
        $theme = ApplicationThemeServices::defaultTheme();
        $this->assertArrayHasKey('show_tip', $theme);
        $this->assertArrayHasKey('window_style', $theme);
        $this->assertSame(ApplicationTheme::TIP_SHOW, $theme['show_tip']);
        $this->assertSame(ApplicationTheme::WINDOW_FLOAT, $theme['window_style']);
    }

    /**
     * 默认显示入口按钮：漏传时不应让客服入口消失
     */
    public function testShowTipDefaultsToVisible()
    {
        $payload = ApplicationThemeServices::buildPayload(['title' => 'x']);
        $this->assertSame(ApplicationTheme::TIP_SHOW, $payload['show_tip']);
    }

    public function testShowTipCanBeHidden()
    {
        $payload = ApplicationThemeServices::buildPayload(['title' => 'x', 'show_tip' => 0]);
        $this->assertSame(ApplicationTheme::TIP_HIDE, $payload['show_tip']);
    }

    public function testWindowStyleIsNormalizedOnSave()
    {
        $payload = ApplicationThemeServices::buildPayload(['title' => 'x', 'window_style' => 'bogus']);
        $this->assertSame(ApplicationTheme::WINDOW_FLOAT, $payload['window_style']);
    }
}
