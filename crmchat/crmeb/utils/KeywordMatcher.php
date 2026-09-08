<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2020 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace crmeb\utils;

/**
 * 关键词自动回复匹配
 *
 * 匹配方向是「运营配置的关键词是否出现在访客这句话里」，不是反过来。
 * 早先的实现反了方向（keyword LIKE %访客词%），只能靠先把访客消息切成
 * 碎片来凑合，因而依赖外部分词服务；而碎片越短越容易落进某条高排序关键词里，
 * 出现「问发票答退款」这类误命中。方向掰正后分词不再是必需品。
 *
 * 二次召回不引词典：访客表述与配置词常常只差几个字（配「扣费了没启动」，
 * 访客说「钱扣了设备没启动」），把配置词自身切成片段去访客消息里找即可，
 * 方向仍然正确，且不需要任何外部依赖。
 *
 * TODO 依赖 xiaodi/think-pullword 至此已无调用方，但 vendor 随仓库提交、
 * 构建期不跑 composer，摘除需同步重生成 vendor/services.php 与自动加载，
 * 留待下次本地 composer 操作时一并处理。
 * @package crmeb\utils
 */
class KeywordMatcher
{
    /**
     * 关键词分隔符，兼容中英文逗号与顿号
     */
    const SEPARATORS = ',，、';

    /**
     * 参与二次召回的最短片段长度，太短会把「多久」这类通用词放进来造成误命中
     */
    const GRAM_MIN_LEN = 3;

    /**
     * 关键词长度达到该值才拆片段，短词本身已足够精确，拆了只会变宽
     */
    const SEGMENT_MIN_LEN = 4;

    /**
     * 片段命中的权重，保证整词命中恒优先于片段命中
     */
    const GRAM_WEIGHT = 0.6;

    /**
     * 未命中的得分
     */
    const NO_MATCH = 0.0;

    /**
     * 拆分关键词配置串
     * @param string $keyword
     * @return array
     */
    public static function split(string $keyword): array
    {
        $parts = preg_split('/[' . self::SEPARATORS . ']/u', $keyword) ?: [];
        $parts = array_map('trim', $parts);
        return array_values(array_filter($parts, function ($part) {
            return $part !== '';
        }));
    }

    /**
     * 关键词在消息中的匹配得分，取最长命中；未命中返回 0
     *
     * 用长度当分值，是为了让「设备二维码」这种更具体的词压过「设备」这种泛词，
     * 避免泛词因排序靠前就抢走本该更精确的那条回复。
     * @param string $message 访客原话
     * @param string $keyword 关键词配置串
     * @return float
     */
    public static function score(string $message, string $keyword): float
    {
        if ($message === '') {
            return self::NO_MATCH;
        }
        $best = self::NO_MATCH;
        $words = self::split($keyword);
        foreach ($words as $word) {
            if (mb_strpos($message, $word) !== false) {
                $best = max($best, (float)mb_strlen($word));
            }
        }
        //整词已命中就不必退到片段，避免片段把更长的整词命中冲淡
        if ($best > self::NO_MATCH) {
            return $best;
        }
        foreach ($words as $word) {
            $best = max($best, self::gramScore($message, $word));
        }
        return $best;
    }

    /**
     * 关键词片段在消息中的匹配得分
     * @param string $message
     * @param string $word
     * @return float
     */
    protected static function gramScore(string $message, string $word): float
    {
        $len = mb_strlen($word);
        if ($len < self::SEGMENT_MIN_LEN) {
            return self::NO_MATCH;
        }
        //由长到短找，命中即为该词能贡献的最长片段
        for ($size = $len - 1; $size >= self::GRAM_MIN_LEN; $size--) {
            for ($start = 0; $start + $size <= $len; $start++) {
                if (mb_strpos($message, mb_substr($word, $start, $size)) !== false) {
                    return $size * self::GRAM_WEIGHT;
                }
            }
        }
        return self::NO_MATCH;
    }

    /**
     * 在候选回复中挑出命中项，按「命中精确度 > 运营排序 > 新旧」排序
     * @param string $message 访客原话
     * @param array $rows 候选行，需含 keyword，可含 sort/id
     * @param int $limit 返回条数
     * @return array
     */
    public static function match(string $message, array $rows, int $limit): array
    {
        $message = trim($message);
        if ($message === '' || $limit <= 0) {
            return [];
        }
        $hits = [];
        foreach ($rows as $row) {
            $score = self::score($message, (string)($row['keyword'] ?? ''));
            if ($score > self::NO_MATCH) {
                $row['match_score'] = $score;
                $hits[] = $row;
            }
        }
        usort($hits, function ($a, $b) {
            return [$b['match_score'], (int)($b['sort'] ?? 0), (int)($b['id'] ?? 0)]
                <=> [$a['match_score'], (int)($a['sort'] ?? 0), (int)($a['id'] ?? 0)];
        });
        return array_slice($hits, 0, $limit);
    }
}
