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

namespace app\controller\admin\chat;


use app\controller\admin\AuthController;
use app\models\AiConfig as AiConfigModel;
use app\services\ai\AiAgentServices;
use app\services\ai\AiConfigServices;
use app\services\ai\AiUsageServices;
use app\services\TenantPlanServices;
use crmeb\services\tenant\TenantContext;

/**
 * AI客服设置（租户视角）
 * Class AiConfig
 * @package app\controller\admin\chat
 */
class AiConfig extends AuthController
{

    /**
     * AiConfig constructor.
     * @param AiConfigServices $services
     */
    public function __construct(AiConfigServices $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    /**
     * AI配置详情与近期用量
     * @return mixed
     */
    public function index()
    {
        $tenantId = $this->mustTenantId();
        if (!$tenantId) {
            return $this->fail('平台账号请切换到租户视角后配置AI客服');
        }
        /** @var AiUsageServices $usageServices */
        $usageServices = app()->make(AiUsageServices::class);
        /** @var TenantPlanServices $planServices */
        $planServices = app()->make(TenantPlanServices::class);
        return $this->success([
            'config' => $this->services->getConfig($tenantId) ?: $this->defaultConfig(),
            'usage' => $usageServices->getSummary($tenantId),
            'plan' => [
                'ai_reply' => $planServices->canUseAi($tenantId),
                'today_count' => $planServices->getTodayAiCount($tenantId),
            ],
        ]);
    }

    /**
     * 保存AI配置并同步AI坐席
     * @return mixed
     */
    public function save()
    {
        $tenantId = $this->mustTenantId();
        if (!$tenantId) {
            return $this->fail('平台账号请切换到租户视角后配置AI客服');
        }
        /** @var TenantPlanServices $planServices */
        $planServices = app()->make(TenantPlanServices::class);
        $data = $this->request->postMore([
            [['enable', 'd'], 0],
            ['mode', AiConfigModel::MODE_STANDBY],
            ['greeting', ''],
            ['system_prompt', ''],
            [['faq', 'a'], []],
            ['transfer_keywords', ''],
            ['model', ''],
        ]);
        //套餐未开通AI时不允许开启，避免配置生效后才发现被拒
        if ($data['enable'] && !$planServices->canUseAi($tenantId)) {
            return $this->fail('当前套餐未包含AI智能客服，请升级套餐');
        }
        $this->services->saveConfig($tenantId, $data);
        $this->syncAgents($tenantId, $data);
        return $this->success('保存成功');
    }

    /**
     * AI调用明细
     * @return mixed
     */
    public function usage()
    {
        $tenantId = $this->mustTenantId();
        if (!$tenantId) {
            return $this->fail('平台账号请切换到租户视角后查看');
        }
        $where = $this->request->getMore([
            ['status', ''],
        ]);
        $where['tenant_id'] = $tenantId;
        /** @var AiUsageServices $usageServices */
        $usageServices = app()->make(AiUsageServices::class);
        return $this->success($usageServices->getUsageList($where));
    }

    /**
     * 配置变更后同步虚拟坐席：开启则确保各应用都有AI坐席，关闭则停用
     * @param int $tenantId
     * @param array $data
     * @return void
     */
    protected function syncAgents(int $tenantId, array $data)
    {
        /** @var AiAgentServices $agentServices */
        $agentServices = app()->make(AiAgentServices::class);
        if (empty($data['enable'])) {
            $agentServices->disableAgentsForTenant($tenantId);
            return;
        }
        $agentServices->ensureAgentsForTenant($tenantId);
        $agentServices->enableAgentsForTenant($tenantId);
        $agentServices->syncGreeting($tenantId, (string)$data['greeting']);
    }

    /**
     * 未配置过时的表单初值
     * @return array
     */
    protected function defaultConfig(): array
    {
        return [
            'enable' => AiConfigModel::ENABLE_OFF,
            'mode' => AiConfigModel::MODE_STANDBY,
            'greeting' => '您好，我是智能客服助手，请问有什么可以帮您？',
            'system_prompt' => '',
            'faq' => [],
            'transfer_keywords' => '人工,转人工,客服,投诉',
            'model' => '',
        ];
    }

    /**
     * 当前租户视角ID
     * @return int
     */
    protected function mustTenantId(): int
    {
        return (int)TenantContext::id();
    }
}
