<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\controller\admin\export;

use app\controller\admin\AuthController;
use app\services\export\ExportTaskServices;

/**
 * 下载中心
 * Class Task
 * @package app\controller\admin\export
 */
class Task extends AuthController
{
    /**
     * @param ExportTaskServices $services
     */
    public function __construct(ExportTaskServices $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    /**
     * 任务列表
     * @return mixed
     */
    public function index()
    {
        $where = $this->request->getMore([
            ['status', ''],
            ['type', ''],
            [['page', 'd'], 1],
            [['limit', 'd'], 20],
        ]);
        return $this->success($this->services->getList($where));
    }

    /**
     * 删除任务及其文件
     * @param int $id
     * @return mixed
     */
    public function delete($id)
    {
        $this->services->remove((int)$id);
        return $this->success('删除成功');
    }
}
