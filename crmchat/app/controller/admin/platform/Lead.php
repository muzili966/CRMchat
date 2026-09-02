<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\controller\admin\platform;

use app\controller\admin\AuthController;
use app\models\PlatformLead;
use app\services\platform\PlatformLeadServices;

/**
 * 销售线索（平台专属）
 *
 * 管理的是平台自己的潜在客户，与租户数据无关，故仅平台端可见。
 * Class Lead
 * @package app\controller\admin\platform
 */
class Lead extends AuthController
{
    /**
     * @param PlatformLeadServices $services
     */
    public function __construct(PlatformLeadServices $services)
    {
        parent::__construct();
        $this->mustPlatformAdmin('销售线索');
        $this->services = $services;
    }

    /**
     * 线索列表
     * @return mixed
     */
    public function index()
    {
        $where = $this->request->getMore([
            ['stage', ''],
            [['owner_id', 'd'], 0],
            ['source', ''],
            ['keyword', ''],
        ]);
        return $this->success($this->services->getLeadList($where));
    }

    /**
     * 阶段与来源的可选项，供前端筛选与表单使用
     * @return mixed
     */
    public function options()
    {
        $stages = [];
        foreach (PlatformLead::STAGES as $value => $label) {
            $stages[] = ['value' => $value, 'label' => $label];
        }
        $sources = [];
        foreach (PlatformLead::SOURCES as $value => $label) {
            $sources[] = ['value' => $value, 'label' => $label];
        }
        return $this->success(['stages' => $stages, 'sources' => $sources]);
    }

    /**
     * 线索详情
     * @param int $id
     * @return mixed
     */
    public function read($id)
    {
        return $this->success($this->services->getLeadInfo((int)$id));
    }

    /**
     * 手工录入线索
     * @return mixed
     */
    public function save()
    {
        $data = $this->request->postMore([
            ['name', ''],
            ['company', ''],
            ['phone', ''],
            ['email', ''],
            ['scale', ''],
            ['intent_plan', ''],
            ['content', ''],
        ]);
        $data['source'] = PlatformLead::SOURCE_MANUAL;
        $this->services->createLead($data, (int)$this->adminId);
        return $this->success('线索已创建');
    }

    /**
     * 记录跟进，可同时推进阶段
     * @param int $id
     * @return mixed
     */
    public function follow($id)
    {
        $data = $this->request->postMore([
            ['content', ''],
            [['stage', 'd'], 0],
            [['next_follow_time', 'd'], 0],
        ]);
        $this->services->addFollow((int)$id, $data, $this->adminInfo);
        return $this->success('跟进已记录');
    }

    /**
     * 转派跟进人
     * @param int $id
     * @return mixed
     */
    public function assign($id)
    {
        $ownerId = (int)$this->request->post('owner_id', 0);
        $this->services->assign((int)$id, $ownerId);
        return $this->success('转派成功');
    }

    /**
     * 关联已开通的租户
     * @param int $id
     * @return mixed
     */
    public function link($id)
    {
        $tenantId = (int)$this->request->post('tenant_id', 0);
        if (!$tenantId) {
            return $this->fail('请选择要关联的租户');
        }
        $this->services->linkTenant((int)$id, $tenantId);
        return $this->success('已关联租户并标记为成交');
    }

    /**
     * @param int $id
     * @return mixed
     */
    public function delete($id)
    {
        $this->services->deleteLead((int)$id);
        return $this->success('删除成功');
    }
}
