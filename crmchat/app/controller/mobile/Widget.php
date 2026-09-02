<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\controller\mobile;

use app\services\ApplicationServices;
use app\services\ApplicationThemeServices;
use think\Request;

/**
 * 悬浮挂件配置
 *
 * 嵌入脚本运行在接入方站点，入口按钮的渲染早于聊天窗口，
 * 拿不到走websocket下发的装修数据，故单开一个公开接口。
 *
 * 不继承 mobile\AuthController：那个基类依赖登录中间件注入的 appId，
 * 而本接口必须在无登录态下可用，只凭应用token定位。
 * Class Widget
 * @package app\controller\mobile
 */
class Widget
{
    /**
     * @var Request
     */
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * 挂件配置
     *
     * token走query而非请求头：简单跨域请求不触发OPTIONS预检，嵌入脚本更轻。
     * 返回内容仅限入口按钮渲染所需，不外泄其余装修配置。
     * @return \think\Response
     */
    public function index()
    {
        $token = trim((string)$this->request->get('token', ''));
        if (!$token) {
            return app('json')->fail('缺少应用token');
        }
        try {
            /** @var ApplicationServices $appServices */
            $appServices = app()->make(ApplicationServices::class);
            //parseToken内部完成token寻址、租户可用性校验与租户上下文建立
            $appInfo = $appServices->parseToken($token);
            $appid = (string)($appInfo['appInfo']['appid'] ?? '');
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
        if (!$appid) {
            return app('json')->fail('无效的应用token');
        }
        /** @var ApplicationThemeServices $themeServices */
        $themeServices = app()->make(ApplicationThemeServices::class);
        return app('json')->success($themeServices->getWidgetConfig($appid));
    }
}
