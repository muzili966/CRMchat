<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\controller\admin\sensitive;

use app\controller\admin\AuthController;
use app\services\sensitive\SensitiveHitServices;

/**
 * 敏感词命中记录
 * Class Hit
 * @package app\controller\admin\sensitive
 */
class Hit extends AuthController
{
    /**
     * @param SensitiveHitServices $services
     */
    public function __construct(SensitiveHitServices $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    /**
     * 命中记录列表
     * @return mixed
     */
    public function index()
    {
        $where = $this->request->getMore([
            ['keyword', ''],
            ['handled', ''],
            ['scope', ''],
            ['start', ''],
            ['end', ''],
            [['page', 'd'], 1],
            [['limit', 'd'], 20],
        ]);
        $data = $this->services->getList($where);
        $data['pending'] = $this->services->pendingCount();
        return $this->success($data);
    }

    /**
     * 标记为已处理
     * @param int $id
     * @return mixed
     */
    public function handle($id)
    {
        $this->services->handle((int)$id);
        return $this->success('已标记处理');
    }
}
