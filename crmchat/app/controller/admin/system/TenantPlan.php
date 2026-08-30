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

namespace app\controller\admin\system;


use app\controller\admin\AuthController;
use app\models\system\admin\SystemAdmin;
use app\services\TenantPlanServices;

/**
 * 租户套餐管理（仅平台超管可操作）
 * Class TenantPlan
 * @package app\controller\admin\system
 */
class TenantPlan extends AuthController
{

    /**
     * TenantPlan constructor.
     * @param TenantPlanServices $services
     */
    public function __construct(TenantPlanServices $services)
    {
        parent::__construct();
        $this->services = $services;
        if (!SystemAdmin::isPlatformAdmin($this->adminInfo)) {
            throw new \crmeb\exceptions\AdminException('仅平台管理员可以管理套餐');
        }
    }

    /**
     * 套餐字段定义
     * @return array
     */
    protected function planFields(): array
    {
        return $this->request->postMore([
            ['name', ''],
            ['price', 0],
            [['app_limit', 'd'], 0],
            [['seat_limit', 'd'], 0],
            [['daily_msg_limit', 'd'], 0],
            [['storage_limit_mb', 'd'], 0],
            [['record_keep_days', 'd'], 0],
            [['daily_ai_limit', 'd'], 0],
            [['ai_reply', 'd'], 0],
            [['auto_reply', 'd'], 0],
            [['brand_custom', 'd'], 0],
            [['white_label', 'd'], 0],
            [['custom_ad', 'd'], 0],
            [['custom_domain', 'd'], 0],
            [['data_export', 'd'], 0],
            [['app_push', 'd'], 0],
            [['sort', 'd'], 0],
        ]);
    }

    /**
     * 套餐列表
     * @return mixed
     */
    public function index()
    {
        $where = $this->request->getMore([
            ['name', '', '', 'name_like'],
            ['status', ''],
        ]);
        return $this->success($this->services->getPlanList($where));
    }

    /**
     * 在售套餐下拉选项
     * @return mixed
     */
    public function all()
    {
        return $this->success($this->services->getOptions());
    }

    /**
     * 创建套餐
     * @return mixed
     */
    public function save()
    {
        $this->services->create($this->planFields());
        return $this->success('套餐创建成功');
    }

    /**
     * 修改套餐
     * @param $id
     * @return mixed
     */
    public function update($id)
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }
        $this->services->edit((int)$id, $this->planFields());
        return $this->success('修改成功');
    }

    /**
     * 上架/停售套餐
     * @param $id
     * @param $status
     * @return mixed
     */
    public function set_status($id, $status)
    {
        if ($status == '' || !$id) {
            return $this->fail('参数错误');
        }
        $this->services->setStatus((int)$id, (int)$status);
        return $this->success($status == \app\models\TenantPlan::STATUS_OFF ? '套餐已停售' : '套餐已上架');
    }

    /**
     * 删除套餐
     * @param $id
     * @return mixed
     */
    public function delete($id)
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }
        $this->services->remove((int)$id);
        return $this->success('删除成功');
    }
}
