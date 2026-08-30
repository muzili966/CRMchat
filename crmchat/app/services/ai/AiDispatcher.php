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


/**
 * AI客服分配决策
 *
 * 全静态纯函数、不查库：所有在线/绑定关系由调用方查好后经ctx传入，
 * 使"访客该分给谁"这一决策可被单测完整覆盖。
 * Class AiDispatcher
 * @package app\services\ai
 */
class AiDispatcher
{
    /**
     * 接待模式：空=AI未启用，standby=真人优先AI值守，ai_first=AI优先
     */
    const MODE_OFF = '';
    const MODE_STANDBY = 'standby';
    const MODE_AI_FIRST = 'ai_first';

    /**
     * 决策依据标识
     */
    const REASON_PASSED = 'passed';
    const REASON_BOUND = 'bound';
    const REASON_LATELY = 'lately';
    const REASON_RANDOM = 'random';
    const REASON_AI = 'ai';
    const REASON_NONE = 'none';
    const REASON_AI_TO_HUMAN = 'ai_to_human';

    /**
     * 粘性引用的优先级：访客端回传 > 转接绑定 > 上次聊天
     */
    const STICKY_KEYS = [
        self::REASON_PASSED => 'passed_id',
        self::REASON_BOUND => 'bound_id',
        self::REASON_LATELY => 'lately_id',
    ];

    /**
     * 决策访客本次会话应分配的坐席
     * @param array $ctx mode、ai_user_id、passed_id、bound_id、lately_id、online_human_ids、rand_seed
     * @return array to_user_id(0=无人可分配)、is_ai、reason、switch_from_ai
     */
    public static function decide(array $ctx): array
    {
        $mode = (string)($ctx['mode'] ?? self::MODE_OFF);
        $aiUserId = (int)($ctx['ai_user_id'] ?? 0);
        if ($mode === self::MODE_AI_FIRST && $aiUserId > 0) {
            return self::result($aiUserId, self::REASON_AI, true);
        }
        $humanIds = self::humanIds($ctx['online_human_ids'] ?? [], $aiUserId);
        $picked = self::pickHuman($ctx, $humanIds);
        if ($picked['to_user_id'] > 0) {
            //值守语义的关键：粘性引用指向AI但此刻有真人在线时必须改派，否则真人永远接不回访客
            $backToHuman = $mode === self::MODE_STANDBY && self::stickyIsAi($ctx, $aiUserId);
            return self::result($picked['to_user_id'], $backToHuman ? self::REASON_AI_TO_HUMAN : $picked['reason']);
        }
        if ($mode === self::MODE_STANDBY && $aiUserId > 0) {
            return self::result($aiUserId, self::REASON_AI, true);
        }
        return self::result(0, self::REASON_NONE);
    }

    /**
     * 在线真人坐席集合
     *
     * 即便调用方误把AI坐席混入在线列表也要剔除，保证"未启用AI时AI永不被选中"
     * @param mixed $onlineHumanIds
     * @param int $aiUserId
     * @return array
     */
    private static function humanIds($onlineHumanIds, int $aiUserId): array
    {
        $ids = array_map('intval', is_array($onlineHumanIds) ? $onlineHumanIds : []);
        $ids = array_filter($ids, function (int $id) use ($aiUserId) {
            return $id > 0 && $id !== $aiUserId;
        });
        return array_values(array_unique($ids));
    }

    /**
     * 在真人坐席中按粘性优先级选人，都不在线则随机
     * @param array $ctx
     * @param array $humanIds
     * @return array
     */
    private static function pickHuman(array $ctx, array $humanIds): array
    {
        if (!$humanIds) {
            return ['to_user_id' => 0, 'reason' => self::REASON_NONE];
        }
        foreach (self::STICKY_KEYS as $reason => $key) {
            $id = (int)($ctx[$key] ?? 0);
            if ($id > 0 && in_array($id, $humanIds, true)) {
                return ['to_user_id' => $id, 'reason' => $reason];
            }
        }
        $seed = isset($ctx['rand_seed']) ? (int)$ctx['rand_seed'] : null;
        return ['to_user_id' => self::pickRandom($humanIds, $seed), 'reason' => self::REASON_RANDOM];
    }

    /**
     * 生效的粘性引用（按优先级取第一个非空）是否指向AI坐席
     * @param array $ctx
     * @param int $aiUserId
     * @return bool
     */
    private static function stickyIsAi(array $ctx, int $aiUserId): bool
    {
        if ($aiUserId <= 0) {
            return false;
        }
        foreach (self::STICKY_KEYS as $key) {
            $id = (int)($ctx[$key] ?? 0);
            if ($id > 0) {
                return $id === $aiUserId;
            }
        }
        return false;
    }

    /**
     * 随机选人，seed非空时取模选择以便测试确定性复现
     * @param array $ids
     * @param int|null $seed
     * @return int
     */
    private static function pickRandom(array $ids, ?int $seed): int
    {
        if (!$ids) {
            return 0;
        }
        if ($seed !== null) {
            return (int)$ids[abs($seed) % count($ids)];
        }
        mt_srand();
        return (int)($ids[array_rand($ids)] ?? 0);
    }

    /**
     * 组装决策结果，switch_from_ai由reason推导保持二者不会打架
     * @param int $toUserId
     * @param string $reason
     * @param bool $isAi
     * @return array
     */
    private static function result(int $toUserId, string $reason, bool $isAi = false): array
    {
        return [
            'to_user_id' => $toUserId,
            'is_ai' => $isAi,
            'reason' => $reason,
            'switch_from_ai' => $reason === self::REASON_AI_TO_HUMAN,
        ];
    }
}
