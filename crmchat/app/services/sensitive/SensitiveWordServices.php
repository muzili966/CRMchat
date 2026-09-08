<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\sensitive;

use app\dao\SensitiveWordDao;
use app\models\SensitiveWord;
use crmeb\basic\BaseServices;
use crmeb\exceptions\AdminException;
use crmeb\services\CacheService;
use crmeb\services\tenant\TenantContext;
use crmeb\utils\SensitiveFilter;

/**
 * 敏感词库
 *
 * 平台级（tenant_id=0）是合规词，对所有租户强制生效、租户不可见不可改；
 * 租户级是业务词（防飞单等），由租户自行维护。
 * Class SensitiveWordServices
 * @package app\services\sensitive
 */
class SensitiveWordServices extends BaseServices
{
    /**
     * 词库版本号缓存键，任一词条变更即自增
     */
    const VERSION_KEY = 'sensitive_word_version';

    /**
     * 进程内匹配树的版本复检间隔（秒）
     *
     * check() 在消息热路径上逐条调用，每次都去 Redis 取版本会平白加一次往返；
     * 词库变更延迟数秒生效完全可接受，故按此间隔复检。
     */
    const VERSION_TTL = 5;

    /**
     * 列表每页条数上限
     */
    const MAX_LIMIT = 200;

    /**
     * 进程内匹配树缓存 [tenantId => ['version'=>, 'trie'=>, 'at'=>]]
     * @var array
     */
    protected static $tries = [];

    /**
     * SensitiveWordServices constructor.
     * @param SensitiveWordDao $dao
     */
    public function __construct(SensitiveWordDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 取指定租户可用的匹配树（含平台级词）
     * @param int $tenantId
     * @return array
     */
    public function trie(int $tenantId): array
    {
        $cached = self::$tries[$tenantId] ?? null;
        if ($cached && $cached['at'] + self::VERSION_TTL > time()) {
            return $cached['trie'];
        }
        $version = $this->version();
        if ($cached && $cached['version'] === $version) {
            self::$tries[$tenantId]['at'] = time();
            return $cached['trie'];
        }
        $trie = SensitiveFilter::build($this->dao->activeWords($tenantId));
        self::$tries[$tenantId] = ['version' => $version, 'trie' => $trie, 'at' => time()];
        return $trie;
    }

    /**
     * 词库版本号
     *
     * Redis 不可用时回落到固定值：此时匹配树仍按 VERSION_TTL 定期重建，
     * 不会让消息收发因缓存故障中断。
     * @return string
     */
    protected function version(): string
    {
        try {
            return (string)CacheService::get(self::VERSION_KEY, '0');
        } catch (\Throwable $e) {
            return '0';
        }
    }

    /**
     * 词库变更后推进版本，使各进程的匹配树失效
     * @return void
     */
    public function bumpVersion()
    {
        self::$tries = [];
        try {
            CacheService::set(self::VERSION_KEY, (string)microtime(true));
        } catch (\Throwable $e) {
            //缓存不可用不该让词条保存失败，各进程会在 VERSION_TTL 后自行重建
        }
    }

    /**
     * 词条列表
     * @param array $where
     * @return array
     */
    public function getList(array $where): array
    {
        $search = [
            'tenant_id' => $this->scopeTenantId(),
            'status' => $where['status'] ?? '',
            'category' => $where['category'] ?? '',
            'keyword' => $where['keyword'] ?? '',
        ];
        [$page, $limit] = $this->pageValue($where);
        return [
            'list' => array_map([$this, 'format'], $this->dao->getWordList($search, $page, $limit)),
            'count' => $this->dao->count($search),
        ];
    }

    /**
     * 新增词条
     * @param array $data word/category/action/scope/status/remark
     * @return array
     */
    public function create(array $data): array
    {
        $tenantId = $this->scopeTenantId();
        $this->assertEditable($tenantId);
        $word = $this->normalizeWord((string)($data['word'] ?? ''));
        if ($this->dao->existingWords($tenantId, [$word])) {
            throw new AdminException('该词条已存在');
        }
        $now = time();
        $saved = $this->dao->save([
            'tenant_id' => $tenantId,
            'word' => $word,
            'category' => mb_substr((string)($data['category'] ?? ''), 0, 32),
            'action' => $this->normalizeAction($data['action'] ?? null),
            'scope' => $this->normalizeScope($data['scope'] ?? null),
            'status' => (int)($data['status'] ?? SensitiveWord::STATUS_ON) ? SensitiveWord::STATUS_ON : SensitiveWord::STATUS_OFF,
            'remark' => mb_substr((string)($data['remark'] ?? ''), 0, 255),
            'create_time' => $now,
            'update_time' => $now,
        ]);
        if (!$saved) {
            throw new AdminException('新增失败');
        }
        $this->bumpVersion();
        return $this->format($saved->toArray());
    }

    /**
     * 修改词条
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function modify(int $id, array $data): bool
    {
        $word = $this->ownWord($id);
        $this->assertEditable((int)$word['tenant_id']);
        $update = ['update_time' => time()];
        if (isset($data['word'])) {
            $update['word'] = $this->normalizeWord((string)$data['word']);
            //改名撞上同租户已有词条时给出明确提示，而不是抛数据库唯一键错误
            if ($update['word'] !== $word['word'] && $this->dao->existingWords((int)$word['tenant_id'], [$update['word']])) {
                throw new AdminException('该词条已存在');
            }
        }
        foreach (['category' => 32, 'remark' => 255] as $field => $len) {
            if (isset($data[$field])) {
                $update[$field] = mb_substr((string)$data[$field], 0, $len);
            }
        }
        if (isset($data['action'])) {
            $update['action'] = $this->normalizeAction($data['action']);
        }
        if (isset($data['scope'])) {
            $update['scope'] = $this->normalizeScope($data['scope']);
        }
        if (isset($data['status'])) {
            $update['status'] = (int)$data['status'] ? SensitiveWord::STATUS_ON : SensitiveWord::STATUS_OFF;
        }
        $this->dao->update($id, $update);
        $this->bumpVersion();
        return true;
    }

    /**
     * 删除词条
     * @param int $id
     * @return bool
     */
    public function remove(int $id): bool
    {
        $word = $this->ownWord($id);
        $this->assertEditable((int)$word['tenant_id']);
        $this->dao->delete($id);
        $this->bumpVersion();
        return true;
    }

    /**
     * 批量导入：一行一词，已存在的跳过
     * @param array $data words/category/action/scope
     * @return array ['added' => int, 'skipped' => int]
     */
    public function import(array $data): array
    {
        $tenantId = $this->scopeTenantId();
        $this->assertEditable($tenantId);
        $words = $this->parseWords((string)($data['words'] ?? ''));
        if (!$words) {
            throw new AdminException('没有可导入的词条');
        }
        $exists = $this->dao->existingWords($tenantId, $words);
        $fresh = array_values(array_diff($words, $exists));
        if (!$fresh) {
            return ['added' => 0, 'skipped' => count($words)];
        }
        $now = time();
        $action = $this->normalizeAction($data['action'] ?? null);
        $scope = $this->normalizeScope($data['scope'] ?? null);
        $category = mb_substr((string)($data['category'] ?? ''), 0, 32);
        $this->dao->saveAll(array_map(function ($word) use ($tenantId, $category, $action, $scope, $now) {
            return [
                'tenant_id' => $tenantId,
                'word' => $word,
                'category' => $category,
                'action' => $action,
                'scope' => $scope,
                'status' => SensitiveWord::STATUS_ON,
                'remark' => '',
                'create_time' => $now,
                'update_time' => $now,
            ];
        }, $fresh));
        $this->bumpVersion();
        return ['added' => count($fresh), 'skipped' => count($words) - count($fresh)];
    }

    /**
     * 把多行文本拆成去重后的词条
     * @param string $text
     * @return array
     */
    public function parseWords(string $text): array
    {
        $parts = preg_split('/[\r\n,，、]+/u', $text) ?: [];
        $words = [];
        foreach ($parts as $part) {
            $word = trim($part);
            if ($word === '') {
                continue;
            }
            $words[] = mb_substr($word, 0, SensitiveWord::WORD_MAX_LEN);
            if (count($words) >= SensitiveWord::IMPORT_MAX) {
                break;
            }
        }
        return array_values(array_unique($words));
    }

    /**
     * 当前视角操作的词库归属
     *
     * 平台视角维护合规词（tenant_id=0），租户视角维护自己的业务词。
     * @return int
     */
    public function scopeTenantId(): int
    {
        return (int)TenantContext::id();
    }

    /**
     * 租户自定义词库属付费能力；平台级合规词不受套餐约束
     * @param int $tenantId
     * @return void
     */
    protected function assertEditable(int $tenantId)
    {
        if ($tenantId === SensitiveWord::PLATFORM_TENANT) {
            return;
        }
        /** @var \app\services\TenantPlanServices $planServices */
        $planServices = app()->make(\app\services\TenantPlanServices::class);
        $planServices->assertFeature($tenantId, 'sensitive_word', '当前套餐不支持自定义敏感词，请升级套餐');
    }

    /**
     * 取本视角下的词条，越权访问按不存在处理
     * @param int $id
     * @return array
     */
    protected function ownWord(int $id): array
    {
        $word = $this->dao->get($id);
        if (!$word || (int)$word['tenant_id'] !== $this->scopeTenantId()) {
            throw new AdminException('词条不存在');
        }
        return $word->toArray();
    }

    /**
     * @param string $word
     * @return string
     */
    protected function normalizeWord(string $word): string
    {
        $word = trim($word);
        if ($word === '') {
            throw new AdminException('词条不能为空');
        }
        return mb_substr($word, 0, SensitiveWord::WORD_MAX_LEN);
    }

    /**
     * @param mixed $action
     * @return int
     */
    protected function normalizeAction($action): int
    {
        $action = (int)$action;
        return in_array($action, SensitiveFilter::ACTIONS, true) ? $action : SensitiveFilter::ACTION_REPLACE;
    }

    /**
     * @param mixed $scope
     * @return int
     */
    protected function normalizeScope($scope): int
    {
        $scope = (int)$scope & SensitiveFilter::SCOPE_ALL;
        return $scope ?: SensitiveFilter::SCOPE_ALL;
    }

    /**
     * @param array $word
     * @return array
     */
    protected function format(array $word): array
    {
        $word['is_platform'] = (int)((int)($word['tenant_id'] ?? 0) === SensitiveWord::PLATFORM_TENANT);
        $word['_create_time'] = !empty($word['create_time']) ? date('Y-m-d H:i', (int)$word['create_time']) : '';
        return $word;
    }

    /**
     * @param array $where
     * @return array [page, limit]
     */
    protected function pageValue(array $where): array
    {
        $page = max(1, (int)($where['page'] ?? 1));
        $limit = (int)($where['limit'] ?? 20);
        $limit = $limit > 0 ? min($limit, self::MAX_LIMIT) : 20;
        return [$page, $limit];
    }
}
