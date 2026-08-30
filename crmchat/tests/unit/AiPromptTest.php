<?php

namespace tests\unit;

use app\services\chat\ChatServiceDialogueRecordServices;
use crmeb\services\ai\AiPrompt;
use PHPUnit\Framework\TestCase;

/**
 * AI提示词组装测试
 *
 * 边界：超长截断、空配置兜底、历史条数上限、非文本转译、关键词分隔符与大小写。
 */
class AiPromptTest extends TestCase
{
    public function testEmptyConfigFallsBackToDefaultIdentity()
    {
        $prompt = AiPrompt::buildSystemPrompt([]);
        $this->assertStringContainsString(AiPrompt::DEFAULT_IDENTITY, $prompt);
        $this->assertStringContainsString(AiPrompt::BEHAVIOR_RULES, $prompt);
        $this->assertStringNotContainsString(AiPrompt::FAQ_TITLE, $prompt);
    }

    public function testSystemPromptTruncatedToLimit()
    {
        $identity = str_repeat('设', AiPrompt::MAX_SYSTEM_PROMPT + 100);
        $prompt = AiPrompt::buildSystemPrompt(['system_prompt' => $identity]);
        $kept = explode("\n\n", $prompt)[0];
        $this->assertSame(AiPrompt::MAX_SYSTEM_PROMPT, mb_strlen($kept, 'UTF-8'));
        $this->assertStringContainsString(AiPrompt::BEHAVIOR_RULES, $prompt);
    }

    public function testFaqAssembledFromJson()
    {
        $faq = json_encode([
            ['q' => '发货时间', 'a' => '48小时内发货'],
            ['q' => '', 'a' => '缺问题应丢弃'],
            ['q' => '缺答案应丢弃', 'a' => ''],
        ], JSON_UNESCAPED_UNICODE);
        $prompt = AiPrompt::buildSystemPrompt(['faq' => $faq]);
        $this->assertStringContainsString("Q: 发货时间\nA: 48小时内发货", $prompt);
        $this->assertStringNotContainsString('缺问题应丢弃', $prompt);
        $this->assertStringNotContainsString('缺答案应丢弃', $prompt);
    }

    public function testFaqTruncatedToLimit()
    {
        $items = array_map(function ($index) {
            return ['q' => '问题' . $index . str_repeat('长', 200), 'a' => '答案' . str_repeat('长', 200)];
        }, range(1, 30));
        $prompt = AiPrompt::buildSystemPrompt(['faq' => $items]);
        $faqSection = explode("\n\n", $prompt)[1];
        $body = mb_substr($faqSection, mb_strlen(AiPrompt::FAQ_TITLE, 'UTF-8') + 1, null, 'UTF-8');
        $this->assertSame(AiPrompt::MAX_FAQ, mb_strlen($body, 'UTF-8'));
    }

    public function testInvalidFaqIgnored()
    {
        $prompt = AiPrompt::buildSystemPrompt(['faq' => 'not-a-json']);
        $this->assertStringNotContainsString(AiPrompt::FAQ_TITLE, $prompt);
    }

    public function testBuildMessagesLayout()
    {
        $history = [
            ['role' => 'user', 'content' => '在吗'],
            ['role' => 'assistant', 'content' => '在的'],
            ['role' => 'other', 'content' => '非法角色'],
            ['role' => 'user', 'content' => '   '],
        ];
        $messages = AiPrompt::buildMessages([], $history, '怎么退款');
        $this->assertCount(4, $messages);
        $this->assertSame(AiPrompt::ROLE_SYSTEM, $messages[0]['role']);
        $this->assertSame(['user', 'assistant'], [$messages[1]['role'], $messages[2]['role']]);
        $this->assertSame(AiPrompt::ROLE_USER, $messages[3]['role']);
        $this->assertSame('怎么退款', $messages[3]['content']);
    }

    public function testNormalizeHistoryMapsRoleByAiUserId()
    {
        $records = [
            ['user_id' => 11, 'msn' => '你好', 'msn_type' => ChatServiceDialogueRecordServices::MSN_TYPE_TXT],
            ['user_id' => 99, 'msn' => '您好，请问需要什么帮助', 'msn_type' => ChatServiceDialogueRecordServices::MSN_TYPE_TXT],
            ['user_id' => 11, 'msn' => '   ', 'msn_type' => ChatServiceDialogueRecordServices::MSN_TYPE_TXT],
        ];
        $history = AiPrompt::normalizeHistory($records, 99);
        $this->assertCount(2, $history);
        $this->assertSame(AiPrompt::ROLE_USER, $history[0]['role']);
        $this->assertSame(AiPrompt::ROLE_ASSISTANT, $history[1]['role']);
    }

    public function testNormalizeHistoryLimitsCountAndLength()
    {
        $records = array_map(function ($index) {
            return ['user_id' => 11, 'msn' => '消息' . $index . str_repeat('啊', AiPrompt::MAX_SINGLE_MSG), 'msn_type' => 1];
        }, range(1, AiPrompt::MAX_HISTORY + 5));
        $history = AiPrompt::normalizeHistory($records, 99);
        $this->assertCount(AiPrompt::MAX_HISTORY, $history);
        $this->assertStringStartsWith('消息6', $history[0]['content']);
        $this->assertSame(AiPrompt::MAX_SINGLE_MSG, mb_strlen($history[0]['content'], 'UTF-8'));
    }

    public function testNormalizeHistoryTranslatesNonText()
    {
        $records = [
            ['user_id' => 11, 'msn' => 'http://img', 'msn_type' => ChatServiceDialogueRecordServices::MSN_TYPE_IME],
            ['user_id' => 11, 'msn' => '', 'msn_type' => ChatServiceDialogueRecordServices::MSN_TYPE_GOODS, 'other' => '{"store_name":"保温杯"}'],
        ];
        $history = AiPrompt::normalizeHistory($records, 99);
        $this->assertSame('[用户发送了一张图片]', $history[0]['content']);
        $this->assertSame('[用户咨询商品：保温杯]', $history[1]['content']);
    }

    public function testDescribeNonTextAllTypes()
    {
        $this->assertSame('[表情]', AiPrompt::describeNonText(ChatServiceDialogueRecordServices::MSN_TYPE_EMOT, []));
        $this->assertSame('[用户发送了一张图片]', AiPrompt::describeNonText(ChatServiceDialogueRecordServices::MSN_TYPE_IME, []));
        $this->assertSame('[用户发送了一条语音]', AiPrompt::describeNonText(ChatServiceDialogueRecordServices::MSN_TYPE_VOICE, []));
        $this->assertSame('[用户咨询商品：真丝围巾]', AiPrompt::describeNonText(ChatServiceDialogueRecordServices::MSN_TYPE_GOODS, ['title' => '真丝围巾', 'store_name' => '备用名']));
        $this->assertSame('[用户咨询订单：SN202608]', AiPrompt::describeNonText(ChatServiceDialogueRecordServices::MSN_TYPE_ORDER, ['order_sn' => 'SN202608']));
        $this->assertSame('[用户咨询商品]', AiPrompt::describeNonText(ChatServiceDialogueRecordServices::MSN_TYPE_GOODS, []));
        $this->assertSame('', AiPrompt::describeNonText(ChatServiceDialogueRecordServices::MSN_TYPE_TXT, []));
        $this->assertSame('', AiPrompt::describeNonText(99, []));
    }

    public function testMatchTransferKeywordSeparators()
    {
        $this->assertTrue(AiPrompt::matchTransferKeyword('我要转人工', '人工，客服'));
        $this->assertTrue(AiPrompt::matchTransferKeyword('我要投诉你们', '投诉,退款'));
        $this->assertTrue(AiPrompt::matchTransferKeyword('please call AGENT now', 'agent, human'));
    }

    public function testMatchTransferKeywordMissAndEmpty()
    {
        $this->assertFalse(AiPrompt::matchTransferKeyword('商品什么时候发货', '人工,客服'));
        $this->assertFalse(AiPrompt::matchTransferKeyword('人工', ''));
        $this->assertFalse(AiPrompt::matchTransferKeyword('人工', ' , ，'));
        $this->assertFalse(AiPrompt::matchTransferKeyword('   ', '人工'));
    }

    public function testTruncateKeepsMultibyteIntact()
    {
        $text = '客服你好啊';
        $this->assertSame('客服你', AiPrompt::truncate($text, 3));
        $this->assertSame($text, AiPrompt::truncate($text, 10));
        $this->assertSame('', AiPrompt::truncate($text, 0));
        $this->assertSame('客服你', mb_substr(AiPrompt::truncate($text, 3), 0, 3, 'UTF-8'));
    }
}
