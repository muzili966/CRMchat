<?php

namespace tests\unit;

use app\models\PlatformLead;
use app\services\platform\PlatformLeadServices;
use PHPUnit\Framework\TestCase;

/**
 * 销售线索测试
 *
 * 线索来自公网表单，字段必须清洗；来源与阶段是枚举，
 * 非法值不能落库，否则列表里会出现认不出的状态。
 */
class PlatformLeadTest extends TestCase
{
    public function testTextFieldsAreTrimmedAndStripped()
    {
        $payload = PlatformLeadServices::buildPayload([
            'name' => '  张先生  ',
            'company' => '<b>测试科技</b>',
            'content' => '<script>alert(1)</script>需要多渠道接入',
        ]);
        $this->assertSame('张先生', $payload['name']);
        $this->assertSame('测试科技', $payload['company']);
        //去标签后脚本内容会以纯文本残留，但不再是可执行标签
        $this->assertStringNotContainsString('<script>', $payload['content']);
        $this->assertStringContainsString('需要多渠道接入', $payload['content']);
    }

    public function testOverLongFieldsAreTruncated()
    {
        $payload = PlatformLeadServices::buildPayload([
            'name' => str_repeat('长', 80),
            'content' => str_repeat('字', PlatformLead::MAX_CONTENT + 200),
        ]);
        $this->assertSame(50, mb_strlen($payload['name']));
        $this->assertSame(PlatformLead::MAX_CONTENT, mb_strlen($payload['content']));
    }

    public function testKnownSourceIsKept()
    {
        foreach (array_keys(PlatformLead::SOURCES) as $source) {
            $payload = PlatformLeadServices::buildPayload(['source' => $source]);
            $this->assertSame($source, $payload['source']);
        }
    }

    /**
     * 来源可由公网表单传入，非法值必须归一化
     */
    public function testUnknownSourceFallsBackToWebsite()
    {
        foreach (['', 'bogus', 'admin', '<x>'] as $source) {
            $payload = PlatformLeadServices::buildPayload(['source' => $source]);
            $this->assertSame(PlatformLead::SOURCE_WEBSITE, $payload['source'], var_export($source, true));
        }
    }

    public function testMissingFieldsBecomeEmptyString()
    {
        $payload = PlatformLeadServices::buildPayload([]);
        foreach (['name', 'company', 'phone', 'email', 'scale', 'intent_plan', 'content'] as $key) {
            $this->assertSame('', $payload[$key], $key);
        }
    }

    /**
     * 成交与关闭属终态，不应再被算作待跟进
     */
    public function testClosedStagesCoverWonAndClosed()
    {
        $this->assertContains(PlatformLead::STAGE_WON, PlatformLead::CLOSED_STAGES);
        $this->assertContains(PlatformLead::STAGE_CLOSED, PlatformLead::CLOSED_STAGES);
        $this->assertNotContains(PlatformLead::STAGE_NEW, PlatformLead::CLOSED_STAGES);
        $this->assertNotContains(PlatformLead::STAGE_CONTACTED, PlatformLead::CLOSED_STAGES);
        $this->assertNotContains(PlatformLead::STAGE_INTENT, PlatformLead::CLOSED_STAGES);
    }

    public function testStagesAreContinuousAndLabelled()
    {
        $this->assertSame([1, 2, 3, 4, 5], array_keys(PlatformLead::STAGES));
        foreach (PlatformLead::STAGES as $label) {
            $this->assertNotSame('', $label);
        }
    }

    /**
     * 转线索时的需求描述由最近对话拼成，客服未填时全靠它
     */
    public function testDialogueDigestKeepsOnlyTheLastFewLines()
    {
        $rows = ['你好', '在吗', '第三条', '第四条', '第五条', '第六条', '第七条'];
        $digest = PlatformLeadServices::digestDialogue($rows);
        $this->assertSame('第三条；第四条；第五条；第六条；第七条', $digest);
    }

    public function testDialogueDigestDropsBlankLines()
    {
        $digest = PlatformLeadServices::digestDialogue(['  ', '想了解报价', '', null, '  能开发票吗 ']);
        $this->assertSame('想了解报价；能开发票吗', $digest);
    }

    public function testDialogueDigestOfEmptyRecordIsEmpty()
    {
        $this->assertSame('', PlatformLeadServices::digestDialogue([]));
        $this->assertSame('', PlatformLeadServices::digestDialogue(['', '   ']));
    }

    /**
     * 长对话不能撑爆 content 字段
     */
    public function testDialogueDigestIsTruncatedToContentLimit()
    {
        $rows = array_fill(0, PlatformLeadServices::DIALOGUE_PICK, str_repeat('长', 500));
        $digest = PlatformLeadServices::digestDialogue($rows);
        $this->assertSame(PlatformLead::MAX_CONTENT, mb_strlen($digest));
    }
}
