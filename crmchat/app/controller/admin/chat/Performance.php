<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\controller\admin\chat;

use app\controller\admin\AuthController;
use app\services\performance\PerformanceServices;

/**
 * 客服绩效
 *
 * 「统计」菜单下此前一个可见页面都没有，管理者只能翻对话做质检。
 * 这里把已在会话行上累计好的指标聚合出来。
 * Class Performance
 * @package app\controller\admin\chat
 */
class Performance extends AuthController
{
    /**
     * @param PerformanceServices $services
     */
    public function __construct(PerformanceServices $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    /**
     * 概览
     * @return mixed
     */
    public function overview()
    {
        return $this->success($this->services->overview($this->rangeWhere()));
    }

    /**
     * 按客服拆分
     * @return mixed
     */
    public function agents()
    {
        return $this->success($this->services->agents($this->rangeWhere()));
    }

    /**
     * 按天趋势
     * @return mixed
     */
    public function trend()
    {
        return $this->success($this->services->trend($this->rangeWhere()));
    }

    /**
     * 会话明细
     * @return mixed
     */
    public function sessions()
    {
        $where = $this->rangeWhere();
        $where['rated'] = $this->request->param('rated', '');
        $where['page'] = (int)$this->request->param('page', 1);
        $where['limit'] = (int)$this->request->param('limit', 20);
        return $this->success($this->services->sessions($where));
    }

    /**
     * 共用的筛选条件
     * @return array
     */
    protected function rangeWhere(): array
    {
        return $this->request->getMore([
            ['start', ''],
            ['end', ''],
            [['kefu_user_id', 'd'], 0],
            ['appid', ''],
        ]);
    }
}
