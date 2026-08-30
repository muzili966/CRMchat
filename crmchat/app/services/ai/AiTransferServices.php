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

namespace app\services\ai;


use app\dao\chat\ChatServiceDao;
use app\services\chat\ChatServiceAuxiliaryServices;
use app\services\chat\ChatServiceRecordServices;
use app\services\kefu\KefuServices;
use crmeb\basic\BaseServices;
use think\facade\Log;

/**
 * AI会话与人工之间的双向流转
 *
 * 复用现有客服转接链路（KefuServices::setTransfer），访客端已监听 to_transfer 事件
 * Class AiTransferServices
 * @package app\services\ai
 */
class AiTransferServices extends BaseServices
{

    /**
     * 转人工结果话术
     */
    const MSG_TRANSFERRED = '已为您转接人工客服，请稍候。';
    const MSG_NO_AGENT = '当前没有在线的人工客服，您可以留言，我们会尽快与您联系。';
    const MSG_FAILED = '转接人工客服失败，请稍后再试。';

    /**
     * AiTransferServices constructor.
     * @param ChatServiceDao $dao
     */
    public function __construct(ChatServiceDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 访客从AI会话转到人工
     * @param string $appid
     * @param int $userId 访客chat_user id
     * @param int $aiUserId AI坐席chat_user id
     * @return array ['success' => bool, 'message' => string, 'to_user_id' => int]
     */
    public function toHuman(string $appid, int $userId, int $aiUserId): array
    {
        $humanId = $this->pickOnlineHuman($appid);
        if (!$humanId) {
            return ['success' => false, 'message' => self::MSG_NO_AGENT, 'to_user_id' => 0];
        }
        try {
            /** @var KefuServices $kefuServices */
            $kefuServices = app()->make(KefuServices::class);
            $kefuServices->setTransfer($appid, $aiUserId, $userId, $humanId);
            return ['success' => true, 'message' => self::MSG_TRANSFERRED, 'to_user_id' => $humanId];
        } catch (\Throwable $e) {
            Log::error('AI转人工失败：' . $e->getMessage());
            return ['success' => false, 'message' => self::MSG_FAILED, 'to_user_id' => 0];
        }
    }

    /**
     * 人工坐席主动接管AI会话
     * @param string $appid
     * @param int $userId 访客chat_user id
     * @param int $kefuUserId 接管的坐席chat_user id
     * @return bool
     */
    public function takeOver(string $appid, int $userId, int $kefuUserId): bool
    {
        $aiUserId = $this->getAiUserId($appid);
        if (!$aiUserId) {
            return false;
        }
        /** @var KefuServices $kefuServices */
        $kefuServices = app()->make(KefuServices::class);
        return (bool)$kefuServices->setTransfer($appid, $aiUserId, $userId, $kefuUserId);
    }

    /**
     * 当前由AI接待的会话列表（供工作台接管），复用工作台同款会话列表口径
     * @param string $appid
     * @param string $nickname 按访客昵称筛选
     * @return array
     */
    public function getAiSessions(string $appid, string $nickname = ''): array
    {
        $aiUserId = $this->getAiUserId($appid);
        if (!$aiUserId) {
            return [];
        }
        /** @var ChatServiceRecordServices $recordServices */
        $recordServices = app()->make(ChatServiceRecordServices::class);
        return $recordServices->getServiceList($appid, $aiUserId, $nickname);
    }

    /**
     * 访客是否已被转给人工（转人工后不再触发AI回复）
     * @param string $appid
     * @param int $userId
     * @param int $aiUserId
     * @return bool
     */
    public function isTransferred(string $appid, int $userId, int $aiUserId): bool
    {
        /** @var ChatServiceAuxiliaryServices $auxiliaryServices */
        $auxiliaryServices = app()->make(ChatServiceAuxiliaryServices::class);
        $relationId = (int)$auxiliaryServices->value(['appid' => $appid, 'binding_id' => $userId], 'relation_id');
        return $relationId > 0 && $relationId !== $aiUserId;
    }

    /**
     * 挑一个在线真人坐席
     * @param string $appid
     * @return int
     */
    protected function pickOnlineHuman(string $appid): int
    {
        $ids = $this->dao->getColumn(['appid' => $appid, 'status' => 1, 'online' => 1, 'is_ai' => 0], 'user_id');
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return 0;
        }
        mt_srand();
        return (int)$ids[array_rand($ids)];
    }

    /**
     * 当前应用的AI坐席chat_user id
     * @param string $appid
     * @return int
     */
    protected function getAiUserId(string $appid): int
    {
        /** @var AiAgentServices $agentServices */
        $agentServices = app()->make(AiAgentServices::class);
        return $agentServices->getAgentUserId($appid);
    }
}
