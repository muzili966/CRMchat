<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\controller\admin\chat;

use app\controller\admin\AuthController;
use app\services\chat\ChatFaqServices;
use app\services\TenantServices;
use crmeb\services\tenant\TenantContext;

/**
 * 常见问题
 *
 * 访客进入新会话时弹卡片，点问题直接得到答案。数据与关键词自动回复同表，
 * 以 user_id=0 标记为全站通用，详见 ChatFaqServices。
 * Class Faq
 * @package app\controller\admin\chat
 */
class Faq extends AuthController
{
    /**
     * Faq constructor.
     * @param ChatFaqServices $services
     */
    public function __construct(ChatFaqServices $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    /**
     * 列表
     * @return mixed
     */
    public function index()
    {
        $where = $this->request->getMore([
            ['appid', ''],
            [['page', 'd'], 1],
            [['limit', 'd'], 20],
        ]);
        if ($error = $this->checkAppOwner((string)$where['appid'])) {
            return $this->fail($error);
        }
        return $this->success($this->services->getAdminList($where));
    }

    /**
     * 新增
     * @return mixed
     */
    public function save()
    {
        [$appid, $data] = $this->faqData();
        if ($error = $this->checkAppOwner($appid)) {
            return $this->fail($error);
        }
        $this->services->saveFaq(0, $data, $appid);
        return $this->success('保存成功');
    }

    /**
     * 编辑
     * @param int $id
     * @return mixed
     */
    public function update($id)
    {
        [$appid, $data] = $this->faqData();
        if ($error = $this->checkAppOwner($appid)) {
            return $this->fail($error);
        }
        $this->services->saveFaq((int)$id, $data, $appid);
        return $this->success('修改成功');
    }

    /**
     * 删除
     * @param int $id
     * @return mixed
     */
    public function delete($id)
    {
        $appid = (string)$this->request->param('appid', '');
        if ($error = $this->checkAppOwner($appid)) {
            return $this->fail($error);
        }
        $this->services->deleteFaq((int)$id, $appid);
        return $this->success('删除成功');
    }

    /**
     * 拖拽排序
     * @return mixed
     */
    public function sort()
    {
        [$appid, $ids] = $this->request->postMore([
            ['appid', ''],
            ['ids', []],
        ], true);
        if ($error = $this->checkAppOwner((string)$appid)) {
            return $this->fail($error);
        }
        $this->services->updateSort((array)$ids, (string)$appid);
        return $this->success('排序成功');
    }

    /**
     * 提交的问题数据
     * @return array [appid, data]
     */
    protected function faqData(): array
    {
        $data = $this->request->postMore([
            ['appid', ''],
            ['title', ''],
            ['keyword', ''],
            ['content', ''],
            [['is_faq', 'd'], 1],
            [['sort', 'd'], 0],
        ]);
        $appid = (string)$data['appid'];
        unset($data['appid']);
        return [$appid, $data];
    }

    /**
     * 校验应用归属，返回空串表示通过
     *
     * 与客户端装修同一口径：平台账号无租户上下文时不能配置租户的应用。
     * @param string $appid
     * @return string
     */
    protected function checkAppOwner(string $appid): string
    {
        if (!$appid) {
            return '请选择应用';
        }
        $tenantId = TenantContext::id();
        if (!$tenantId) {
            return '平台账号请切换到租户视角后配置常见问题';
        }
        /** @var TenantServices $tenantServices */
        $tenantServices = app()->make(TenantServices::class);
        $appTenantId = $tenantServices->tenantIdByAppid($appid);
        if (!$appTenantId) {
            return '所选应用不存在';
        }
        return $appTenantId == $tenantId ? '' : '所选应用不属于当前租户';
    }
}
