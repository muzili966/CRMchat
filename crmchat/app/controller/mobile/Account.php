<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\controller\mobile;

use app\services\ApplicationServices;
use app\services\chat\VisitorAccountServices;
use think\Request;

/**
 * 访客账号
 *
 * 面向未登录访客：绑定手机号后可换设备接续会话。不继承 mobile\AuthController，
 * 那个基类要登录中间件注入的 appId，而这里必须在无登录态下凭应用token工作。
 * Class Account
 * @package app\controller\mobile
 */
class Account
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var VisitorAccountServices
     */
    protected $services;

    public function __construct(Request $request, VisitorAccountServices $services)
    {
        $this->request = $request;
        $this->services = $services;
    }

    /**
     * 发送验证码
     * @return \think\Response
     */
    public function code()
    {
        [$appid] = $this->resolve();
        $phone = trim((string)$this->request->param('phone', ''));
        try {
            $code = $this->services->sendCode($appid, $phone, (string)$this->request->ip());
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
        //开发桩把验证码回给前端仅用于本地联调，生产为空串
        return app('json')->success('验证码已发送', $code === '' ? [] : ['debug_code' => $code]);
    }

    /**
     * 验证码绑定/登录
     * @return \think\Response
     */
    public function bind()
    {
        [$appid, $tenantId] = $this->resolve();
        try {
            $result = $this->services->bindOrLogin([
                'appid' => $appid,
                'tenantId' => $tenantId,
                'phone' => trim((string)$this->request->param('phone', '')),
                'code' => (string)$this->request->param('code', ''),
                //客户端持有的是 uid 串，服务端据此定位访客档案
                'uid' => (int)$this->request->param('uid', 0),
                'password' => (string)$this->request->param('password', ''),
            ]);
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
        return app('json')->success('绑定成功', $result);
    }

    /**
     * 密码登录
     * @return \think\Response
     */
    public function login()
    {
        [$appid, $tenantId] = $this->resolve();
        try {
            $result = $this->services->loginByPassword([
                'appid' => $appid,
                'tenantId' => $tenantId,
                'phone' => trim((string)$this->request->param('phone', '')),
                'password' => (string)$this->request->param('password', ''),
            ]);
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
        return app('json')->success('登录成功', $result);
    }

    /**
     * 解析应用token，返回 [appid, tenantId] 并建立租户上下文
     * @return array
     */
    protected function resolve(): array
    {
        //与record一致：token既可走param，也可走 Authori-zation 头（前端 mobile 请求默认带头）
        $token = trim((string)$this->request->param('token', ''));
        if (!$token) {
            $token = trim(preg_replace('/^Bearer\s+/i', '', (string)$this->request->header('Authori-zation', '')));
        }
        if (!$token) {
            throw new \think\exception\ValidateException('缺少应用token');
        }
        /** @var ApplicationServices $appServices */
        $appServices = app()->make(ApplicationServices::class);
        $appInfo = $appServices->parseToken($token)['appInfo'] ?? [];
        $appid = (string)($appInfo['appid'] ?? '');
        if (!$appid) {
            throw new \think\exception\ValidateException('无效的应用token');
        }
        return [$appid, (int)($appInfo['tenant_id'] ?? 0)];
    }
}
