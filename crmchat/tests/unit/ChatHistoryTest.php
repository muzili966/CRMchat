<?php

namespace tests\unit;

use app\services\chat\ChatHistoryServices;
use PHPUnit\Framework\TestCase;

/**
 * 历史会话测试
 *
 * 这个接口对外暴露分页参数，limit 不设上限时客户端一次就能把整张消息表拉走；
 * 边界收敛在 pageValue 里，故重点钉死它。
 */
class ChatHistoryTest extends TestCase
{
    /**
     * @var ChatHistoryServices
     */
    protected $svc;

    protected function setUp(): void
    {
        $this->svc = new ChatHistoryServices();
    }

    protected function pageValue(array $where): array
    {
        $m = new \ReflectionMethod($this->svc, 'pageValue');
        $m->setAccessible(true);
        return $m->invoke($this->svc, $where);
    }

    public function testDefaultsWhenNothingGiven()
    {
        $this->assertSame([1, 20], $this->pageValue([]));
    }

    /**
     * 超大 limit 必须被夹到上限，否则一次请求可拖走整表
     */
    public function testOversizeLimitIsClamped()
    {
        [, $limit] = $this->pageValue(['limit' => 100000]);
        $this->assertSame(ChatHistoryServices::MAX_LIMIT, $limit);
    }

    /**
     * 非法页码/条数不能变成 0 或负数传进 SQL
     */
    public function testInvalidValuesFallBackToSafeDefaults()
    {
        $this->assertSame([1, 20], $this->pageValue(['page' => 0, 'limit' => 0]));
        $this->assertSame([1, 20], $this->pageValue(['page' => -5, 'limit' => -1]));
        $this->assertSame([1, 20], $this->pageValue(['page' => 'abc', 'limit' => 'xyz']));
    }

    public function testNormalValuesPassThrough()
    {
        $this->assertSame([3, 50], $this->pageValue(['page' => 3, 'limit' => 50]));
        $this->assertSame([2, ChatHistoryServices::MAX_LIMIT], $this->pageValue(['page' => 2, 'limit' => ChatHistoryServices::MAX_LIMIT]));
    }

    public function testMaxLimitIsSane()
    {
        $this->assertGreaterThan(0, ChatHistoryServices::MAX_LIMIT);
        $this->assertLessThanOrEqual(200, ChatHistoryServices::MAX_LIMIT);
    }
}
