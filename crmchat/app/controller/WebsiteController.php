<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\controller;

use app\services\platform\PlatformLeadServices;
use crmeb\services\CacheService;
use think\Request;

/**
 * 官网公开接口
 *
 * 面向未登录的公网访客，只提供合作意向提交这一个入口。
 * Class WebsiteController
 * @package app\controller
 */
class WebsiteController
{
    /**
     * 同一来源的提交间隔（秒）：表单在公网上裸奔，不限流会被灌垃圾数据
     */
    const SUBMIT_INTERVAL = 60;

    /**
     * @var Request
     */
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * 提交合作意向
     * @return \think\Response
     */
    public function lead()
    {
        //蜜罐字段：正常访客看不到也不会填，被填了基本可判定为脚本提交。
        //直接返回成功而不报错，让脚本以为得手，避免其换策略重试。
        if (trim((string)$this->request->post('website', '')) !== '') {
            return app('json')->success('提交成功，我们会尽快与您联系');
        }
        if (!$this->passRateLimit()) {
            return app('json')->fail('提交过于频繁，请稍后再试');
        }
        $data = $this->request->postMore([
            ['name', ''],
            ['company', ''],
            ['phone', ''],
            ['email', ''],
            ['scale', ''],
            ['intent_plan', ''],
            ['content', ''],
        ]);
        $data['source'] = \app\models\PlatformLead::SOURCE_WEBSITE;
        try {
            /** @var PlatformLeadServices $services */
            $services = app()->make(PlatformLeadServices::class);
            $services->createLead($data);
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
        return app('json')->success('提交成功，我们会尽快与您联系');
    }

    /**
     * 按IP限流，缓存不可用时放行
     *
     * 限流是防灌水的辅助手段，不该因为缓存故障就把正常客户的意向挡在门外
     * @return bool
     */
    protected function passRateLimit(): bool
    {
        try {
            $key = 'website_lead:' . md5((string)$this->request->ip());
            $redis = CacheService::redisHandler();
            if ($redis->get($key)) {
                return false;
            }
            $redis->set($key, 1, self::SUBMIT_INTERVAL);
        } catch (\Throwable $e) {
            return true;
        }
        return true;
    }
}
