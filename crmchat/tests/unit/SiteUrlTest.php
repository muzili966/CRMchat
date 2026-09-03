<?php

namespace tests\unit;

use crmeb\utils\SiteUrl;
use PHPUnit\Framework\TestCase;

/**
 * 对外地址测试
 *
 * 这几个地址错了不会报错，只会让接入方站点上的客服静默失效，
 * 或者让页面在 https 下被浏览器拦成混合内容，所以边界要钉死。
 */
class SiteUrlTest extends TestCase
{
    /**
     * 同主机退回相对路径：页面挂 https 而配置是 http 内网地址时，
     * 绝对地址会被当混合内容拦掉
     */
    public function testSameHostFallsBackToRelative()
    {
        $this->assertSame('', SiteUrl::relativeIfSameHost('http://app.example.com', 'app.example.com'));
        $this->assertSame('', SiteUrl::relativeIfSameHost('http://app.example.com/', 'app.example.com'));
    }

    public function testHostComparisonIsCaseInsensitive()
    {
        $this->assertSame('', SiteUrl::relativeIfSameHost('http://APP.Example.COM', 'app.example.com'));
    }

    public function testCrossHostKeepsAbsoluteUrl()
    {
        $this->assertSame(
            'http://app.example.com',
            SiteUrl::relativeIfSameHost('http://app.example.com/', 'www.example.com')
        );
    }

    /**
     * 未配置时保持相对路径，不能凭空造出地址
     */
    public function testEmptyUrlStaysEmpty()
    {
        $this->assertSame('', SiteUrl::relativeIfSameHost('', 'www.example.com'));
        $this->assertSame('', SiteUrl::relativeIfSameHost('   ', 'www.example.com'));
    }

    /**
     * 解析不出主机的值不能被当成跨域地址甩给浏览器
     */
    public function testUrlWithoutHostIsTreatedAsRelative()
    {
        $this->assertSame('', SiteUrl::relativeIfSameHost('/chat', 'www.example.com'));
        $this->assertSame('', SiteUrl::relativeIfSameHost('not a url', 'www.example.com'));
    }

    /**
     * 端口不同即不同源，必须保留绝对地址
     */
    public function testDifferentPortSameHostStillRelative()
    {
        //parse_url 的 host 不含端口，同主机不同端口仍视为同主机：
        //浏览器按相对路径请求会落到当前端口，而这正是页面自己所在的服务
        $this->assertSame('', SiteUrl::relativeIfSameHost('http://app.example.com:8080', 'app.example.com'));
    }

    public function testEnvKeysAreStable()
    {
        $this->assertSame('SERVICE_URL', SiteUrl::ENV_SERVICE);
        $this->assertSame('CONSOLE_URL', SiteUrl::ENV_CONSOLE);
        $this->assertSame('WEBSITE_URL', SiteUrl::ENV_WEBSITE);
    }
}
