<?php

namespace tests\unit;

use crmeb\utils\KeywordMatcher;
use PHPUnit\Framework\TestCase;

/**
 * 关键词自动回复匹配测试
 *
 * 这条链路的历史问题是方向反了（拿访客的词去匹配关键词字段），表现为
 * 「问发票答退款」这类误命中，且必须依赖外部分词服务才勉强能用。
 * 因此精度方向要重点钉：泛词不能压过具体词，不相干的话不能命中。
 */
class KeywordMatcherTest extends TestCase
{
    /**
     * 与线上同形状的候选行：sort 越大运营优先级越高
     */
    protected function rows(): array
    {
        return [
            ['id' => 3, 'sort' => 90, 'title' => '如何申请退款？', 'keyword' => '退款,退钱,申请退款,退款多久,原路返回'],
            ['id' => 4, 'sort' => 85, 'title' => '设备显示离线怎么处理？', 'keyword' => '离线,不在线,掉线,连不上,设备离线'],
            ['id' => 2, 'sort' => 95, 'title' => '支付成功但设备没启动？', 'keyword' => '支付成功没启动,扣费了没启动,扣钱了,没运行,未启动'],
            ['id' => 7, 'sort' => 70, 'title' => '商户余额怎么提现？', 'keyword' => '提现,余额,结算,到账,打款'],
            ['id' => 28, 'sort' => 22, 'title' => '发票怎么开？', 'keyword' => '发票,开票,报销,税号'],
            ['id' => 32, 'sort' => 32, 'title' => '设备二维码在哪里获取？', 'keyword' => '设备二维码,收款码,码牌,贴码'],
        ];
    }

    protected function hit(string $message): string
    {
        $result = KeywordMatcher::match($message, $this->rows(), 5);
        return $result ? $result[0]['title'] : '';
    }

    public function testSplitsOnMixedSeparators()
    {
        $this->assertSame(['退款', '退钱', '原路返回'], KeywordMatcher::split('退款，退钱、 原路返回'));
        $this->assertSame([], KeywordMatcher::split('  ,  ，  '));
    }

    /**
     * 核心方向：关键词出现在访客这句话里就该命中，而不是要求访客只打关键词
     */
    public function testMatchesKeywordInsideSentence()
    {
        $this->assertSame('如何申请退款？', $this->hit('我要退款'));
        $this->assertSame('如何申请退款？', $this->hit('你好，我想申请退款可以吗'));
        $this->assertSame('设备显示离线怎么处理？', $this->hit('这个设备离线了是什么情况'));
        $this->assertSame('商户余额怎么提现？', $this->hit('我想提现，多久到账'));
    }

    /**
     * 旧实现下这些全会被高排序的「退款」条目截走，是最典型的误命中
     */
    public function testGenericFragmentsDoNotStealMoreSpecificEntries()
    {
        $this->assertSame('发票怎么开？', $this->hit('发票怎么开呀'));
        $this->assertSame('设备二维码在哪里获取？', $this->hit('设备二维码在哪儿'));
    }

    /**
     * 更长的整词命中应压过泛词，避免「设备」这种词抢答
     */
    public function testLongerKeywordWinsOverShorterOne()
    {
        $rows = [
            ['id' => 1, 'sort' => 99, 'title' => '泛问设备', 'keyword' => '设备'],
            ['id' => 2, 'sort' => 1, 'title' => '设备二维码', 'keyword' => '设备二维码'],
        ];
        $result = KeywordMatcher::match('设备二维码在哪儿', $rows, 5);
        $this->assertSame('设备二维码', $result[0]['title']);
    }

    /**
     * 同等命中长度时才轮到运营排序说话
     */
    public function testSortBreaksTieOnEqualScore()
    {
        $rows = [
            ['id' => 1, 'sort' => 10, 'title' => '低优先', 'keyword' => '离线'],
            ['id' => 2, 'sort' => 90, 'title' => '高优先', 'keyword' => '离线'],
        ];
        $result = KeywordMatcher::match('设备离线了', $rows, 5);
        $this->assertSame('高优先', $result[0]['title']);
    }

    /**
     * 二次召回：访客表述与配置词只差几个字时靠片段兜住
     */
    public function testFallsBackToKeywordFragment()
    {
        $this->assertSame('支付成功但设备没启动？', $this->hit('钱扣了设备没启动怎么办'));
    }

    /**
     * 片段召回不能盖过整词命中
     */
    public function testExactKeywordBeatsFragment()
    {
        $rows = [
            ['id' => 1, 'sort' => 1, 'title' => '整词命中', 'keyword' => '提现'],
            ['id' => 2, 'sort' => 99, 'title' => '片段命中', 'keyword' => '扣费了没提现过'],
        ];
        $result = KeywordMatcher::match('我要提现', $rows, 5);
        $this->assertSame('整词命中', $result[0]['title']);
    }

    /**
     * 过短的片段不参与召回，否则「多久」「怎么」会把不相干的条目拉进来
     */
    public function testShortFragmentsAreIgnored()
    {
        $rows = [['id' => 1, 'sort' => 1, 'title' => '退款', 'keyword' => '退款多久']];
        $this->assertSame([], KeywordMatcher::match('这个要多久', $rows, 5));
    }

    public function testUnrelatedMessageMatchesNothing()
    {
        $this->assertSame('', $this->hit('今天天气不错'));
        $this->assertSame([], KeywordMatcher::match('', $this->rows(), 5));
        $this->assertSame([], KeywordMatcher::match('退款', $this->rows(), 0));
    }

    /**
     * 结果条数受限，且按精度降序排列
     */
    public function testResultsAreLimitedAndOrdered()
    {
        $rows = [
            ['id' => 1, 'sort' => 5, 'title' => 'A', 'keyword' => '设备'],
            ['id' => 2, 'sort' => 5, 'title' => 'B', 'keyword' => '设备离线'],
            ['id' => 3, 'sort' => 5, 'title' => 'C', 'keyword' => '设备离线了吗'],
        ];
        $result = KeywordMatcher::match('我的设备离线了吗', $rows, 2);
        $this->assertCount(2, $result);
        $this->assertSame(['C', 'B'], array_column($result, 'title'));
    }
}
