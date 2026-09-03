<?php

namespace tests\unit;

use PHPUnit\Framework\TestCase;

/**
 * 官网渲染契约测试
 *
 * 官网可以被解析到独立域名，届时控制台与客服都不再同源。
 * 模板里一旦残留写死的相对路径或固定 token，换个域名或换个环境就静默失效，
 * 而这类失效没有报错——访客只会看到一个点不动的按钮。
 */
class WebsiteRenderTest extends TestCase
{
    /**
     * @var string
     */
    protected $template;

    protected function setUp(): void
    {
        $this->template = (string)file_get_contents(__DIR__ . '/../../view/website/index.html');
    }

    public function testTemplateIsReadable()
    {
        $this->assertNotSame('', $this->template);
    }

    /**
     * 控制台链接必须走服务端注入的应用地址
     */
    public function testConsoleLinksAreNotHardcodedRelative()
    {
        $this->assertStringNotContainsString('href="/admin/"', $this->template);
        $this->assertStringContainsString('href="{$console_url}/admin/"', $this->template);
    }

    /**
     * 客服脚本同理，且要把 openUrl 传给嵌入脚本，否则它会退回 location.origin
     */
    public function testChatScriptUsesInjectedOrigin()
    {
        $this->assertStringNotContainsString('src="/customerServer.js"', $this->template);
        $this->assertStringContainsString('{$service_url}/customerServer.js', $this->template);
        $this->assertStringContainsString('"openUrl":"{$service_url}"', $this->template);
    }

    /**
     * token 曾经写死在模板里，随代码进到每个环境后与本环境的应用对不上，
     * 访客一律 400 掉进留言页
     */
    public function testChatTokenIsResolvedAtRuntime()
    {
        $this->assertStringContainsString('"token":"{$chat_token}"', $this->template);
        $this->assertSame(0, preg_match('/"token"\s*:\s*"[0-9a-f]{32}"/', $this->template),
            '模板里不应出现写死的32位token');
    }

    /**
     * 解析不到 token 时不渲染脚本，胜过挂一个必然失败的入口
     */
    public function testChatScriptIsGuarded()
    {
        $this->assertStringContainsString('{if $chat_token}', $this->template);
    }

    /**
     * 没有 icon 标签时浏览器会去要 /favicon.ico，那是上游默认图标
     */
    public function testFaviconIsDeclared()
    {
        $this->assertStringContainsString('rel="icon"', $this->template);
        $this->assertStringContainsString('qialink-favicon', $this->template);
    }

}
