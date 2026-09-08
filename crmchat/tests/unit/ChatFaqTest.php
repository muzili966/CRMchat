<?php

namespace tests\unit;

use app\services\chat\ChatFaqServices;
use app\services\chat\ChatServiceDialogueRecordServices as RecordServices;
use crmeb\exceptions\AdminException;
use PHPUnit\Framework\TestCase;

/**
 * 常见问题测试
 *
 * 重点钉入库前的组装与校验：这层决定了访客卡片上会出现什么，
 * 也决定了 user_id 是否被正确置为「全站通用」——那个值一旦写错，
 * 条目会掉进某个客服的私有关键词回复里，卡片就再也查不到它。
 */
class ChatFaqTest extends TestCase
{
    /**
     * @var ChatFaqServices
     */
    protected $svc;

    protected function setUp(): void
    {
        $this->svc = new ChatFaqServices(new \app\dao\chat\ChatAutoReplyDao());
    }

    protected function build(array $data, string $appid = 'app_test'): array
    {
        $m = new \ReflectionMethod($this->svc, 'buildSaveData');
        $m->setAccessible(true);
        return $m->invoke($this->svc, $data, $appid);
    }

    /**
     * 归属必须是全站通用，否则卡片查不到自己刚存的条目
     */
    public function testAlwaysStoredAsGlobalScope()
    {
        $row = $this->build(['title' => '如何退款', 'content' => '走订单页']);
        $this->assertSame(ChatFaqServices::SCOPE_GLOBAL, $row['user_id']);
        $this->assertSame(0, ChatFaqServices::SCOPE_GLOBAL);
    }

    /**
     * 关键词留空时用问题兜底，保证打字与点卡片得到同一条答案
     */
    public function testKeywordFallsBackToTitle()
    {
        $row = $this->build(['title' => '如何退款', 'content' => '走订单页']);
        $this->assertSame('如何退款', $row['keyword']);
    }

    public function testKeywordKeptWhenProvided()
    {
        $row = $this->build(['title' => '如何退款', 'content' => '走订单页', 'keyword' => '退款,退货']);
        $this->assertSame('退款,退货', $row['keyword']);
    }

    /**
     * 空白字符不算内容，否则卡片上会出现点了没反应的空条目
     */
    public function testBlankTitleRejected()
    {
        $this->expectException(AdminException::class);
        $this->build(['title' => "  \n ", 'content' => '走订单页']);
    }

    public function testBlankContentRejected()
    {
        $this->expectException(AdminException::class);
        $this->build(['title' => '如何退款', 'content' => '   ']);
    }

    /**
     * 标题按字符数而非字节数限长，中文不能被误判超限
     */
    public function testTitleLengthCountedInCharacters()
    {
        $title = str_repeat('退', ChatFaqServices::TITLE_MAX);
        $row = $this->build(['title' => $title, 'content' => '答案']);
        $this->assertSame($title, $row['title']);

        $this->expectException(AdminException::class);
        $this->build(['title' => $title . '款', 'content' => '答案']);
    }

    /**
     * is_faq 必须收敛成 0/1，避免脏值让卡片查询漏掉条目
     */
    public function testIsFaqNormalizedToBoolean()
    {
        $this->assertSame(1, $this->build(['title' => 'a', 'content' => 'b'])['is_faq']);
        $this->assertSame(1, $this->build(['title' => 'a', 'content' => 'b', 'is_faq' => '1'])['is_faq']);
        $this->assertSame(0, $this->build(['title' => 'a', 'content' => 'b', 'is_faq' => 0])['is_faq']);
        $this->assertSame(0, $this->build(['title' => 'a', 'content' => 'b', 'is_faq' => ''])['is_faq']);
    }

    /**
     * 前后空白要去掉：卡片按标题原样展示，留空格会显得像排版错误
     */
    public function testValuesAreTrimmed()
    {
        $row = $this->build(['title' => '  如何退款  ', 'content' => "  走订单页\n"]);
        $this->assertSame('如何退款', $row['title']);
        $this->assertSame('走订单页', $row['content']);
    }

    /**
     * 卡片消息类型不能落进客户端可发送白名单，
     * 否则访客能自己伪造一张卡片诱导别人点
     */
    public function testFaqTypeNotClientSendable()
    {
        $this->assertSame(9, RecordServices::MSN_TYPE_FAQ);
        $sendable = RecordServices::MSN_TYPE;
        $this->assertSame(false, in_array(
            RecordServices::MSN_TYPE_FAQ,
            $sendable,
            true
        ));
    }

    /**
     * 卡片条数上限要是个合理值：0 等于功能不可用，过大则压住真实对话
     */
    public function testCardLimitIsSane()
    {
        $this->assertGreaterThan(0, ChatFaqServices::CARD_LIMIT);
        $this->assertLessThanOrEqual(20, ChatFaqServices::CARD_LIMIT);
    }
}
