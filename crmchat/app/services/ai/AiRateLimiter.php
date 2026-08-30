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

namespace app\services\ai;


use crmeb\services\CacheService;
use think\facade\Log;

/**
 * AI访客维度限频
 *
 * 租户日配额之外再加一层访客维度限制：游客可反复重建身份，
 * 单靠租户配额会被一个人刷爆整个租户的额度。
 * 与租户消息计数不同，此处fail-closed——计数不可用时拒绝，因为AI调用有真实成本。
 * Class AiRateLimiter
 * @package app\services\ai
 */
class AiRateLimiter
{
    /**
     * 单访客每分钟/每日AI回复上限，游客身份廉价故收得更紧
     */
    const MINUTE_LIMIT = 10;
    const DAY_LIMIT = 100;
    const TOURIST_DAY_LIMIT = 30;

    const KEY_PREFIX = 'ai_visitor:';

    /**
     * 计数窗口
     */
    const WINDOW_MINUTE = 'minute';
    const WINDOW_DAY = 'day';

    /**
     * 计数key存活时间：均比窗口本身多留一档，避免边界时刻计数提前消失
     */
    const MINUTE_TTL = 120;
    const DAY_TTL = 172800;

    /**
     * 是否允许本次AI回复，分钟与日两档任一超限即拒绝
     * @param int $userId 访客chat_user id
     * @param bool $isTourist
     * @return bool
     */
    public function allow(int $userId, bool $isTourist = false): bool
    {
        if ($userId <= 0) {
            return false;
        }
        try {
            $cache = CacheService::redisHandler();
            $minute = $this->hit($cache, self::buildKey($userId, self::WINDOW_MINUTE), self::MINUTE_TTL);
            $day = $this->hit($cache, self::buildKey($userId, self::WINDOW_DAY), self::DAY_TTL);
        } catch (\Throwable $e) {
            Log::error('AI访客限频计数失败：' . $e->getMessage());
            return false;
        }
        return $minute <= self::MINUTE_LIMIT && $day <= self::dayLimit($isTourist);
    }

    /**
     * 自增计数并在首次写入时设置过期
     * @param mixed $cache
     * @param string $key
     * @param int $ttl
     * @return int
     */
    protected function hit($cache, string $key, int $ttl): int
    {
        $count = (int)$cache->incr($key);
        if ($count == 1) {
            $cache->expire($key, $ttl);
        }
        return $count;
    }

    /**
     * 日上限
     * @param bool $isTourist
     * @return int
     */
    public static function dayLimit(bool $isTourist): int
    {
        return $isTourist ? self::TOURIST_DAY_LIMIT : self::DAY_LIMIT;
    }

    /**
     * 计数key：分钟窗口精确到分、日窗口精确到天，到点自然换key
     * @param int $userId
     * @param string $window
     * @param int $time 计数时刻，0=当前时间（显式传入便于测试）
     * @return string
     */
    public static function buildKey(int $userId, string $window, int $time = 0): string
    {
        $time = $time > 0 ? $time : time();
        $suffix = $window === self::WINDOW_MINUTE ? date('YmdHi', $time) : date('Ymd', $time);
        return self::KEY_PREFIX . $userId . ':' . $window . ':' . $suffix;
    }
}
