<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\controller\admin\chat;

use app\controller\admin\AuthController;
use app\services\chat\ChatHistoryServices;

/**
 * 历史会话
 *
 * 管理者视角回看全部客服的历史对话：质检、纠纷举证、提炼FAQ。
 * 支持两种聚合——按会话（一次接待）与按访客（一个客户的全部往来）。
 * Class History
 * @package app\controller\admin\chat
 */
class History extends AuthController
{
    /**
     * @param ChatHistoryServices $services
     */
    public function __construct(ChatHistoryServices $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    /**
     * 会话视角列表
     * @return mixed
     */
    public function sessions()
    {
        return $this->success($this->services->getSessionList($this->listWhere()));
    }

    /**
     * 访客视角列表
     * @return mixed
     */
    public function visitors()
    {
        return $this->success($this->services->getVisitorList($this->listWhere()));
    }

    /**
     * 某访客的全部会话
     * @param int $id 访客ID
     * @return mixed
     */
    public function visitorSessions($id)
    {
        return $this->success($this->services->getVisitorSessions((int)$id));
    }

    /**
     * 对话内容
     * @return mixed
     */
    public function records()
    {
        $data = $this->request->getMore([
            [['agent_user_id', 'd'], 0],
            [['visitor_user_id', 'd'], 0],
            [['page', 'd'], 1],
            [['limit', 'd'], 30],
        ]);
        return $this->success($this->services->getTranscript($data));
    }

    /**
     * 列表筛选条件
     * @return array
     */
    protected function listWhere(): array
    {
        return $this->request->getMore([
            ['keyword', ''],
            [['kefu_user_id', 'd'], 0],
            ['appid', ''],
            ['start', ''],
            ['end', ''],
            [['page', 'd'], 1],
            [['limit', 'd'], 20],
        ]);
    }
}
