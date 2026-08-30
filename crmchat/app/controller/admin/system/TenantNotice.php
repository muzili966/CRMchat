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
use app\services\TenantNoticeServices;

/**
 * 租户通知：租户管理员查看自己的到期/续费通知，平台未选视角看全部
 * Class TenantNotice
 * @package app\controller\admin\system
 */
class TenantNotice extends AuthController
{

    /**
     * TenantNotice constructor.
     * @param TenantNoticeServices $services
     */
    public function __construct(TenantNoticeServices $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    /**
     * 通知列表
     * @return mixed
     */
    public function index()
    {
        $where = $this->request->getMore([
            ['is_read', ''],
            ['type', ''],
        ]);
        return $this->success($this->withPlatformScope(function () use ($where) {
            return $this->services->getNoticeList($where);
        }));
    }

    /**
     * 平台发送公告通知
     * @return mixed
     */
    public function send()
    {
        if (!\app\models\system\admin\SystemAdmin::isPlatformAdmin($this->adminInfo)) {
            return $this->fail('仅平台管理员可以发送通知');
        }
        $data = $this->request->postMore([
            [['tenant_ids', 'a'], []],
            ['content', ''],
        ]);
        $count = $this->services->sendNotice(array_map('intval', (array)$data['tenant_ids']), (string)$data['content']);
        return $this->success('已发送' . $count . '条通知');
    }

    /**
     * 标记已读
     * @param $id
     * @return mixed
     */
    public function read($id)
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }
        $this->withPlatformScope(function () use ($id) {
            $this->services->markRead((int)$id);
        });
        return $this->success('已读');
    }
}
