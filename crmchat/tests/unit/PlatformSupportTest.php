<?php

namespace tests\unit;

use app\models\Tenant;
use app\services\platform\PlatformSupportServices;
use PHPUnit\Framework\TestCase;

/**
 * 平台客服入口测试
 *
 * 这个入口把平台自营租户的会话窗口挂进租户后台，地址一旦拼错就是一个
 * 点不开的按钮，而用户只会看到白屏。这里钉住路由与降级行为。
 */
class PlatformSupportTest extends TestCase
{
    /**
     * 会话窗口路由必须与嵌入脚本 customerServer.js 一致，改一处就得改两处
     */
    public function testChatPathMatchesEmbedScript()
    {
        $this->assertSame('/chat', PlatformSupportServices::CHAT_PATH);
    }

    /**
     * 平台自营租户是被联系的一方，不该给它挂一个联系自己的按钮
     */
    public function testPlatformTenantIsBoundary()
    {
        //入口对 tenant_id <= DEFAULT_TENANT_ID 关闭，这里钉住边界值本身
        $this->assertSame(1, Tenant::DEFAULT_TENANT_ID);
        $this->assertLessThan(Tenant::DEFAULT_TENANT_ID, Tenant::PLATFORM_TENANT_ID);
    }

    /**
     * 关闭态必须同时给出 url，前端拿 res.data.url 时不该撞上 undefined
     */
    public function testDisabledEntryKeepsShape()
    {
        $svc = new PlatformSupportServices();
        $m = new \ReflectionMethod($svc, 'disabled');
        $m->setAccessible(true);
        $r = $m->invoke($svc);
        $this->assertSame(['enabled' => false, 'url' => ''], $r);
    }
}
