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

use think\facade\Env;
use think\facade\Log;

/**
 * OpenAI兼容协议大模型客户端（DeepSeek/通义/智谱/OpenAI）
 *
 * 未复用 HttpService：其 https 请求关闭证书校验且缺少连接超时，
 * AI调用处于用户会话链路上，必须保证证书可信与快速失败。
 * Class AiClient
 * @package crmeb\services\ai
 */
class AiClient
{
    /**
     * 默认服务地址
     */
    const DEFAULT_BASE_URL = 'https://api.deepseek.com';

    /**
     * 默认模型
     */
    const DEFAULT_MODEL = 'deepseek-chat';

    /**
     * 默认响应超时（秒）
     */
    const DEFAULT_TIMEOUT = 30;

    /**
     * 建连超时（秒）
     */
    const CONNECT_TIMEOUT = 5;

    /**
     * 调用状态：成功
     */
    const STATUS_OK = 1;

    /**
     * 调用状态：失败
     */
    const STATUS_FAIL = 2;

    /**
     * 调用状态：超时
     */
    const STATUS_TIMEOUT = 3;

    /**
     * 单次回复最大token
     */
    const MAX_TOKENS = 800;

    /**
     * 采样温度
     */
    const TEMPERATURE = 0.7;

    /**
     * 对话补全接口路径
     */
    const CHAT_PATH = '/v1/chat/completions';

    /**
     * 成功的HTTP状态码
     */
    const HTTP_OK = 200;

    /**
     * 环境变量：服务地址
     */
    const ENV_BASE_URL = 'AI_BASE_URL';

    /**
     * 环境变量：接口密钥
     */
    const ENV_API_KEY = 'AI_API_KEY';

    /**
     * 系统配置：模型名
     */
    const CONFIG_BASE_URL = 'ai_base_url';

    const CONFIG_MODEL = 'ai_model';

    /**
     * 系统配置：超时时间
     */
    const CONFIG_TIMEOUT = 'ai_timeout';

    const ERROR_NO_KEY = 'AI密钥未配置';
    const ERROR_EMPTY_MESSAGES = '对话内容为空';
    const ERROR_ENCODE = '请求参数序列化失败';
    const ERROR_EMPTY_CONTENT = '模型返回空内容';
    const ERROR_DECODE = '响应内容解析失败';

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var string
     */
    protected $model;

    /**
     * @var int
     */
    protected $timeout;

    /**
     * AiClient constructor.
     * @param array $options base_url/api_key/model/timeout
     */
    public function __construct(array $options = [])
    {
        $baseUrl = rtrim(trim((string)($options['base_url'] ?? '')), '/');
        $model = trim((string)($options['model'] ?? ''));
        $timeout = (int)($options['timeout'] ?? 0);

        $this->baseUrl = $baseUrl !== '' ? $baseUrl : self::DEFAULT_BASE_URL;
        $this->apiKey = trim((string)($options['api_key'] ?? ''));
        $this->model = $model !== '' ? $model : self::DEFAULT_MODEL;
        $this->timeout = $timeout > 0 ? $timeout : self::DEFAULT_TIMEOUT;
    }

    /**
     * 由环境变量与系统配置构建客户端
     * @return static
     */
    public static function fromEnv(): self
    {
        return new self([
            'base_url' => self::readOr(function () {
                $configured = (string)sys_config(self::CONFIG_BASE_URL, '');
                return $configured !== '' ? $configured : Env::get(self::ENV_BASE_URL, self::DEFAULT_BASE_URL);
            }, self::DEFAULT_BASE_URL, self::ENV_BASE_URL),
            //密钥优先取后台配置（加密存储，平台可自助更换），未配置时回落环境变量
            'api_key' => self::readOr(function () {
                return app()->make(\app\services\ai\AiSecretServices::class)->getApiKey();
            }, '', self::ENV_API_KEY),
            'model' => self::readOr(function () {
                return sys_config(self::CONFIG_MODEL, self::DEFAULT_MODEL);
            }, self::DEFAULT_MODEL, self::CONFIG_MODEL),
            'timeout' => self::readOr(function () {
                return sys_config(self::CONFIG_TIMEOUT, self::DEFAULT_TIMEOUT);
            }, self::DEFAULT_TIMEOUT, self::CONFIG_TIMEOUT),
        ]);
    }

    /**
     * 是否具备调用条件
     * @return bool
     */
    public function isReady(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * 当前默认模型
     * @return string
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * 发起对话补全，异常一律转为失败结果，避免打断会话链路
     * @param array $messages OpenAI messages结构
     * @param string $model 模型覆盖，空=用默认
     * @return array
     */
    public function chat(array $messages, string $model = ''): array
    {
        $useModel = $model !== '' ? $model : $this->model;
        $start = microtime(true);
        try {
            return $this->request($messages, $useModel, $start);
        } catch (\Throwable $e) {
            Log::error('AI调用异常：' . $e->getMessage());
            return self::result([
                'model' => $useModel,
                'duration_ms' => self::elapsed($start),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 参数校验通过后执行请求
     * @param array $messages
     * @param string $model
     * @param float $start
     * @return array
     */
    protected function request(array $messages, string $model, float $start): array
    {
        if (!$this->isReady()) {
            return self::result(['model' => $model, 'error' => self::ERROR_NO_KEY]);
        }
        if (!$messages) {
            return self::result(['model' => $model, 'error' => self::ERROR_EMPTY_MESSAGES]);
        }
        $body = json_encode(self::payload($messages, $model), JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            return self::result(['model' => $model, 'error' => self::ERROR_ENCODE]);
        }
        return self::parse($this->send($body), [
            'model' => $model,
            'duration_ms' => self::elapsed($start),
        ]);
    }

    /**
     * 请求体
     * @param array $messages
     * @param string $model
     * @return array
     */
    protected static function payload(array $messages, string $model): array
    {
        return [
            'model' => $model,
            'messages' => array_values($messages),
            'temperature' => self::TEMPERATURE,
            'max_tokens' => self::MAX_TOKENS,
        ];
    }

    /**
     * 执行curl请求
     * @param string $body
     * @return array errno/error/http_code/body
     */
    protected function send(string $body): array
    {
        $curl = curl_init($this->baseUrl . self::CHAT_PATH);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
        ]);
        $content = curl_exec($curl);
        $response = [
            'errno' => curl_errno($curl),
            'error' => curl_error($curl),
            'http_code' => (int)curl_getinfo($curl, CURLINFO_HTTP_CODE),
            'body' => is_string($content) ? $content : '',
        ];
        curl_close($curl);
        return $response;
    }

    /**
     * 解析响应为统一结构
     * @param array $response send()的返回
     * @param array $meta model/duration_ms
     * @return array
     */
    protected static function parse(array $response, array $meta): array
    {
        $errno = (int)$response['errno'];
        if ($errno === CURLE_OPERATION_TIMEDOUT) {
            return self::result($meta + ['status' => self::STATUS_TIMEOUT, 'error' => $response['error']]);
        }
        if ($errno !== 0) {
            return self::result($meta + ['error' => $response['error']]);
        }
        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            return self::result($meta + ['error' => self::ERROR_DECODE]);
        }
        if ((int)$response['http_code'] !== self::HTTP_OK) {
            $message = (string)($data['error']['message'] ?? '');
            return self::result($meta + ['error' => $message !== '' ? $message : 'HTTP ' . $response['http_code']]);
        }
        return self::success($data, $meta);
    }

    /**
     * 提取正文与token用量
     * @param array $data
     * @param array $meta
     * @return array
     */
    protected static function success(array $data, array $meta): array
    {
        $content = trim((string)($data['choices'][0]['message']['content'] ?? ''));
        if ($content === '') {
            return self::result($meta + ['error' => self::ERROR_EMPTY_CONTENT]);
        }
        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];
        $model = trim((string)($data['model'] ?? ''));
        return self::result([
            'success' => true,
            'content' => $content,
            'model' => $model !== '' ? $model : $meta['model'],
            'prompt_tokens' => (int)($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int)($usage['completion_tokens'] ?? 0),
            'duration_ms' => (int)($meta['duration_ms'] ?? 0),
            'status' => self::STATUS_OK,
        ]);
    }

    /**
     * 统一返回结构，缺省即失败
     * @param array $override
     * @return array
     */
    protected static function result(array $override): array
    {
        return array_merge([
            'success' => false,
            'content' => '',
            'model' => '',
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'duration_ms' => 0,
            'status' => self::STATUS_FAIL,
            'error' => '',
        ], $override);
    }

    /**
     * 耗时毫秒
     * @param float $start
     * @return int
     */
    protected static function elapsed(float $start): int
    {
        return (int)round((microtime(true) - $start) * 1000);
    }

    /**
     * 配置读取失败不应阻断AI能力，回落默认值并留痕
     * @param callable $reader
     * @param mixed $default
     * @param string $key
     * @return mixed
     */
    protected static function readOr(callable $reader, $default, string $key)
    {
        try {
            $value = $reader();
        } catch (\Throwable $e) {
            Log::error('AI配置读取失败：' . $key . '，原因：' . $e->getMessage());
            return $default;
        }
        return ($value === null || $value === '' || $value === false) ? $default : $value;
    }
}
