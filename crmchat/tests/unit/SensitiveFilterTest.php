<?php

namespace tests\unit;

use crmeb\utils\SensitiveFilter;
use PHPUnit\Framework\TestCase;

/**
 * 敏感词匹配测试
 *
 * 这是消息热路径上的逻辑，误伤会直接影响正常对话，漏判则失去意义，
 * 故两个方向都要钉：该命中的绕过写法要命中，不该命中的正常文本不能误伤。
 */
class SensitiveFilterTest extends TestCase
{
    protected function trie(array $words = null): array
    {
        $words = $words ?: [
            ['id' => 1, 'word' => '微信', 'action' => SensitiveFilter::ACTION_REPLACE],
            ['id' => 2, 'word' => '微信号', 'action' => SensitiveFilter::ACTION_BLOCK],
            ['id' => 3, 'word' => 'QQ', 'action' => SensitiveFilter::ACTION_WARN],
        ];
        return SensitiveFilter::build($words);
    }

    protected function words(string $text, array $trie = null): array
    {
        $hits = SensitiveFilter::detect($trie ?: $this->trie(), $text);
        return array_map(function ($hit) {
            return $hit['meta']['word'];
        }, $hits);
    }

    public function testMatchesPlainWord()
    {
        $this->assertSame(['微信'], $this->words('加我微信聊'));
        $this->assertSame([], $this->words('今天天气不错'));
    }

    /**
     * 干扰符分隔是最省事的绕过写法，必须能穿透
     */
    public function testNoiseCharactersAreSkipped()
    {
        foreach (['微 信', '微-信', '微.信', '微_信', '微·信', '微*信'] as $text) {
            $this->assertSame(['微信'], $this->words($text), $text . ' 应命中');
        }
    }

    /**
     * 全角与大小写改写同样要穿透
     */
    public function testFullWidthAndCaseAreNormalized()
    {
        $this->assertSame(['QQ'], $this->words('加我qq'));
        $this->assertSame(['QQ'], $this->words('加我ＱＱ'));
    }

    /**
     * 最长匹配：命中"微信号"就不该再报"微信"
     */
    public function testLongestMatchWins()
    {
        $this->assertSame(['微信号'], $this->words('我的微信号是123'));
    }

    /**
     * 命中区间不重复起匹，否则一句话能刷出大量重复留痕
     */
    public function testHitsDoNotOverlap()
    {
        $hits = SensitiveFilter::detect($this->trie(), '微信微信微信');
        $this->assertCount(3, $hits);
        $this->assertSame([0, 2, 4], array_column($hits, 'start'));
    }

    /**
     * 干扰符不跳字母数字，否则会把正常文本切出敏感词来
     */
    public function testDoesNotSkipAlphanumeric()
    {
        $trie = SensitiveFilter::build([['id' => 1, 'word' => 'ab', 'action' => SensitiveFilter::ACTION_BLOCK]]);
        $this->assertSame([], $this->words('a1b', $trie));
        $this->assertSame(['ab'], $this->words('a b', $trie));
    }

    /**
     * 替换要盖住整个命中区间，含被跳过的干扰符
     */
    public function testMaskCoversWholeSpan()
    {
        $hits = SensitiveFilter::detect($this->trie(), '加我微 信好吗');
        $this->assertSame('加我***好吗', SensitiveFilter::mask('加我微 信好吗', $hits));
    }

    public function testMaskWithoutHitsReturnsOriginal()
    {
        $this->assertSame('你好', SensitiveFilter::mask('你好', []));
    }

    /**
     * 一条消息命中多个词时以最严的处置为准
     */
    public function testSeverestActionWins()
    {
        $hits = SensitiveFilter::detect($this->trie(), '加qq还是微信号');
        $this->assertSame(SensitiveFilter::ACTION_BLOCK, SensitiveFilter::severestAction($hits));

        $onlyWarn = SensitiveFilter::detect($this->trie(), '加qq');
        $this->assertSame(SensitiveFilter::ACTION_WARN, SensitiveFilter::severestAction($onlyWarn));

        $replace = SensitiveFilter::detect($this->trie(), '加微信');
        $this->assertSame(SensitiveFilter::ACTION_REPLACE, SensitiveFilter::severestAction($replace));
    }

    /**
     * 空词库与空文本不能出异常，热路径上要能安全短路
     */
    public function testEmptyInputsAreSafe()
    {
        $this->assertSame([], SensitiveFilter::detect([], '任意文本'));
        $this->assertSame([], SensitiveFilter::detect($this->trie(), ''));
        $this->assertSame([], SensitiveFilter::build([]));
        $this->assertSame([], SensitiveFilter::build([['word' => '']]));
    }

    /**
     * 非法编码不能产生乱码或异常
     */
    public function testInvalidEncodingIsIgnored()
    {
        $this->assertSame([], SensitiveFilter::detect($this->trie(), "\xB5\xC4\xFF"));
    }

    /**
     * 单条文本的命中数要有上限，防止刷屏文本产生海量留痕
     */
    public function testHitCountIsCapped()
    {
        $hits = SensitiveFilter::detect($this->trie(), str_repeat('微信', 200));
        $this->assertCount(SensitiveFilter::MAX_HITS, $hits);
    }

    /**
     * 作用范围是位掩码，三个位互不重叠
     */
    public function testScopeBitsAreDistinct()
    {
        $bits = [SensitiveFilter::SCOPE_VISITOR, SensitiveFilter::SCOPE_AGENT, SensitiveFilter::SCOPE_AI];
        $this->assertSame(SensitiveFilter::SCOPE_ALL, array_sum($bits));
        foreach ($bits as $a) {
            foreach ($bits as $b) {
                if ($a !== $b) {
                    $this->assertSame(0, $a & $b);
                }
            }
        }
    }
}
