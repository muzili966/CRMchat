<?php

namespace tests\unit;

use app\services\ai\AiDispatcher;
use app\services\ai\AiRateLimiter;
use PHPUnit\Framework\TestCase;

/**
 * AI客服分配决策测试
 *
 * 边界：未启用AI时AI坐席绝不被选中；standby下三条粘性路径（访客回传/转接绑定/上次聊天）
 * 指向AI且有真人在线时必须改派真人；离线粘性引用跳过；无人可分配返回0。
 */
class AiDispatcherTest extends TestCase
{
    const AI_ID = 99;
    const HUMAN_A = 11;
    const HUMAN_B = 12;
    const OFFLINE_ID = 77;

    /**
     * 组装决策入参，rand_seed固定保证随机分支可复现
     * @param array $override
     * @return array
     */
    private function ctx(array $override = []): array
    {
        return array_merge([
            'mode' => AiDispatcher::MODE_OFF,
            'ai_user_id' => self::AI_ID,
            'passed_id' => 0,
            'bound_id' => 0,
            'lately_id' => 0,
            'online_human_ids' => [],
            'rand_seed' => 0,
        ], $override);
    }

    public function testAiNeverPickedWhenDisabled()
    {
        $result = AiDispatcher::decide($this->ctx([
            'passed_id' => self::AI_ID,
            'bound_id' => self::AI_ID,
            'lately_id' => self::AI_ID,
            'online_human_ids' => [self::HUMAN_A],
        ]));
        $this->assertSame(self::HUMAN_A, $result['to_user_id'], '未启用AI时粘性引用指向AI也只能分给真人');
        $this->assertFalse($result['is_ai']);
        $this->assertSame(AiDispatcher::REASON_RANDOM, $result['reason']);
        $this->assertFalse($result['switch_from_ai'], '未启用AI时不存在AI接回语义');
    }

    public function testAiFilteredFromOnlineListWhenDisabled()
    {
        $result = AiDispatcher::decide($this->ctx(['online_human_ids' => [self::AI_ID]]));
        $this->assertSame(0, $result['to_user_id'], 'AI混入在线列表也要被剔除');
        $this->assertSame(AiDispatcher::REASON_NONE, $result['reason']);
    }

    public function testAiFirstAlwaysReturnsAi()
    {
        $result = AiDispatcher::decide($this->ctx([
            'mode' => AiDispatcher::MODE_AI_FIRST,
            'passed_id' => self::HUMAN_A,
            'online_human_ids' => [self::HUMAN_A, self::HUMAN_B],
        ]));
        $this->assertSame(self::AI_ID, $result['to_user_id']);
        $this->assertTrue($result['is_ai']);
        $this->assertSame(AiDispatcher::REASON_AI, $result['reason']);
    }

    public function testAiFirstDegradesWithoutAiSeat()
    {
        $result = AiDispatcher::decide($this->ctx([
            'mode' => AiDispatcher::MODE_AI_FIRST,
            'ai_user_id' => 0,
            'online_human_ids' => [self::HUMAN_A],
        ]));
        $this->assertSame(self::HUMAN_A, $result['to_user_id'], '无AI坐席时ai_first退化为常规分配');
        $this->assertFalse($result['is_ai']);
    }

    public function testStandbyPrefersOnlineHuman()
    {
        $result = AiDispatcher::decide($this->ctx([
            'mode' => AiDispatcher::MODE_STANDBY,
            'online_human_ids' => [self::HUMAN_A],
        ]));
        $this->assertSame(self::HUMAN_A, $result['to_user_id']);
        $this->assertFalse($result['is_ai']);
        $this->assertFalse($result['switch_from_ai']);
    }

    public function testStandbyFallsBackToAiWhenNoHumanOnline()
    {
        $result = AiDispatcher::decide($this->ctx([
            'mode' => AiDispatcher::MODE_STANDBY,
            'lately_id' => self::OFFLINE_ID,
            'online_human_ids' => [],
        ]));
        $this->assertSame(self::AI_ID, $result['to_user_id']);
        $this->assertTrue($result['is_ai']);
        $this->assertSame(AiDispatcher::REASON_AI, $result['reason']);
    }

    public function testStandbySwitchesBackFromPassedAi()
    {
        $result = AiDispatcher::decide($this->ctx([
            'mode' => AiDispatcher::MODE_STANDBY,
            'passed_id' => self::AI_ID,
            'online_human_ids' => [self::HUMAN_A],
        ]));
        $this->assertSame(self::HUMAN_A, $result['to_user_id'], '访客端回传AI坐席时真人上线应接回');
        $this->assertFalse($result['is_ai']);
        $this->assertTrue($result['switch_from_ai']);
        $this->assertSame(AiDispatcher::REASON_AI_TO_HUMAN, $result['reason']);
    }

    public function testStandbySwitchesBackFromBoundAi()
    {
        $result = AiDispatcher::decide($this->ctx([
            'mode' => AiDispatcher::MODE_STANDBY,
            'bound_id' => self::AI_ID,
            'online_human_ids' => [self::HUMAN_A],
        ]));
        $this->assertSame(self::HUMAN_A, $result['to_user_id'], '转接绑定指向AI时真人上线应接回');
        $this->assertTrue($result['switch_from_ai']);
        $this->assertSame(AiDispatcher::REASON_AI_TO_HUMAN, $result['reason']);
    }

    public function testStandbySwitchesBackFromLatelyAi()
    {
        $result = AiDispatcher::decide($this->ctx([
            'mode' => AiDispatcher::MODE_STANDBY,
            'lately_id' => self::AI_ID,
            'online_human_ids' => [self::HUMAN_A],
        ]));
        $this->assertSame(self::HUMAN_A, $result['to_user_id'], '上次聊天为AI时真人上线应接回');
        $this->assertTrue($result['switch_from_ai']);
        $this->assertSame(AiDispatcher::REASON_AI_TO_HUMAN, $result['reason']);
    }

    public function testOfflineStickyIdsSkipped()
    {
        $result = AiDispatcher::decide($this->ctx([
            'passed_id' => self::OFFLINE_ID,
            'bound_id' => self::OFFLINE_ID + 1,
            'lately_id' => self::OFFLINE_ID + 2,
            'online_human_ids' => [self::HUMAN_A],
        ]));
        $this->assertSame(self::HUMAN_A, $result['to_user_id']);
        $this->assertSame(AiDispatcher::REASON_RANDOM, $result['reason']);
    }

    public function testStickyPriorityOrder()
    {
        $online = ['online_human_ids' => [self::HUMAN_A, self::HUMAN_B]];
        $passed = AiDispatcher::decide($this->ctx($online + [
                'passed_id' => self::HUMAN_B,
                'bound_id' => self::HUMAN_A,
                'lately_id' => self::HUMAN_A,
            ]));
        $this->assertSame(AiDispatcher::REASON_PASSED, $passed['reason']);
        $this->assertSame(self::HUMAN_B, $passed['to_user_id']);

        $bound = AiDispatcher::decide($this->ctx($online + [
                'bound_id' => self::HUMAN_B,
                'lately_id' => self::HUMAN_A,
            ]));
        $this->assertSame(AiDispatcher::REASON_BOUND, $bound['reason']);
        $this->assertSame(self::HUMAN_B, $bound['to_user_id']);

        $lately = AiDispatcher::decide($this->ctx($online + ['lately_id' => self::HUMAN_B]));
        $this->assertSame(AiDispatcher::REASON_LATELY, $lately['reason']);
        $this->assertSame(self::HUMAN_B, $lately['to_user_id']);
    }

    public function testNobodyAvailableReturnsZero()
    {
        $result = AiDispatcher::decide($this->ctx(['ai_user_id' => 0]));
        $this->assertSame(0, $result['to_user_id']);
        $this->assertFalse($result['is_ai']);
        $this->assertSame(AiDispatcher::REASON_NONE, $result['reason']);
        $this->assertFalse($result['switch_from_ai']);
    }

    public function testStandbyWithoutAiSeatAndNoHumanReturnsZero()
    {
        $result = AiDispatcher::decide($this->ctx([
            'mode' => AiDispatcher::MODE_STANDBY,
            'ai_user_id' => 0,
        ]));
        $this->assertSame(0, $result['to_user_id']);
        $this->assertSame(AiDispatcher::REASON_NONE, $result['reason']);
    }

    public function testRandomPickIsSeedDeterministic()
    {
        $ctx = $this->ctx(['online_human_ids' => [self::HUMAN_A, self::HUMAN_B], 'rand_seed' => 3]);
        $this->assertSame(AiDispatcher::decide($ctx)['to_user_id'], AiDispatcher::decide($ctx)['to_user_id']);
        $this->assertSame(self::HUMAN_B, AiDispatcher::decide($ctx)['to_user_id']);
    }

    public function testRateLimiterKeyFormat()
    {
        $time = strtotime('2026-08-30 14:05:09');
        $this->assertSame(
            'ai_visitor:42:minute:202608301405',
            AiRateLimiter::buildKey(42, AiRateLimiter::WINDOW_MINUTE, $time)
        );
        $this->assertSame(
            'ai_visitor:42:day:20260830',
            AiRateLimiter::buildKey(42, AiRateLimiter::WINDOW_DAY, $time)
        );
    }

    public function testTouristDayLimitIsStricter()
    {
        $this->assertSame(AiRateLimiter::TOURIST_DAY_LIMIT, AiRateLimiter::dayLimit(true));
        $this->assertSame(AiRateLimiter::DAY_LIMIT, AiRateLimiter::dayLimit(false));
        $this->assertLessThan(AiRateLimiter::DAY_LIMIT, AiRateLimiter::TOURIST_DAY_LIMIT);
    }
}
