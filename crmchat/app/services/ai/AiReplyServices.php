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


use app\models\chat\ChatServiceDialogueRecord;
use app\services\chat\ChatServiceDialogueRecordServices;
use app\services\chat\ChatServiceRecordServices;
use app\services\chat\ChatUserServices;
use app\services\TenantPlanServices;
use crmeb\basic\BaseServices;
use crmeb\services\ai\AiClient;
use crmeb\services\ai\AiPrompt;
use crmeb\services\SwooleTaskService;
use crmeb\services\tenant\TenantContext;
use think\facade\Log;

/**
 * AI回复主流程（在task进程内执行）
 *
 * 不复用BaseHandler::chat()落库推送，避免AI回复再次触发聊天链路形成回环，
 * 这与现有autoReply的处理方式一致
 * Class AiReplyServices
 * @package app\services\ai
 */
class AiReplyServices extends BaseServices
{

    /**
     * LLM不可用或超限时的兜底话术
     */
    const FALLBACK_BUSY = '抱歉，我这边暂时无法回复，您可以稍后再试或输入"人工"联系人工客服。';
    const FALLBACK_QUOTA = '今日智能客服服务量已达上限，您可以输入"人工"联系人工客服。';
    const FALLBACK_LIMIT = '您的提问有点频繁，请稍后再试，或输入"人工"联系人工客服。';
    const FALLBACK_MEDIA = '我暂时无法识别图片和语音，麻烦您用文字描述一下，或输入"人工"联系人工客服。';

    /**
     * 非文本消息中仅这两类可转译为文本继续问模型
     */
    const TRANSLATABLE_TYPES = [
        ChatServiceDialogueRecordServices::MSN_TYPE_GOODS,
        ChatServiceDialogueRecordServices::MSN_TYPE_ORDER,
    ];

    /**
     * AiReplyServices constructor.
     * @param ChatServiceDialogueRecordServices $services
     */
    public function __construct(ChatServiceDialogueRecordServices $services)
    {
        $this->services = $services;
    }

    /**
     * 处理一条AI回复任务
     * @param array $payload 见 BaseHandler 投递结构
     * @return void
     */
    public function handle(array $payload)
    {
        $ctx = self::normalizePayload($payload);
        if (!$ctx['appid'] || !$ctx['user_id'] || !$ctx['ai_user_id']) {
            Log::error('AI回复任务上下文不完整');
            return;
        }
        $question = $this->resolveQuestion($ctx);
        if ($question === '') {
            $this->reply($ctx, self::FALLBACK_MEDIA, ChatServiceDialogueRecord::SOURCE_AI_FALLBACK);
            return;
        }
        $result = $this->askModel($ctx, $question);
        $this->reply($ctx, $result['content'], $result['source']);
    }

    /**
     * 规整任务载荷，避免后续到处做类型转换
     * @param array $payload
     * @return array
     */
    public static function normalizePayload(array $payload): array
    {
        return [
            'appid' => (string)($payload['appid'] ?? ''),
            'user_id' => (int)($payload['user_id'] ?? 0),
            'ai_user_id' => (int)($payload['ai_user_id'] ?? 0),
            'msn' => (string)($payload['msn'] ?? ''),
            'msn_type' => (int)($payload['msn_type'] ?? ChatServiceDialogueRecordServices::MSN_TYPE_TXT),
            'other' => is_array($payload['other'] ?? null) ? $payload['other'] : [],
            'is_tourist' => (int)($payload['is_tourist'] ?? 0),
            'tenant_id' => (int)($payload['tenant_id'] ?? TenantContext::id()),
        ];
    }

    /**
     * 得到可提问的文本：非文本消息按类型转译或拒答
     * @param array $ctx
     * @return string
     */
    protected function resolveQuestion(array $ctx): string
    {
        if ($ctx['msn_type'] === ChatServiceDialogueRecordServices::MSN_TYPE_TXT) {
            return trim($ctx['msn']);
        }
        if (in_array($ctx['msn_type'], self::TRANSLATABLE_TYPES, true)) {
            return AiPrompt::describeNonText($ctx['msn_type'], $ctx['other']);
        }
        return '';
    }

    /**
     * 调用模型并记录用量，任何失败都返回兜底话术
     * @param array $ctx
     * @param string $question
     * @return array ['content' => string, 'source' => int]
     */
    protected function askModel(array $ctx, string $question): array
    {
        $client = AiClient::fromEnv();
        if (!$client->isReady()) {
            Log::error('AI客服未配置API密钥');
            return ['content' => self::FALLBACK_BUSY, 'source' => ChatServiceDialogueRecord::SOURCE_AI_FALLBACK];
        }
        /** @var AiConfigServices $configServices */
        $configServices = app()->make(AiConfigServices::class);
        $config = $configServices->getConfig($ctx['tenant_id']);
        $history = $this->loadHistory($ctx);
        $messages = AiPrompt::buildMessages($config, $history, $question);

        $result = $client->chat($messages, (string)($config['model'] ?? ''));
        $this->recordUsage($ctx, $result);
        if (empty($result['success'])) {
            return ['content' => self::FALLBACK_BUSY, 'source' => ChatServiceDialogueRecord::SOURCE_AI_FALLBACK];
        }
        return ['content' => $result['content'], 'source' => ChatServiceDialogueRecord::SOURCE_AI];
    }

    /**
     * 取该会话最近若干条对话作为上下文
     * @param array $ctx
     * @return array
     */
    protected function loadHistory(array $ctx): array
    {
        try {
            $records = $this->services->getServiceChatList([
                'appid' => $ctx['appid'],
                'to_user_id' => $ctx['user_id'],
            ], AiPrompt::MAX_HISTORY, 0);
            $rows = is_array($records) ? $records : $records->toArray();
            return AiPrompt::normalizeHistory(array_reverse($rows), $ctx['ai_user_id']);
        } catch (\Throwable $e) {
            //上下文缺失只降低回答质量，不应阻断回复
            Log::error('AI上下文读取失败：' . $e->getMessage());
            return [];
        }
    }

    /**
     * 写入调用流水（平台级表，失败不影响回复）
     * @param array $ctx
     * @param array $result
     * @return void
     */
    protected function recordUsage(array $ctx, array $result)
    {
        /** @var AiUsageServices $usageServices */
        $usageServices = app()->make(AiUsageServices::class);
        $usageServices->record([
            'tenant_id' => $ctx['tenant_id'],
            'appid' => $ctx['appid'],
            'user_id' => $ctx['user_id'],
            'model' => $result['model'] ?? '',
            'prompt_tokens' => $result['prompt_tokens'] ?? 0,
            'completion_tokens' => $result['completion_tokens'] ?? 0,
            'duration_ms' => $result['duration_ms'] ?? 0,
            'status' => $result['status'] ?? AiClient::STATUS_FAIL,
            'error' => $result['error'] ?? '',
        ]);
    }

    /**
     * 以AI坐席身份落库并推送给访客与工作台
     * @param array $ctx
     * @param string $content
     * @param int $source
     * @return void
     */
    public function reply(array $ctx, string $content, int $source)
    {
        $content = trim($content);
        if ($content === '') {
            return;
        }
        try {
            $data = $this->saveReply($ctx, $content, $source);
            $this->pushReply($ctx, $data);
            $this->markRead($ctx);
        } catch (\Throwable $e) {
            Log::error('AI回复落库或推送失败：' . $e->getMessage());
        }
    }

    /**
     * 落库AI消息并更新会话摘要
     * @param array $ctx
     * @param string $content
     * @param int $source
     * @return array
     */
    protected function saveReply(array $ctx, string $content, int $source): array
    {
        $record = $this->services->save([
            'appid' => $ctx['appid'],
            'user_id' => $ctx['ai_user_id'],
            'to_user_id' => $ctx['user_id'],
            'msn' => $content,
            'msn_type' => ChatServiceDialogueRecordServices::MSN_TYPE_TXT,
            'source' => $source,
            'other' => '',
            'type' => 1,
            'is_send' => 1,
            'add_time' => time(),
        ]);
        $data = $record->toArray();
        $data['_add_time'] = $data['add_time'];
        $data['add_time'] = is_numeric($data['add_time']) ? (int)$data['add_time'] : strtotime($data['add_time']);

        /** @var ChatUserServices $userService */
        $userService = app()->make(ChatUserServices::class);
        //推送给访客的消息署AI坐席的名，而会话摘要要显示对端(访客)的名
        $aiInfo = $userService->getUserInfo($ctx['ai_user_id'], ['nickname', 'avatar']);
        $visitorInfo = $userService->getUserInfo($ctx['user_id'], ['nickname', 'avatar']);
        $data['nickname'] = $aiInfo['nickname'] ?? '';
        $data['avatar'] = $aiInfo['avatar'] ?? '';

        /** @var ChatServiceRecordServices $recordServices */
        $recordServices = app()->make(ChatServiceRecordServices::class);
        //saveRecord内部会反转两个id落库，故按"访客,坐席"顺序传入，
        //这样生成的摘要行user_id=AI坐席，与工作台会话列表口径一致
        $data['recored'] = $recordServices->saveRecord(
            $ctx['appid'],
            $ctx['user_id'],
            $ctx['ai_user_id'],
            $content,
            0,
            ChatServiceDialogueRecordServices::MSN_TYPE_TXT,
            0,
            $ctx['is_tourist'],
            $visitorInfo['nickname'] ?? '',
            $visitorInfo['avatar'] ?? '',
            0
        );
        return $data;
    }

    /**
     * 推送给访客；AI会话在工作台可见，同步推一份便于人工介入
     * @param array $ctx
     * @param array $data
     * @return void
     */
    protected function pushReply(array $ctx, array $data)
    {
        SwooleTaskService::user()->to($ctx['user_id'])->type('reply')->data($data)->push();
        SwooleTaskService::kefu()->to($ctx['ai_user_id'])->type('chat')->data($data)->push();
    }

    /**
     * AI永不打开会话，需主动把访客发来的消息置为已读，否则未读数单调膨胀污染工作台
     * @param array $ctx
     * @return void
     */
    protected function markRead(array $ctx)
    {
        try {
            $this->services->update(
                ['user_id' => $ctx['user_id'], 'to_user_id' => $ctx['ai_user_id'], 'type' => 0],
                ['type' => 1]
            );
            /** @var ChatServiceRecordServices $recordServices */
            $recordServices = app()->make(ChatServiceRecordServices::class);
            $recordServices->update(
                ['user_id' => $ctx['ai_user_id'], 'to_user_id' => $ctx['user_id']],
                ['mssage_num' => 0]
            );
        } catch (\Throwable $e) {
            Log::error('AI会话已读标记失败：' . $e->getMessage());
        }
    }

    /**
     * 派发前的准入判定：功能位、日配额、访客限频（全部fail-closed）
     * @param array $ctx tenant_id/user_id/is_tourist
     * @return string 空串=准入通过，否则为应直接回复的兜底话术
     */
    public function checkAdmission(array $ctx): string
    {
        /** @var TenantPlanServices $planServices */
        $planServices = app()->make(TenantPlanServices::class);
        if (!$planServices->canUseAi((int)$ctx['tenant_id'])) {
            return self::FALLBACK_QUOTA;
        }
        /** @var AiRateLimiter $limiter */
        $limiter = app()->make(AiRateLimiter::class);
        if (!$limiter->allow((int)$ctx['user_id'], !empty($ctx['is_tourist']))) {
            return self::FALLBACK_LIMIT;
        }
        //计数放在准入阶段一次性完成，task内失败重试不会重复扣减配额
        if (!$planServices->checkDailyAi((int)$ctx['tenant_id'])) {
            return self::FALLBACK_QUOTA;
        }
        return '';
    }
}
