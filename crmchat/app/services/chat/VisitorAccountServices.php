<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\chat;

use app\dao\ApplicationDao;
use app\models\ChatVisitorAccount;
use crmeb\services\tenant\TenantContext;
use think\exception\ValidateException;

/**
 * 访客账号
 *
 * 让访客用手机号在不同设备间接续会话。手机号在公网可枚举，所以它自己不是凭据：
 * 绑定和登录都要过验证码或密码。绑定成功后，这个 uid 单靠自身不再能接续会话，
 * 必须带服务端签发的续接令牌（见 issueToken/verifyToken），把“猜到 uid 就能读历史”
 * 这个老问题在账号访客身上堵死。
 * Class VisitorAccountServices
 * @package app\services\chat
 */
class VisitorAccountServices
{
    /**
     * 续接令牌有效期（秒）：给足回访窗口，过期需重新登录
     */
    const TOKEN_TTL = 2592000;

    /**
     * @var ChatVisitorAccount
     */
    protected $model;

    /**
     * @var VerifyCodeServices
     */
    protected $codeServices;

    public function __construct(ChatVisitorAccount $model, VerifyCodeServices $codeServices)
    {
        $this->model = $model;
        $this->codeServices = $codeServices;
    }

    /**
     * 发送验证码
     * @param string $appid
     * @param string $phone
     * @param string $ip
     * @return string
     */
    public function sendCode(string $appid, string $phone, string $ip): string
    {
        $this->assertPhone($phone);
        return $this->codeServices->send($appid, $phone, $ip);
    }

    /**
     * 验证码绑定/登录
     *
     * 号码没绑过就把当前访客会话绑上去；绑过了就直接登录返回原会话，
     * 不把老号主的会话改指向当前游客（否则拿到验证码的人能吞掉别人历史，
     * 但拿到验证码本就证明其掌握该号，按登录处理即可）。
     * @param array $data appid/tenantId/phone/code/userId/password
     * @return array
     */
    public function bindOrLogin(array $data): array
    {
        $this->assertPhone($data['phone']);
        if (!$this->codeServices->verify($data['appid'], $data['phone'], (string)($data['code'] ?? ''))) {
            throw new ValidateException('验证码错误或已过期');
        }
        $account = $this->find($data['tenantId'], $data['appid'], $data['phone']);
        if (!$account) {
            $account = $this->create($data);
        }
        if (!empty($data['password'])) {
            $this->applyPassword($account, (string)$data['password']);
        }
        return $this->grant($account, $data['appid']);
    }

    /**
     * 密码登录
     * @param array $data appid/tenantId/phone/password
     * @return array
     */
    public function loginByPassword(array $data): array
    {
        $this->assertPhone($data['phone']);
        $account = $this->find($data['tenantId'], $data['appid'], $data['phone']);
        if (!$account || $account->password_hash === '') {
            throw new ValidateException('账号不存在或未设置密码');
        }
        $this->assertNotLocked($account);
        if (!password_verify((string)($data['password'] ?? ''), $account->password_hash)) {
            $this->onFail($account);
            throw new ValidateException('手机号或密码错误');
        }
        $this->onSuccess($account);
        return $this->grant($account, $data['appid']);
    }

    /**
     * 校验续接令牌，通过返回账号关联的 user_id，否则 0
     *
     * getRecord 用它决定是否放行：账号访客必须带有效令牌，令牌失效于
     * 过期或 token_version 变更（改密码/注销）。
     * @param string $appid
     * @param string $token
     * @return int
     */
    public function resolveToken(string $appid, string $token): int
    {
        $payload = self::unsign($token, $this->appSecret($appid));
        if ($payload === null) {
            return 0;
        }
        [$userId, $version, $issuedAt] = array_pad(explode('|', $payload), 3, '');
        if (time() - (int)$issuedAt > self::TOKEN_TTL) {
            return 0;
        }
        $account = $this->model->where(['user_id' => (int)$userId])->find();
        if (!$account || (int)$account->token_version !== (int)$version) {
            return 0;
        }
        return (int)$userId;
    }

    /**
     * 签名一段 payload
     * @param string $payload
     * @param string $secret
     * @return string
     */
    public static function sign(string $payload, string $secret): string
    {
        $sig = hash_hmac('sha256', $payload, $secret);
        return self::base64UrlEncode($payload) . '.' . $sig;
    }

    /**
     * 验签并取回 payload，失败返回 null
     * @param string $token
     * @param string $secret
     * @return string|null
     */
    public static function unsign(string $token, string $secret): ?string
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2 || $secret === '') {
            return null;
        }
        $payload = self::base64UrlDecode($parts[0]);
        return hash_equals(hash_hmac('sha256', $payload, $secret), $parts[1]) ? $payload : null;
    }

    /**
     * 该访客是否已绑定账号（决定 getRecord 是否强制校验令牌）
     * @param int $userId
     * @return bool
     */
    public function isBound(int $userId): bool
    {
        return $userId > 0 && (bool)$this->model->where(['user_id' => $userId])->count();
    }

    /**
     * @param int $tenantId
     * @param string $appid
     * @param string $phone
     * @return ChatVisitorAccount|null
     */
    protected function find(int $tenantId, string $appid, string $phone)
    {
        return $this->model->where([
            'tenant_id' => $tenantId,
            'appid' => $appid,
            'phone' => $phone,
        ])->find();
    }

    /**
     * @param array $data
     * @return ChatVisitorAccount
     */
    protected function create(array $data): ChatVisitorAccount
    {
        $userId = $this->resolveUserId($data['appid'], (int)($data['uid'] ?? 0));
        if (!$userId) {
            throw new ValidateException('会话已失效，请刷新页面后重试');
        }
        $account = new ChatVisitorAccount();
        $account->tenant_id = (int)$data['tenantId'];
        $account->appid = $data['appid'];
        $account->phone = $data['phone'];
        $account->user_id = $userId;
        $account->token_version = 1;
        $account->create_time = time();
        $account->update_time = time();
        $account->save();
        return $account;
    }

    /**
     * 访客 uid 串解析为 eb_chat_user 主键
     * @param string $appid
     * @param int $uid
     * @return int
     */
    protected function resolveUserId(string $appid, int $uid): int
    {
        if (!$uid) {
            return 0;
        }
        return (int)TenantContext::withoutTenant(function () use ($appid, $uid) {
            return app()->make(\app\dao\chat\ChatUserDao::class)->value(['appid' => $appid, 'uid' => $uid], 'id');
        });
    }

    /**
     * 设密码即吊销所有旧令牌，逼其他设备重新登录
     * @param ChatVisitorAccount $account
     * @param string $password
     */
    protected function applyPassword(ChatVisitorAccount $account, string $password)
    {
        if (mb_strlen($password) < 6 || mb_strlen($password) > 32) {
            throw new ValidateException('密码长度需为6到32位');
        }
        $account->password_hash = password_hash($password, PASSWORD_DEFAULT);
        $account->token_version = (int)$account->token_version + 1;
        $account->update_time = time();
        $account->save();
    }

    /**
     * 发放会话：返回关联 uid 与续接令牌
     * @param ChatVisitorAccount $account
     * @param string $appid
     * @return array
     */
    protected function grant(ChatVisitorAccount $account, string $appid): array
    {
        $account->last_login_time = time();
        $account->save();
        $uid = (int)TenantContext::withoutTenant(function () use ($account) {
            return app()->make(\app\dao\chat\ChatUserDao::class)->value(['id' => $account->user_id], 'uid');
        });
        return [
            'uid' => $uid,
            'has_password' => $account->password_hash !== '',
            'resume_token' => $this->issueToken($account, $appid),
        ];
    }

    /**
     * @param ChatVisitorAccount $account
     * @param string $appid
     * @return string
     */
    protected function issueToken(ChatVisitorAccount $account, string $appid): string
    {
        $payload = $account->user_id . '|' . $account->token_version . '|' . time();
        return self::sign($payload, $this->appSecret($appid));
    }

    /**
     * @param ChatVisitorAccount $account
     */
    protected function assertNotLocked(ChatVisitorAccount $account)
    {
        if ((int)$account->locked_until > time()) {
            throw new ValidateException('尝试过于频繁，请稍后再试');
        }
    }

    /**
     * @param ChatVisitorAccount $account
     */
    protected function onFail(ChatVisitorAccount $account)
    {
        $account->failed_attempts = (int)$account->failed_attempts + 1;
        if ($account->failed_attempts >= ChatVisitorAccount::MAX_FAILED) {
            $account->locked_until = time() + ChatVisitorAccount::LOCK_SECONDS;
            $account->failed_attempts = 0;
        }
        $account->save();
    }

    /**
     * @param ChatVisitorAccount $account
     */
    protected function onSuccess(ChatVisitorAccount $account)
    {
        $account->failed_attempts = 0;
        $account->locked_until = 0;
        $account->save();
    }

    /**
     * @param string $phone
     */
    protected function assertPhone(string $phone)
    {
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            throw new ValidateException('请输入正确的手机号');
        }
    }

    /**
     * @param string $appid
     * @return string
     */
    protected function appSecret(string $appid): string
    {
        return (string)TenantContext::withoutTenant(function () use ($appid) {
            return app()->make(ApplicationDao::class)->value(['appid' => $appid], 'app_secret');
        });
    }

    /**
     * @param string $raw
     * @return string
     */
    protected static function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * @param string $raw
     * @return string
     */
    protected static function base64UrlDecode(string $raw): string
    {
        return (string)base64_decode(strtr($raw, '-_', '+/'));
    }
}
