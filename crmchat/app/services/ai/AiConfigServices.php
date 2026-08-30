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


use app\dao\AiConfigDao;
use app\models\AiConfig;
use crmeb\basic\BaseServices;
use crmeb\services\ai\AiPrompt;
use crmeb\exceptions\AdminException;
use crmeb\services\CacheService;
use crmeb\services\tenant\TenantContext;
use think\facade\Log;

/**
 * AI客服配置service
 * Class AiConfigServices
 * @package app\services\ai
 */
class AiConfigServices extends BaseServices
{

    /**
     * 配置缓存key前缀与TTL（配置变更最迟5分钟生效，保存时会主动清除）
     */
    const CACHE_PREFIX = 'ai_config:';
    const CACHE_TTL = 300;

    /**
     * AiConfigServices constructor.
     * @param AiConfigDao $dao
     */
    public function __construct(AiConfigDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取租户AI配置（带缓存），空数组表示未配置
     * @param int $tenantId
     * @return array
     */
    public function getConfig(int $tenantId): array
    {
        if ($tenantId <= 0) {
            return [];
        }
        try {
            return CacheService::redisHandler()->remember(self::CACHE_PREFIX . $tenantId, function () use ($tenantId) {
                return TenantContext::withoutTenant(function () use ($tenantId) {
                    $config = $this->dao->getByTenantId($tenantId);
                    return $config ? $config->toArray() : [];
                });
            }, self::CACHE_TTL) ?: [];
        } catch (\Throwable $e) {
            Log::error('读取AI配置失败：' . $e->getMessage());
            return [];
        }
    }

    /**
     * 保存租户AI配置（不存在则新建）
     * @param int $tenantId
     * @param array $data
     * @return bool
     */
    public function saveConfig(int $tenantId, array $data)
    {
        $payload = self::buildPayload($data);
        $this->validatePayload($payload);
        $this->validateFaq($payload['faq'], self::faqRowCount($data['faq'] ?? null));
        $result = TenantContext::runAs($tenantId, function () use ($tenantId, $payload) {
            return $this->persist($tenantId, $payload);
        });
        $this->clearCache($tenantId);
        return $result;
    }

    /**
     * 落库：按租户唯一键决定更新还是插入
     * @param int $tenantId
     * @param array $payload
     * @return bool
     */
    protected function persist(int $tenantId, array $payload)
    {
        $payload['update_time'] = time();
        $id = (int)$this->dao->value(['tenant_id' => $tenantId], 'id');
        if ($id) {
            return false !== $this->dao->update(['id' => $id, 'tenant_id' => $tenantId], $payload);
        }
        $payload['tenant_id'] = $tenantId;
        $payload['create_time'] = time();
        return (bool)$this->dao->save($payload);
    }

    /**
     * 清除配置缓存
     * @param int $tenantId
     * @return void
     */
    public function clearCache(int $tenantId): void
    {
        try {
            CacheService::redisHandler()->delete(self::CACHE_PREFIX . $tenantId);
        } catch (\Throwable $e) {
            Log::error('清除AI配置缓存失败：' . $e->getMessage());
        }
    }

    /**
     * 配置是否已生效（纯函数）
     * @param array $config
     * @return bool
     */
    public static function isEffective(array $config): bool
    {
        if (!$config || (int)($config['enable'] ?? AiConfig::ENABLE_OFF) !== AiConfig::ENABLE_ON) {
            return false;
        }
        return in_array((string)($config['mode'] ?? ''), AiConfig::MODES, true);
    }

    /**
     * 归一化FAQ为[['q'=>..,'a'=>..]]（纯函数），脏数据直接丢弃
     * @param mixed $faq json字符串或数组
     * @return array
     */
    public static function normalizeFaq($faq): array
    {
        $rows = is_string($faq) ? json_decode($faq, true) : $faq;
        if (!is_array($rows)) {
            return [];
        }
        $items = array_map([self::class, 'normalizeFaqItem'], $rows);
        return array_values(array_filter($items));
    }

    /**
     * 归一化单条FAQ，问题或答案缺失返回null
     * @param mixed $row
     * @return array|null
     */
    protected static function normalizeFaqItem($row)
    {
        if (!is_array($row)) {
            return null;
        }
        $question = isset($row['q']) && is_scalar($row['q']) ? trim((string)$row['q']) : '';
        $answer = isset($row['a']) && is_scalar($row['a']) ? trim((string)$row['a']) : '';
        if ($question === '' || $answer === '') {
            return null;
        }
        return ['q' => $question, 'a' => $answer];
    }

    /**
     * 原始FAQ条数（纯函数），-1表示结构非法
     * @param mixed $faq
     * @return int
     */
    public static function faqRowCount($faq): int
    {
        if ($faq === null || $faq === '' || $faq === []) {
            return 0;
        }
        $rows = is_string($faq) ? json_decode($faq, true) : $faq;
        return is_array($rows) ? count($rows) : -1;
    }

    /**
     * 组装入库字段（纯函数）
     * @param array $data
     * @return array
     */
    public static function buildPayload(array $data): array
    {
        $enable = (int)($data['enable'] ?? AiConfig::ENABLE_OFF);
        return [
            'enable' => $enable === AiConfig::ENABLE_ON ? AiConfig::ENABLE_ON : AiConfig::ENABLE_OFF,
            'mode' => trim((string)($data['mode'] ?? AiConfig::MODE_STANDBY)),
            'greeting' => trim((string)($data['greeting'] ?? '')),
            'system_prompt' => trim((string)($data['system_prompt'] ?? '')),
            'faq' => json_encode(self::normalizeFaq($data['faq'] ?? []), JSON_UNESCAPED_UNICODE),
            'transfer_keywords' => self::normalizeKeywords($data['transfer_keywords'] ?? ''),
            'model' => trim((string)($data['model'] ?? '')),
        ];
    }

    /**
     * 归一化转人工关键词为去重的逗号分隔串（纯函数）
     * @param mixed $keywords 数组或逗号分隔串
     * @return string
     */
    public static function normalizeKeywords($keywords): string
    {
        $items = is_array($keywords) ? $keywords : explode(AiConfig::KEYWORD_SEPARATOR, (string)$keywords);
        $items = array_map(function ($item) {
            return is_scalar($item) ? trim((string)$item) : '';
        }, $items);
        return implode(AiConfig::KEYWORD_SEPARATOR, array_unique(array_filter($items, 'strlen')));
    }

    /**
     * 校验模式与各字段长度
     * @param array $payload
     * @return void
     */
    protected function validatePayload(array $payload): void
    {
        if (!in_array($payload['mode'], AiConfig::MODES, true)) {
            throw new AdminException('接待模式不合法');
        }
        if (mb_strlen($payload['system_prompt']) > AiPrompt::MAX_SYSTEM_PROMPT) {
            throw new AdminException('身份设定最多' . AiPrompt::MAX_SYSTEM_PROMPT . '个字');
        }
        if (mb_strlen($payload['greeting']) > AiConfig::MAX_GREETING) {
            throw new AdminException('欢迎语最多' . AiConfig::MAX_GREETING . '个字');
        }
        if (mb_strlen($payload['transfer_keywords']) > AiConfig::MAX_TRANSFER_KEYWORDS) {
            throw new AdminException('转人工关键词最多' . AiConfig::MAX_TRANSFER_KEYWORDS . '个字');
        }
    }

    /**
     * 校验FAQ结构与体积：归一化后条数少于原始条数说明存在缺问或缺答的脏数据
     * @param string $encoded 归一化后的json
     * @param int $rawCount 原始条数
     * @return void
     */
    protected function validateFaq(string $encoded, int $rawCount): void
    {
        $rows = json_decode($encoded, true);
        if ($rawCount < 0 || count(is_array($rows) ? $rows : []) !== $rawCount) {
            throw new AdminException('FAQ格式不正确，每条需包含问题q与答案a');
        }
        if (mb_strlen($encoded) > AiPrompt::MAX_FAQ) {
            throw new AdminException('FAQ内容过多，请精简后再保存');
        }
    }
}
