<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\utils;

/**
 * 敏感词匹配（DFA）
 *
 * 词库建成前缀树后对文本单趟扫描，复杂度只与文本长度有关，与词条数无关；
 * 逐词正则的做法在消息热路径上会随词库增长线性变慢，不可用。
 *
 * 匹配前做归一化：转小写、全角转半角、跳过常见干扰符，
 * 否则"微 信""微-信""ＷＥＩＸＩＮ"这类改写轻易绕过。
 * 这挡不住拆字谐音等有心绕过——敏感词过滤的价值在于留痕与挡住绝大多数
 * 无意违规，不要当成完备的合规手段。
 * Class SensitiveFilter
 * @package crmeb\utils
 */
class SensitiveFilter
{
    /**
     * 命中后的处置：拦截，消息不入库
     */
    const ACTION_BLOCK = 1;

    /**
     * 命中后的处置：替换成掩码后照常发送
     */
    const ACTION_REPLACE = 2;

    /**
     * 命中后的处置：放行，仅留痕告警
     */
    const ACTION_WARN = 3;

    const ACTIONS = [self::ACTION_BLOCK, self::ACTION_REPLACE, self::ACTION_WARN];

    /**
     * 作用范围位掩码：访客发出的消息
     */
    const SCOPE_VISITOR = 1;

    /**
     * 作用范围位掩码：客服发出的消息
     */
    const SCOPE_AGENT = 2;

    /**
     * 作用范围位掩码：AI 生成的回复
     */
    const SCOPE_AI = 4;

    const SCOPE_ALL = self::SCOPE_VISITOR | self::SCOPE_AGENT | self::SCOPE_AI;

    /**
     * 替换用的掩码字符
     */
    const MASK_CHAR = '*';

    /**
     * 词条结束标记，用不会与字符冲突的键名
     */
    const END = "\0end";

    /**
     * 单条文本最多记录的命中数，防止刷屏文本产生海量留痕
     */
    const MAX_HITS = 20;

    /**
     * 匹配时跳过的干扰字符
     *
     * 只跳分隔性质的符号，不跳字母数字，否则"a1b"会被当成"ab"造成误伤。
     */
    const NOISE = [
        ' ', "\t", "\n", "\r", '-', '_', '.', '*', '+', '=', '~', '/', '\\', '|', '#', '@',
        '·', '。', '，', ',', '、', '…', '—', '　',
    ];

    /**
     * 构建前缀树
     * @param array $words [['word' => '词', ...其余作为命中元信息], ...]
     * @return array
     */
    public static function build(array $words): array
    {
        $trie = [];
        foreach ($words as $item) {
            $chars = self::chars(self::normalize((string)($item['word'] ?? '')));
            if (!$chars) {
                continue;
            }
            $node = &$trie;
            foreach ($chars as $char) {
                if (!isset($node[$char])) {
                    $node[$char] = [];
                }
                $node = &$node[$char];
            }
            //同词重复登记时后者覆盖前者，与库里的唯一约束一致
            $node[self::END] = $item;
            unset($node);
        }
        return $trie;
    }

    /**
     * 扫描文本，返回命中项
     *
     * 采用最长匹配并跳过已匹配区间，避免"微信"和"微信号"同时命中导致重复留痕。
     * @param array $trie
     * @param string $text
     * @return array [['meta' => 词信息, 'start' => 起始字符位, 'length' => 占用字符数], ...]
     */
    public static function detect(array $trie, string $text): array
    {
        if (!$trie || $text === '') {
            return [];
        }
        $chars = self::chars($text);
        $count = count($chars);
        $hits = [];
        for ($i = 0; $i < $count && count($hits) < self::MAX_HITS; $i++) {
            $hit = self::matchAt($trie, $chars, $i, $count);
            if (!$hit) {
                continue;
            }
            $hits[] = $hit;
            //跳过整段已命中区间，防止在其内部重复起匹
            $i += $hit['length'] - 1;
        }
        return $hits;
    }

    /**
     * 从指定位置尝试最长匹配
     * @param array $trie
     * @param array $chars
     * @param int $start
     * @param int $count
     * @return array|null
     */
    protected static function matchAt(array $trie, array $chars, int $start, int $count)
    {
        $node = $trie;
        $best = null;
        for ($j = $start; $j < $count; $j++) {
            $char = self::normalize($chars[$j]);
            //干扰符不消耗树节点，但计入命中长度，替换时一并盖掉
            if ($j > $start && in_array($char, self::NOISE, true)) {
                continue;
            }
            if (!isset($node[$char])) {
                break;
            }
            $node = $node[$char];
            if (isset($node[self::END])) {
                $best = ['meta' => $node[self::END], 'start' => $start, 'length' => $j - $start + 1];
            }
        }
        return $best;
    }

    /**
     * 按命中区间替换成掩码
     * @param string $text
     * @param array $hits detect 的返回
     * @return string
     */
    public static function mask(string $text, array $hits): string
    {
        if (!$hits) {
            return $text;
        }
        $chars = self::chars($text);
        foreach ($hits as $hit) {
            for ($i = $hit['start']; $i < $hit['start'] + $hit['length']; $i++) {
                if (isset($chars[$i])) {
                    $chars[$i] = self::MASK_CHAR;
                }
            }
        }
        return implode('', $chars);
    }

    /**
     * 归一化：转小写并把全角转半角
     *
     * 全角改写是最省事的绕过方式，故在匹配前统一。
     * @param string $text
     * @return string
     */
    public static function normalize(string $text): string
    {
        if ($text === '') {
            return '';
        }
        if (function_exists('mb_convert_kana')) {
            //r=全角英文转半角，n=全角数字转半角，s=全角空格转半角
            $text = mb_convert_kana($text, 'rns', 'UTF-8');
        }
        return mb_strtolower($text, 'UTF-8');
    }

    /**
     * 按字符切分，非法编码时返回空数组而不是产生乱码
     * @param string $text
     * @return array
     */
    protected static function chars(string $text): array
    {
        if ($text === '' || !mb_check_encoding($text, 'UTF-8')) {
            return [];
        }
        return preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * 命中项里最严的处置动作
     *
     * 一条消息命中多个词时以最严的为准：拦截 > 替换 > 告警。
     * @param array $hits
     * @return int
     */
    public static function severestAction(array $hits): int
    {
        $action = self::ACTION_WARN;
        foreach ($hits as $hit) {
            $current = (int)($hit['meta']['action'] ?? self::ACTION_WARN);
            if ($current === self::ACTION_BLOCK) {
                return self::ACTION_BLOCK;
            }
            if ($current === self::ACTION_REPLACE) {
                $action = self::ACTION_REPLACE;
            }
        }
        return $action;
    }
}
