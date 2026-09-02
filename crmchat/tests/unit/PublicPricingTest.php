<?php

namespace tests\unit;

use app\services\TenantPlanServices;
use PHPUnit\Framework\TestCase;

/**
 * 官网定价展示测试
 *
 * 配额与能力必须自洽：能力关闭时若配额仍按"0=不限"显示，
 * 官网上就会出现"AI能力不含、AI日回复不限"这种自相矛盾的表述。
 */
class PublicPricingTest extends TestCase
{
    /**
     * 借反射调用受保护方法：这两个方法是纯数据换算，无需构造整个服务
     * @param string $method
     * @param array $plan
     * @return array
     */
    protected function call(string $method, array $plan): array
    {
        $ref = new \ReflectionMethod(TenantPlanServices::class, $method);
        $ref->setAccessible(true);
        return $ref->invoke(null, $plan);
    }

    protected function quotaText(array $plan, string $label): string
    {
        foreach ($this->call('publicQuotas', $plan) as $item) {
            if ($item['label'] === $label) {
                return $item['text'];
            }
        }
        return '';
    }

    public function testPositiveQuotaShowsValueWithUnit()
    {
        $this->assertSame('5个', $this->quotaText(['app_limit' => 5], '接入应用'));
    }

    public function testZeroQuotaMeansUnlimited()
    {
        $this->assertSame('不限', $this->quotaText(['app_limit' => 0], '接入应用'));
    }

    public function testRecordKeepZeroMeansForever()
    {
        $this->assertSame('永久', $this->quotaText(['record_keep_days' => 0], '记录保留'));
    }

    /**
     * AI能力关闭时，AI配额不能显示成"不限"
     */
    public function testAiQuotaShowsUnsupportedWhenFeatureOff()
    {
        $this->assertSame('不支持', $this->quotaText(['ai_reply' => 0, 'daily_ai_limit' => 0], 'AI日回复'));
        //即便配额填了数值，能力关着也应显示不支持
        $this->assertSame('不支持', $this->quotaText(['ai_reply' => 0, 'daily_ai_limit' => 500], 'AI日回复'));
    }

    public function testAiQuotaShownWhenFeatureOn()
    {
        $this->assertSame('200次', $this->quotaText(['ai_reply' => 1, 'daily_ai_limit' => 200], 'AI日回复'));
        $this->assertSame('不限', $this->quotaText(['ai_reply' => 1, 'daily_ai_limit' => 0], 'AI日回复'));
    }

    /**
     * 能力清单要含未包含项，便于横向比较档位差异
     */
    public function testFeaturesKeepDisabledOnesForComparison()
    {
        $features = $this->call('publicFeatures', ['auto_reply' => 1, 'ai_reply' => 0]);
        $labels = array_column($features, 'label');
        $this->assertContains('AI 智能客服', $labels);
        $map = array_column($features, 'enabled', 'label');
        $this->assertTrue($map['关键词自动回复']);
        $this->assertFalse($map['AI 智能客服']);
    }
}
