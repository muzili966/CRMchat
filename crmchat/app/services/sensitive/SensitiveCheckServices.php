<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\sensitive;

use app\dao\SensitiveHitDao;
use app\models\SensitiveHit;
use app\models\SensitiveWord;
use app\services\TenantPlanServices;
use crmeb\basic\BaseServices;
use crmeb\services\tenant\TenantContext;
use crmeb\utils\SensitiveFilter;
use think\facade\Log;

/**
 * 敏感词检查
 *
 * 消息收发与AI回复的统一检查入口。命中即留痕——过滤本身挡不住有心绕过，
 * 留痕才是这个功能真正的价值：谁在什么时候发了什么、系统怎么处置的可查可举证。
 *
 * 任何内部异常都不得中断消息收发：检查失败时放行并记日志，
 * 让聊天因过滤器故障而中断，代价远大于漏过一条待审内容。
 * Class SensitiveCheckServices
 * @package app\services\sensitive
 */
class SensitiveCheckServices extends BaseServices
{
    /**
     * @var SensitiveWordServices
     */
    protected $wordServices;

    /**
     * SensitiveCheckServices constructor.
     * @param SensitiveHitDao $dao
     * @param SensitiveWordServices $wordServices
     */
    public function __construct(SensitiveHitDao $dao, SensitiveWordServices $wordServices)
    {
        $this->dao = $dao;
        $this->wordServices = $wordServices;
    }

    /**
     * 检查一段文本
     *
     * @param string $text 待检文本
     * @param int $scope 来源，SensitiveFilter::SCOPE_*
     * @param array $context appid/user_id/to_user_id/nickname，仅用于留痕
     * @return array ['blocked'=>bool, 'text'=>string, 'words'=>array]
     */
    public function check(string $text, int $scope, array $context = []): array
    {
        $pass = ['blocked' => false, 'text' => $text, 'words' => []];
        if (trim($text) === '') {
            return $pass;
        }
        try {
            $tenantId = (int)TenantContext::id();
            $hits = $this->hits($text, $scope, $tenantId);
            if (!$hits) {
                return $pass;
            }
            $action = SensitiveFilter::severestAction($hits);
            $result = [
                'blocked' => $action === SensitiveFilter::ACTION_BLOCK,
                'text' => $action === SensitiveFilter::ACTION_REPLACE ? SensitiveFilter::mask($text, $hits) : $text,
                'words' => array_values(array_unique(array_column(array_column($hits, 'meta'), 'word'))),
            ];
            $this->record($hits, $text, $scope, $action, $tenantId, $context);
            return $result;
        } catch (\Throwable $e) {
            //检查器故障不能拖垮聊天，放行并留日志
            Log::error('敏感词检查失败：' . $e->getMessage());
            return $pass;
        }
    }

    /**
     * 取本次生效的命中项
     * @param string $text
     * @param int $scope
     * @param int $tenantId
     * @return array
     */
    protected function hits(string $text, int $scope, int $tenantId): array
    {
        $hits = SensitiveFilter::detect($this->wordServices->trie($tenantId), $text);
        if (!$hits) {
            return [];
        }
        $tenantAllowed = $this->tenantWordsAllowed($tenantId);
        return array_values(array_filter($hits, function ($hit) use ($scope, $tenantAllowed) {
            $meta = $hit['meta'];
            //词条未覆盖本次来源时不生效
            if (!((int)($meta['scope'] ?? 0) & $scope)) {
                return false;
            }
            //平台级合规词无条件生效，租户自定义词受套餐约束
            return (int)($meta['tenant_id'] ?? 0) === SensitiveWord::PLATFORM_TENANT || $tenantAllowed;
        }));
    }

    /**
     * 租户自定义词是否生效
     *
     * 套餐能力查询失败时按生效处理：宁可多拦一条，不可让付费能力静默失效。
     * @param int $tenantId
     * @return bool
     */
    protected function tenantWordsAllowed(int $tenantId): bool
    {
        if ($tenantId === SensitiveWord::PLATFORM_TENANT) {
            return true;
        }
        try {
            /** @var TenantPlanServices $planServices */
            $planServices = app()->make(TenantPlanServices::class);
            return (bool)$planServices->hasFeature($tenantId, 'sensitive_word');
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * 写入命中记录
     * @param array $hits
     * @param string $text
     * @param int $scope
     * @param int $action
     * @param int $tenantId
     * @param array $context
     * @return void
     */
    protected function record(array $hits, string $text, int $scope, int $action, int $tenantId, array $context)
    {
        $now = time();
        $content = mb_substr($text, 0, SensitiveHit::CONTENT_MAX_LEN);
        $rows = [];
        foreach ($hits as $hit) {
            $meta = $hit['meta'];
            $rows[] = [
                'tenant_id' => $tenantId,
                'word_id' => (int)($meta['id'] ?? 0),
                'word' => (string)($meta['word'] ?? ''),
                'is_platform' => (int)((int)($meta['tenant_id'] ?? 0) === SensitiveWord::PLATFORM_TENANT),
                'action' => $action,
                'scope' => $scope,
                'appid' => mb_substr((string)($context['appid'] ?? ''), 0, 32),
                'user_id' => (int)($context['user_id'] ?? 0),
                'to_user_id' => (int)($context['to_user_id'] ?? 0),
                'nickname' => mb_substr((string)($context['nickname'] ?? ''), 0, 64),
                'content' => $content,
                'handled' => SensitiveHit::HANDLED_NO,
                'create_time' => $now,
            ];
        }
        $this->dao->saveAll($rows);
    }
}
