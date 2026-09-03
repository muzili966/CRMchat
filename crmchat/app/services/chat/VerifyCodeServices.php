<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\chat;

use crmeb\services\CacheService;
use think\exception\ValidateException;
use think\facade\Env;
use think\facade\Log;

/**
 * 手机验证码
 *
 * 只负责“发一个码、过一会儿核验一次”，谁来真正投递短信由发送器决定：
 * 开发环境写日志（调试时从日志或调试响应里取码），生产接真短信网关。
 * 验证码存 Redis，限流也在这里，把它和上层的账号绑定/登录解耦。
 * Class VerifyCodeServices
 * @package app\services\chat
 */
class VerifyCodeServices
{
    /**
     * 验证码位数
     */
    const CODE_LENGTH = 6;

    /**
     * 验证码有效期（秒）
     */
    const CODE_TTL = 300;

    /**
     * 同一手机两次发送的最小间隔（秒）
     */
    const RESEND_INTERVAL = 60;

    /**
     * 单手机每日发送上限
     */
    const PHONE_DAY_LIMIT = 10;

    /**
     * 单IP每日发送上限
     */
    const IP_DAY_LIMIT = 30;

    /**
     * 核验时最多试几次，超过即作废本码，逼其重新获取
     */
    const MAX_VERIFY = 5;

    const CODE_PREFIX = 'visitor_vcode:';
    const TRY_PREFIX = 'visitor_vtry:';
    const RESEND_PREFIX = 'visitor_vsend:';
    const PHONE_DAY_PREFIX = 'visitor_vpday:';
    const IP_DAY_PREFIX = 'visitor_vipday:';

    /**
     * 发送验证码
     *
     * @param string $appid
     * @param string $phone
     * @param string $ip
     * @return string 开发桩下返回验证码本身供调试，真短信下返回空串
     */
    public function send(string $appid, string $phone, string $ip): string
    {
        $this->guardRate($appid, $phone, $ip);
        $code = $this->generate();
        $cache = CacheService::redisHandler();
        $cache->set(self::CODE_PREFIX . $appid . ':' . $phone, $code, self::CODE_TTL);
        $cache->delete(self::TRY_PREFIX . $appid . ':' . $phone);
        $cache->set(self::RESEND_PREFIX . $phone, 1, self::RESEND_INTERVAL);
        $this->hit($cache, self::PHONE_DAY_PREFIX . $phone, 86400);
        $this->hit($cache, self::IP_DAY_PREFIX . md5($ip), 86400);
        return $this->deliver($phone, $code);
    }

    /**
     * 核验验证码，成功即作废，失败累计次数
     *
     * @param string $appid
     * @param string $phone
     * @param string $code
     * @return bool
     */
    public function verify(string $appid, string $phone, string $code): bool
    {
        $code = trim($code);
        if ($code === '') {
            return false;
        }
        $cache = CacheService::redisHandler();
        $key = self::CODE_PREFIX . $appid . ':' . $phone;
        $stored = (string)$cache->get($key);
        if ($stored === '') {
            return false;
        }
        if ((int)$this->hit($cache, self::TRY_PREFIX . $appid . ':' . $phone, self::CODE_TTL) > self::MAX_VERIFY) {
            $cache->delete($key);
            return false;
        }
        if (!hash_equals($stored, $code)) {
            return false;
        }
        $cache->delete($key);
        $cache->delete(self::TRY_PREFIX . $appid . ':' . $phone);
        return true;
    }

    /**
     * 限流：间隔、单手机日限、单IP日限。任一超限即拒绝
     * @param string $appid
     * @param string $phone
     * @param string $ip
     */
    protected function guardRate(string $appid, string $phone, string $ip)
    {
        $cache = CacheService::redisHandler();
        if ($cache->get(self::RESEND_PREFIX . $phone)) {
            throw new ValidateException('验证码发送过于频繁，请稍后再试');
        }
        if ((int)$cache->get(self::PHONE_DAY_PREFIX . $phone) >= self::PHONE_DAY_LIMIT) {
            throw new ValidateException('该号码今日获取次数已达上限');
        }
        if ((int)$cache->get(self::IP_DAY_PREFIX . md5($ip)) >= self::IP_DAY_LIMIT) {
            throw new ValidateException('操作过于频繁，请稍后再试');
        }
    }

    /**
     * 投递验证码给发送器
     *
     * 目前只有写日志的开发桩；接真短信时在此按配置选驱动即可。
     * 开发环境额外把码回给调用方，仅用于本地联调。
     * @param string $phone
     * @param string $code
     * @return string
     */
    protected function deliver(string $phone, string $code): string
    {
        Log::info('访客验证码[开发桩] phone=' . $phone . ' code=' . $code);
        return $this->isDebug() ? $code : '';
    }

    /**
     * @return string
     */
    protected function generate(): string
    {
        $max = (10 ** self::CODE_LENGTH) - 1;
        return str_pad((string)random_int(0, $max), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * @param mixed $cache
     * @param string $key
     * @param int $ttl
     * @return int
     */
    protected function hit($cache, string $key, int $ttl): int
    {
        $count = (int)$cache->incr($key);
        if ($count === 1) {
            $cache->expire($key, $ttl);
        }
        return $count;
    }

    /**
     * @return bool
     */
    protected function isDebug(): bool
    {
        return (bool)Env::get('app_debug', false);
    }
}
