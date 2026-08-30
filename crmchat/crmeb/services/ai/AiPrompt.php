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

namespace crmeb\services\ai;

use app\services\chat\ChatServiceDialogueRecordServices;

/**
 * AI提示词组装
 *
 * 全部为无副作用的静态纯函数：不读配置、不查库，
 * 便于在无框架上下文的单元测试中验证截断与角色映射规则。
 * Class AiPrompt
 * @package crmeb\services\ai
 */
class AiPrompt
{
    /**
     * 身份设定最大长度
     */
    const MAX_SYSTEM_PROMPT = 4000;

    /**
     * FAQ拼装后最大长度
     */
    const MAX_FAQ = 8000;

    /**
     * 携带的历史消息条数
     */
    const MAX_HISTORY = 10;

    /**
     * 单条消息最大长度
     */
    const MAX_SINGLE_MSG = 500;

    /**
     * 商品名、订单号等引用信息最大长度
     */
    const MAX_SUBJECT = 100;

    /**
     * 字符编码
     */
    const ENCODING = 'UTF-8';

    const ROLE_SYSTEM = 'system';
    const ROLE_USER = 'user';
    const ROLE_ASSISTANT = 'assistant';

    /**
     * 关键词分隔符：中英文逗号均需兼容后台录入习惯
     */
    const SEPARATOR = ',';
    const SEPARATOR_CN = '，';

    /**
     * 未配置身份设定时的兜底人设
     */
    const DEFAULT_IDENTITY = '你是一名专业、友好的在线客服助手，负责解答用户的咨询。';

    /**
     * FAQ段落引导语
     */
    const FAQ_TITLE = '以下是常见问题与标准答案，回答时优先参考：';

    /**
     * 行为约束，抑制模型编造并引导及时转人工
     */
    const BEHAVIOR_RULES = "回答时必须遵守以下要求：\n1. 只回答与本业务相关的问题，无关话题礼貌拒绝；\n2. 不确定答案，或用户涉及退款、投诉、账号安全等敏感诉求时，建议用户转接人工客服；\n3. 不编造商品、价格、库存、物流、活动等资料中没有的信息；\n4. 回答简洁直接，控制在三句话以内，使用与用户相同的语言。";

    /**
     * 组装system提示词
     * @param array $config AI配置，含system_prompt/faq
     * @return string
     */
    public static function buildSystemPrompt(array $config): string
    {
        $identity = self::truncate(trim((string)($config['system_prompt'] ?? '')), self::MAX_SYSTEM_PROMPT);
        if ($identity === '') {
            $identity = self::DEFAULT_IDENTITY;
        }
        $sections = [$identity];
        $faq = self::buildFaq($config['faq'] ?? '');
        if ($faq !== '') {
            $sections[] = self::FAQ_TITLE . "\n" . $faq;
        }
        $sections[] = self::BEHAVIOR_RULES;
        return implode("\n\n", $sections);
    }

    /**
     * 组装OpenAI messages
     * @param array $config AI配置
     * @param array $history 历史消息，每条{role,content}
     * @param string $question 本轮提问
     * @return array
     */
    public static function buildMessages(array $config, array $history, string $question): array
    {
        $system = [['role' => self::ROLE_SYSTEM, 'content' => self::buildSystemPrompt($config)]];
        $context = array_values(array_filter(array_map([self::class, 'normalizeMessage'], $history)));
        $current = [[
            'role' => self::ROLE_USER,
            'content' => self::truncate(trim($question), self::MAX_SINGLE_MSG),
        ]];
        return array_merge($system, $context, $current);
    }

    /**
     * 对话记录转历史消息
     * @param array $records 对话记录，每条含user_id/msn/msn_type/other
     * @param int $aiUserId AI虚拟坐席的user_id
     * @return array
     */
    public static function normalizeHistory(array $records, int $aiUserId): array
    {
        $messages = array_map(function ($record) use ($aiUserId) {
            return self::recordToMessage(is_array($record) ? $record : [], $aiUserId);
        }, $records);
        $messages = array_values(array_filter($messages));
        if (count($messages) > self::MAX_HISTORY) {
            return array_slice($messages, -self::MAX_HISTORY);
        }
        return $messages;
    }

    /**
     * 非文本消息转译为模型可理解的描述
     * @param int $msnType 消息类型
     * @param array $other 消息附加信息
     * @return string
     */
    public static function describeNonText(int $msnType, array $other): string
    {
        switch ($msnType) {
            case ChatServiceDialogueRecordServices::MSN_TYPE_EMOT:
                return '[表情]';
            case ChatServiceDialogueRecordServices::MSN_TYPE_IME:
                return '[用户发送了一张图片]';
            case ChatServiceDialogueRecordServices::MSN_TYPE_VOICE:
                return '[用户发送了一条语音]';
            case ChatServiceDialogueRecordServices::MSN_TYPE_GOODS:
                return self::describeSubject('用户咨询商品', $other, ['title', 'store_name']);
            case ChatServiceDialogueRecordServices::MSN_TYPE_ORDER:
                return self::describeSubject('用户咨询订单', $other, ['order_id', 'order_sn']);
        }
        return '';
    }

    /**
     * 是否命中转人工关键词
     * @param string $msg 用户消息
     * @param string $keywords 逗号分隔的关键词
     * @return bool
     */
    public static function matchTransferKeyword(string $msg, string $keywords): bool
    {
        $needles = self::splitKeywords($keywords);
        $haystack = mb_strtolower(trim($msg), self::ENCODING);
        if (!$needles || $haystack === '') {
            return false;
        }
        foreach ($needles as $needle) {
            if (mb_strpos($haystack, $needle, 0, self::ENCODING) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 按字符数截断，避免截断多字节字符
     * @param string $text
     * @param int $max
     * @return string
     */
    public static function truncate(string $text, int $max): string
    {
        if ($max <= 0) {
            return '';
        }
        if (mb_strlen($text, self::ENCODING) <= $max) {
            return $text;
        }
        return mb_substr($text, 0, $max, self::ENCODING);
    }

    /**
     * FAQ问答对拼装
     * @param mixed $faq json字符串或数组
     * @return string
     */
    protected static function buildFaq($faq): string
    {
        $items = is_string($faq) ? json_decode($faq, true) : $faq;
        if (!is_array($items)) {
            return '';
        }
        $lines = array_filter(array_map([self::class, 'formatFaqItem'], $items));
        return self::truncate(implode("\n", $lines), self::MAX_FAQ);
    }

    /**
     * 单条问答对格式化
     * @param mixed $item
     * @return string
     */
    protected static function formatFaqItem($item): string
    {
        if (!is_array($item)) {
            return '';
        }
        $question = trim((string)($item['q'] ?? ''));
        $answer = trim((string)($item['a'] ?? ''));
        if ($question === '' || $answer === '') {
            return '';
        }
        return 'Q: ' . $question . "\nA: " . $answer;
    }

    /**
     * 历史消息规范化，非法或空内容丢弃
     * @param mixed $message
     * @return array
     */
    protected static function normalizeMessage($message): array
    {
        if (!is_array($message)) {
            return [];
        }
        $role = (string)($message['role'] ?? '');
        $content = self::truncate(trim((string)($message['content'] ?? '')), self::MAX_SINGLE_MSG);
        if ($content === '' || !in_array($role, [self::ROLE_USER, self::ROLE_ASSISTANT], true)) {
            return [];
        }
        return ['role' => $role, 'content' => $content];
    }

    /**
     * 单条对话记录转消息
     * @param array $record
     * @param int $aiUserId
     * @return array
     */
    protected static function recordToMessage(array $record, int $aiUserId): array
    {
        $content = self::recordContent($record);
        if ($content === '') {
            return [];
        }
        $isAi = $aiUserId > 0 && (int)($record['user_id'] ?? 0) === $aiUserId;
        return [
            'role' => $isAi ? self::ROLE_ASSISTANT : self::ROLE_USER,
            'content' => $content,
        ];
    }

    /**
     * 提取记录正文
     * @param array $record
     * @return string
     */
    protected static function recordContent(array $record): string
    {
        $msnType = (int)($record['msn_type'] ?? ChatServiceDialogueRecordServices::MSN_TYPE_TXT);
        if ($msnType !== ChatServiceDialogueRecordServices::MSN_TYPE_TXT) {
            return self::describeNonText($msnType, self::toArray($record['other'] ?? []));
        }
        return self::truncate(trim((string)($record['msn'] ?? '')), self::MAX_SINGLE_MSG);
    }

    /**
     * 商品、订单类消息的引用描述
     * @param string $label 描述前缀
     * @param array $other 附加信息
     * @param array $keys 取值字段优先级
     * @return string
     */
    protected static function describeSubject(string $label, array $other, array $keys): string
    {
        $values = array_filter(array_map(function ($key) use ($other) {
            return trim((string)($other[$key] ?? ''));
        }, $keys));
        if (!$values) {
            return '[' . $label . ']';
        }
        return '[' . $label . '：' . self::truncate((string)reset($values), self::MAX_SUBJECT) . ']';
    }

    /**
     * other字段在不同来源下可能是json字符串
     * @param mixed $value
     * @return array
     */
    protected static function toArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 关键词切分
     * @param string $keywords
     * @return array
     */
    protected static function splitKeywords(string $keywords): array
    {
        $normalized = str_replace(self::SEPARATOR_CN, self::SEPARATOR, $keywords);
        $items = array_map(function ($item) {
            return mb_strtolower(trim($item), self::ENCODING);
        }, explode(self::SEPARATOR, $normalized));
        return array_values(array_filter($items, function ($item) {
            return $item !== '';
        }));
    }
}
