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

namespace crmeb\utils;


use crmeb\exceptions\AdminException;
use crmeb\services\CacheService;
use Firebase\JWT\JWT;
use think\facade\Env;

/**
 * Jwt
 * Class JwtAuth
 * @package crmeb\utils
 */
class JwtAuth
{

    /**
     * token
     * @var string
     */
    protected $token;

    /**
     * @var string
     */
    protected $app_key = 'crmeb_app_key';

    /**
     * 获取token
     * @param int $id
     * @param string $type
     * @param array $params
     * @return array
     */
    public function getToken(int $id, string $type, array $params = []): array
    {
        $host = app()->request->host();
        $time = time();
        $exp = strtotime('+ 30day');

        //租户声明写入jti，防止token跨租户复用
        $tenantId = (int)($params['tenant_id'] ?? 0);
        unset($params['tenant_id']);

        $params += [
            'iss' => $host,
            'aud' => $host,
            'iat' => $time,
            'nbf' => $time,
            'exp' => $exp,
        ];
        $params['jti'] = ['id' => $id, 'type' => $type, 'tenant_id' => $tenantId];
        $token = JWT::encode($params, Env::get('app_key', $this->app_key));

        return compact('token', 'params');
    }

    /**
     * 解析token
     * @param string $jwt
     * @return array [id, type, tenant_id|null] 旧token无租户声明时第三项为null
     */
    public function parseToken(string $jwt): array
    {
        $this->token = $jwt;
        list($headb64, $bodyb64, $cryptob64) = explode('.', $this->token);
        $payload = JWT::jsonDecode(JWT::urlsafeB64Decode($bodyb64));
        return [$payload->jti->id, $payload->jti->type, $payload->jti->tenant_id ?? null];
    }

    /**
     * 验证token
     */
    public function verifyToken()
    {
        JWT::$leeway = 60;

        JWT::decode($this->token, Env::get('app_key', $this->app_key), array('HS256'));

        $this->token = null;
    }

    /**
     * 获取token并放入令牌桶
     * @param int $id
     * @param string $type
     * @param array $params
     * @return array
     */
    public function createToken(int $id, string $type, array $params = [])
    {
        $tokenInfo = $this->getToken($id, $type, $params);
        $exp = $tokenInfo['params']['exp'] - $tokenInfo['params']['iat'] + 60;
        $res = CacheService::setTokenBucket(md5($tokenInfo['token']), ['uid' => $id, 'type' => $type, 'token' => $tokenInfo['token'], 'exp' => $exp], (int)$exp);
        if (!$res) {
            throw new AdminException(ApiErrorCode::ERR_SAVE_TOKEN);
        }
        return $tokenInfo;
    }
}
