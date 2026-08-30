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


use app\services\system\config\SystemConfigServices;
use crmeb\utils\Encrypter;
use think\facade\Env;
use think\facade\Log;

/**
 * AI密钥的存取
 *
 * 密钥落库便于平台在后台自助更换，但明文入库等同于"拖库即泄漏"，
 * 故统一用APP_KEY派生的Encrypter加密存储；环境变量作为兜底与灰度手段
 * Class AiSecretServices
 * @package app\services\ai
 */
class AiSecretServices
{

    /**
     * 存放密文的配置项
     */
    const CONFIG_KEY = 'ai_api_key';

    /**
     * 环境变量兜底（优先级低于数据库，便于容器化部署直接注入）
     */
    const ENV_KEY = 'AI_API_KEY';

    /**
     * 密文前缀：用于区分历史明文值，便于平滑迁移
     */
    const CIPHER_PREFIX = 'enc:';

    /**
     * 后台回显用的掩码，避免把密钥再次吐回前端
     */
    const MASK = '******';

    /**
     * 读取可用的API密钥
     *
     * 优先数据库（平台可自助更换），为空时回落环境变量
     * @return string
     */
    public function getApiKey(): string
    {
        $stored = (string)sys_config(self::CONFIG_KEY, '');
        $key = $stored !== '' ? $this->decrypt($stored) : '';
        if ($key !== '') {
            return $key;
        }
        return trim((string)Env::get(self::ENV_KEY, ''));
    }

    /**
     * 保存密钥（空串表示清除，掩码表示未修改）
     * @param string $plain
     * @return void
     */
    public function saveApiKey(string $plain)
    {
        $plain = trim($plain);
        if ($plain === self::MASK) {
            return;
        }
        $value = $plain === '' ? '' : self::CIPHER_PREFIX . $this->encrypt($plain);
        /** @var SystemConfigServices $configServices */
        $configServices = app()->make(SystemConfigServices::class);
        $configServices->setPlatformValue(self::CONFIG_KEY, $value);
    }

    /**
     * 密钥是否已配置（供后台展示状态，不返回明文）
     * @return bool
     */
    public function hasApiKey(): bool
    {
        return $this->getApiKey() !== '';
    }

    /**
     * 密钥来源，便于排查"改了库却没生效"
     * @return string database|env|none
     */
    public function source(): string
    {
        $stored = (string)sys_config(self::CONFIG_KEY, '');
        if ($stored !== '' && $this->decrypt($stored) !== '') {
            return 'database';
        }
        return trim((string)Env::get(self::ENV_KEY, '')) !== '' ? 'env' : 'none';
    }

    /**
     * 加密失败时宁可不存，也不能明文落库
     * @param string $plain
     * @return string
     */
    protected function encrypt(string $plain): string
    {
        try {
            /** @var Encrypter $encrypter */
            $encrypter = app()->make(Encrypter::class);
            return $encrypter->encryptString($plain);
        } catch (\Throwable $e) {
            Log::error('AI密钥加密失败：' . $e->getMessage());
            throw new \crmeb\exceptions\AdminException('密钥加密失败，请检查APP_KEY配置');
        }
    }

    /**
     * 解密；历史明文值（无前缀）原样返回以便平滑迁移
     * @param string $stored
     * @return string
     */
    protected function decrypt(string $stored): string
    {
        if (strpos($stored, self::CIPHER_PREFIX) !== 0) {
            return trim($stored);
        }
        try {
            /** @var Encrypter $encrypter */
            $encrypter = app()->make(Encrypter::class);
            return (string)$encrypter->decrypt(substr($stored, strlen(self::CIPHER_PREFIX)), false);
        } catch (\Throwable $e) {
            //APP_KEY变更会导致旧密文无法解开，此时应提示重新填写而不是静默当作未配置
            Log::error('AI密钥解密失败（APP_KEY是否变更？）：' . $e->getMessage());
            return '';
        }
    }
}
