<?php

namespace tests\unit;

use app\services\ApplicationThemeServices;
use PHPUnit\Framework\TestCase;

/**
 * 自定义广告HTML清洗测试
 *
 * 边界：script/iframe/object/embed标签（含未闭合形态）、on*事件属性的三种取值写法、
 * javascript伪协议（含空白绕过）、正常排版标签的保留，以及空值与纯空白。
 */
class ThemeHtmlSanitizeTest extends TestCase
{
    public function testStripsScriptTagWithContent()
    {
        $html = '<p>促销</p><script>alert(1)</script>';
        $this->assertSame('<p>促销</p>', $this->sanitize($html));
    }

    public function testStripsUnclosedAndUppercaseScriptTag()
    {
        $this->assertSame('hi', $this->sanitize('<SCRIPT SRC="x.js">hi'));
        $this->assertSame('hi', $this->sanitize('hi</script >'));
    }

    public function testStripsOtherDangerousTags()
    {
        foreach (['iframe', 'object', 'embed'] as $tag) {
            $html = '<div>ok</div><' . $tag . ' src="//evil.com">bad</' . $tag . '>';
            $this->assertSame('<div>ok</div>', $this->sanitize($html));
        }
    }

    public function testStripsEventAttributes()
    {
        $this->assertSame('<img src="/a.png">', $this->sanitize('<img src="/a.png" onerror="alert(1)">'));
        $this->assertSame('<div>x</div>', $this->sanitize('<div onclick=\'alert(1)\'>x</div>'));
        $this->assertSame('<div>x</div>', $this->sanitize('<div onmouseover=alert(1)>x</div>'));
    }

    public function testStripsJavascriptProtocol()
    {
        $this->assertSame('<a href="alert(1)">买</a>', $this->sanitize('<a href="javascript:alert(1)">买</a>'));
        $this->assertSame('<a href="alert(1)">买</a>', $this->sanitize('<a href="JavaScript:alert(1)">买</a>'));
        //浏览器会忽略协议名内的空白与换行，清洗必须一并覆盖
        $this->assertSame('<a href="alert(1)">买</a>', $this->sanitize("<a href=\"java\nscript:alert(1)\">买</a>"));
    }

    public function testKeepsNormalHtml()
    {
        $html = '<div class="ad"><a href="https://a.com" target="_blank"><img src="/a.png" alt="活动"></a>'
            . '<p style="color:#f00">限时优惠</p></div>';
        $this->assertSame($html, $this->sanitize($html));
    }

    public function testStripsAnyOnPrefixedAttribute()
    {
        //事件属性名无法穷举，宁可误伤 on 开头的自定义属性也不放过未知事件
        $this->assertSame('<div>x</div>', $this->sanitize('<div onfoo="1">x</div>'));
    }

    public function testEmptyValue()
    {
        $this->assertSame('', $this->sanitize(''));
        $this->assertSame('', $this->sanitize('   '));
        $this->assertSame('', $this->sanitize('<script>alert(1)</script>'));
    }

    /**
     * sanitizeHtml为受保护的纯函数，测试内通过反射调用
     * @param string $html
     * @return string
     */
    private function sanitize(string $html): string
    {
        $method = new \ReflectionMethod(ApplicationThemeServices::class, 'sanitizeHtml');
        $method->setAccessible(true);
        return $method->invoke(null, $html);
    }
}
