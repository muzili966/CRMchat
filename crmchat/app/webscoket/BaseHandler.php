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

namespace app\webscoket;

use app\jobs\ServiceTransfer;
use app\jobs\UniPush;
use app\models\chat\ChatServiceDialogueRecord;
use app\services\ai\AiAgentServices;
use app\services\ai\AiConfigServices;
use app\services\ai\AiReplyServices;
use app\services\ai\AiTransferServices;
use app\services\chat\ChatServiceAuxiliaryServices;
use app\services\chat\ChatServiceDialogueRecordServices;
use app\services\chat\ChatServiceRecordServices;
use app\services\chat\ChatServiceServices;
use app\services\chat\ChatUserServices;
use app\services\TenantPlanServices;
use crmeb\services\ai\AiPrompt;
use crmeb\services\tenant\TenantContext;
use crmeb\services\SwooleTaskService;
use crmeb\utils\Arr;
use Swoole\Timer;
use think\exception\ValidateException;
use think\facade\Log;

/**
 * socket 事件基础类
 * Class BaseHandler
 * @package app\webscoket
 */
abstract class BaseHandler
{

    /**
     * @var Manager
     */
    protected $manager;

    /**
     * @var Room
     */
    protected $room;

    /**
     * @var int
     */
    protected $fd;

    /**
     * 用户聊天端
     * @var int|null
     */
    protected $formType;

    /**
     * 登陆
     * @param array $data
     * @param Response $response
     * @return mixed
     */
    abstract public function login(array $data = [], Response $response);

    /**
     * 事件入口
     * @param $event
     * @return |null
     */
    public function handle($event)
    {
        [$method, $result, $manager, $room] = $event;
        $this->manager = $manager;
        $this->room = $room;
        $this->fd = array_shift($result);
        $this->formType = array_shift($result);
        if (method_exists($this, $method)) {
            return $this->{$method}(...$result);
        } else {
            Log::error('socket 回调事件' . $method . '不存在,消息内容为:' . json_encode($result));
            return null;
        }
    }

    /**
     * 聊天事件
     * @param array $data
     * @param Response $response
     * @return bool|\think\response\Json|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function chat(array $data = [], Response $response)
    {
        $user = $this->room->get($this->fd);
        if (!$user) {
            return $response->fail('聊天用户不存在');
        }
        $appId = $user['appid'];
        $to_user_id = $data['to_user_id'] ?? 0;
        $msn_type = $data['type'] ?? 0;
        $msn = $data['msn'] ?? '';
        $formType = $this->formType ?? null;
        $userId = $user['user_id'];
        $other = $data['other'] ?? [];
        $guid = $data['guid'] ?? 0;
        if (!$to_user_id) {
            return $response->message('err_tip', ['msg' => '用户不存在']);
        }
        if ($to_user_id == $userId) {
            return $response->message('err_tip', ['msg' => '不能和自己聊天']);
        }

        /** @var ChatUserServices $userService */
        $userService = app()->make(ChatUserServices::class);
        //接收人必须在当前租户内（查询被租户Scope约束），顺带取回后续要用的在线状态
        $toUserOnlineValue = $userService->value(['id' => $to_user_id], 'online');
        if (is_null($toUserOnlineValue)) {
            return $response->message('err_tip', ['msg' => '用户不存在']);
        }

        /** @var TenantPlanServices $planServices */
        $planServices = app()->make(TenantPlanServices::class);
        if (!$planServices->checkDailyMessage(TenantContext::id())) {
            return $response->message('err_tip', ['msg' => '今日消息量已达套餐上限，请联系管理员升级套餐']);
        }

        /** @var ChatServiceDialogueRecordServices $logServices */
        $logServices = app()->make(ChatServiceDialogueRecordServices::class);
        if (!in_array($msn_type, ChatServiceDialogueRecordServices::MSN_TYPE)) {
            return $response->message('err_tip', ['msg' => '格式错误']);
        }
        //文件消息按套餐开关：即便前端绕过上传直接发type=7也在此拦下
        if ($msn_type == ChatServiceDialogueRecordServices::MSN_TYPE_FILE
            && !$planServices->hasFeature(TenantContext::id(), 'file_send')) {
            return $response->message('err_tip', ['msg' => '当前套餐不支持发送文件']);
        }
        $msn = trim(strip_tags(str_replace(["\n", "\t", "\r", "&nbsp;"], '', htmlspecialchars_decode($msn))));
        $data = compact('to_user_id', 'msn_type', 'msn');
        $data['add_time'] = time();
        $data['appid'] = $appId;
        $data['user_id'] = $userId;
        $data['guid'] = $guid;
        $data['is_send'] = 1;

        $toUserFd = $this->manager->getUserIdByFds($to_user_id);

        $toUser = ['to_user_id' => -1];
        $fremaData = [];
        foreach ($toUserFd as $value) {
            if ($frem = $this->room->get($value)) {
                $fremaData[] = $frem;
                if ($frem['to_user_id'] == $userId) {
                    $toUser = $frem;
                }
            }
        }
        //是否在线
        $userOnline = count($fremaData) ? 1 : 0;
        //是否和当前用户对话
        $online = $toUserFd && $toUser && $toUser['to_user_id'] == $userId;
        $data['type'] = $online ? 1 : 0;
        if (in_array($msn_type, [5, 6])) {
            $data['other'] = json_encode($other);
        } else {
            $data['other'] = '';
        }
        $data = $logServices->save($data);
        $data = $data->toArray();
        $data['_add_time'] = $data['add_time'];
        $data['add_time'] = strtotime($data['add_time']);

        $_userInfo = $userService->getUserInfo($data['user_id'], ['nickname', 'avatar', 'version', 'is_tourist', 'online']);
        $isTourist = $_userInfo['is_tourist'];
        $data['nickname'] = $_userInfo['nickname'] ?? '';
        $data['avatar'] = $_userInfo['avatar'] ?? '';

        //用户向客服发送消息，判断当前客服是否在登录中
        /** @var ChatServiceRecordServices $serviceRecored */
        $serviceRecored = app()->make(ChatServiceRecordServices::class);
        $unMessagesCount = $logServices->getMessageNum(['user_id' => $userId, 'to_user_id' => $to_user_id, 'type' => 0]);
        //记录当前用户和他人聊天记录
        $data['recored'] = $serviceRecored->saveRecord(
            $user['appid'],
            $userId,
            $to_user_id,
            $msn,
            $formType ?? 0,
            $msn_type,
            $unMessagesCount,
            (int)$isTourist,
            $data['nickname'],
            $data['avatar'],
            $userOnline
        );
        $data['recored']['nickname'] = isset($_userInfo['version']) && $_userInfo['version'] ? '[' . $_userInfo['version'] . ']' . $data['recored']['nickname'] : $data['recored']['nickname'];
        $data['recored']['_update_time'] = date('Y-m-d H:i', $data['recored']['update_time']);
        /** @var ChatServiceServices $services */
        $services = app()->make(ChatServiceServices::class);
        $kefuInfo = $services->get(['user_id' => $to_user_id, 'appid' => $user['appid']], ['is_backstage', 'online', 'client_id', 'auto_reply']);
        if (!$kefuInfo) {
            $clientId = '';
            $auto_reply = false;
            $kefuOnline = false;
            $isBackstage = false;
        } else {
            $clientId = $kefuInfo->client_id;
            $auto_reply = !!$kefuInfo->auto_reply;
            $kefuOnline = !!$kefuInfo['online'];
            $isBackstage = !!$kefuInfo['is_backstage'];
        }

        //接收方是AI坐席：LLM调用交由task进程执行，避免阻塞ws worker事件循环
        if ($this->dispatchAiReply($appId, $userId, $to_user_id, [
            'msn' => $msn,
            'msn_type' => $msn_type,
            'other' => $other,
            'is_tourist' => $_userInfo['is_tourist'] ?? 0,
        ])) {
            return $response->message('chat', $data);
        }

        //开启自动回复（受套餐功能约束；Timer回调运行在新协程，wrap携带当前租户上下文）
        if ($auto_reply && $planServices->hasFeature(TenantContext::id(), 'auto_reply')) {
            $app = app();
            Timer::after(100, \crmeb\services\tenant\TenantContext::wrap(function () use ($app, $services, $appId, $to_user_id, $other, $msn_type, $userId, $msn, $response) {
                $data = $services->autoReply($app, $appId, $to_user_id, $userId, $msn, $msn_type, $other);
                if ($data) {
                    //给当前用户自动回复
                    $toUserFd = $this->manager->getUserIdByFds($userId);
                    $this->manager->pushing($toUserFd, $response->message('reply', $data)->getData());
                    //给对方回复消息
                    $toUserFd = $this->manager->getUserIdByFds($to_user_id);
                    $this->manager->pushing($toUserFd, $response->message('chat', $data)->getData());
                }
            }));
        }
        $toUserOnline = !!$toUserOnlineValue;
        //是否在线
        if ($online && $toUserOnline) {
            $this->manager->pushing($toUserFd, $response->message('reply', $data)->getData());
        } else {
            //用户在线，可是没有和当前用户进行聊天，给当前用户发送未读条数
            if ($toUserFd && $toUser['to_user_id'] != $userId && $isBackstage && $kefuOnline) {
                $data['recored']['nickname'] = $_userInfo['nickname'];
                $data['recored']['avatar'] = $_userInfo['avatar'];

                $data['recored']['online'] = $userOnline;
                $allUnMessagesCount = $logServices->getMessageNum([
                    'appid' => $user['appid'],
                    'to_user_id' => $to_user_id,
                    'type' => 0
                ]);

                $this->manager->pushing($toUserFd, $response->message('mssage_num', [
                    'user_id' => $userId,
                    'num' => $unMessagesCount,//某个用户的未读条数
                    'allNum' => $allUnMessagesCount,//总未读条数
                    'recored' => $data['recored']
                ])->getData());
            } else if ($kefuOnline && $clientId && $kefuInfo && $planServices->hasFeature(TenantContext::id(), 'app_push')) {
                //客服不在线,但是客服在app登录了,状态保持在线,发送app推送消息（受套餐功能约束）
                UniPush::dispatch([
                    ['nickname' => $data['nickname'], 'to_user_id' => $to_user_id, 'user_id' => $userId, 'appid' => $appId],
                    $clientId,
                    [
                        'content' => $msn,
                        'msn_type' => $data['msn_type'],
                        'other' => is_string($data['other']) ?
                            json_decode($data['other'], true) :
                            $data['other'],
                    ]
                ]);
            } else if (!$kefuOnline && $kefuInfo) {
                //客服不在线,app端也不在线,自动转接给在线的客服
                $this->authTransfer($response, $data['appid'], $userId, $to_user_id);
            }
        }

        $data['recored'] = $serviceRecored->get(['user_id' => $userId, 'to_user_id' => $to_user_id], ['*'], ['user']);
        if ($data['recored']) {
            $data['recored'] = $data['recored']->toArray();
            $data['recored']['_update_time'] = date('Y-m-d H:i', $data['recored']['update_time']);
            $data['recored']['nickname'] = isset($data['recored']['user']['version']) && $data['recored']['user']['version'] ? '[' . $data['recored']['user']['version'] . ']' . $data['recored']['nickname'] : $data['recored']['nickname'];
        }
        return $response->message('chat', $data);
    }

    /**
     * 聊天自动转接
     * @param Response $response
     * @param string $appid
     * @param $userId
     * @param $kfuUserId
     * @return bool
     */
    /**
     * 接收方为AI坐席时接管本条消息
     *
     * ws worker内只做轻量判定（准入/转人工关键词/精确关键词），
     * 耗时的LLM调用投递到task进程，其阻塞不影响本worker上的其他连接
     * @param string $appId
     * @param int $userId 访客chat_user id
     * @param int $toUserId 接收方chat_user id
     * @param array $message msn/msn_type/other/is_tourist
     * @return bool true=已由AI接管，调用方无需继续人工链路
     */
    protected function dispatchAiReply(string $appId, int $userId, int $toUserId, array $message): bool
    {
        /** @var AiAgentServices $agentServices */
        $agentServices = app()->make(AiAgentServices::class);
        if (!$agentServices->isAiAgent($toUserId)) {
            return false;
        }
        $ctx = [
            'appid' => $appId,
            'user_id' => $userId,
            'ai_user_id' => $toUserId,
            'msn' => $message['msn'],
            'msn_type' => (int)$message['msn_type'],
            'other' => is_array($message['other']) ? $message['other'] : [],
            'is_tourist' => (int)$message['is_tourist'],
            'tenant_id' => (int)TenantContext::id(),
        ];
        /** @var AiReplyServices $replyServices */
        $replyServices = app()->make(AiReplyServices::class);
        /** @var AiTransferServices $transferServices */
        $transferServices = app()->make(AiTransferServices::class);
        //已转人工的会话不再由AI应答，避免访客端未及时切换目标时被AI抢答
        if ($transferServices->isTransferred($appId, $userId, $toUserId)) {
            return false;
        }
        if ($this->transferToHuman($ctx)) {
            return true;
        }
        $denied = $replyServices->checkAdmission($ctx);
        if ($denied !== '') {
            $replyServices->reply($ctx, $denied, ChatServiceDialogueRecord::SOURCE_AI_LIMITED);
            return true;
        }
        SwooleTaskService::instance('aiReply')->data($ctx)->push();
        return true;
    }

    /**
     * 命中转人工关键词时把会话交还人工，无人在线则提示留言
     * @param array $ctx
     * @return bool true=已按转人工处理
     */
    protected function transferToHuman(array $ctx): bool
    {
        if ($ctx['msn_type'] !== ChatServiceDialogueRecordServices::MSN_TYPE_TXT) {
            return false;
        }
        /** @var AiConfigServices $configServices */
        $configServices = app()->make(AiConfigServices::class);
        $config = $configServices->getConfig($ctx['tenant_id']);
        $keywords = (string)($config['transfer_keywords'] ?? '');
        if (!AiPrompt::matchTransferKeyword($ctx['msn'], $keywords)) {
            return false;
        }
        /** @var AiTransferServices $transferServices */
        $transferServices = app()->make(AiTransferServices::class);
        $result = $transferServices->toHuman($ctx['appid'], $ctx['user_id'], $ctx['ai_user_id']);
        /** @var AiReplyServices $replyServices */
        $replyServices = app()->make(AiReplyServices::class);
        $replyServices->reply($ctx, $result['message'], ChatServiceDialogueRecord::SOURCE_AI_FALLBACK);
        return true;
    }

    protected function authTransfer(Response $response, string $appid, $userId, $kfuUserId)
    {
        /** @var ChatServiceServices $services */
        $services = app()->make(ChatServiceServices::class);
        //客服不在线,app端也不在线,自动转接给在线的客服
        $kefuUserInfo = $services->getColumn(['online' => 1, 'appid' => $appid], 'user_id,id');
        if (!$kefuUserInfo) {
            return $this->manager->pushing($userId, $response->message('kefu_logout', [
                'user_id' => $kfuUserId,
                'online' => 0
            ])->getData());
        }
        $userIds = array_column($kefuUserInfo, 'user_id');
        mt_srand();
        $kefuToUserId = $userIds[array_rand($userIds)] ?? 0;

        /** @var ChatServiceDialogueRecordServices $service */
        $service = app()->make(ChatServiceDialogueRecordServices::class);
        $where = ['chat' => [$kfuUserId, $userId]];
        $messageData = $service->getMessageOne($where);
        $messageData = $messageData ? $messageData->toArray() : [];

        try {
            /** @var ChatServiceRecordServices $serviceRecord */
            $serviceRecord = app()->make(ChatServiceRecordServices::class);
            $info = $serviceRecord->get(['user_id' => $kfuUserId, 'to_user_id' => $userId, 'appid' => $appid], ['id', 'user_id', 'to_user_id', 'type', 'message_type', 'is_tourist', 'avatar', 'nickname']);
            /** @var ChatServiceAuxiliaryServices $transfeerService */
            $transfeerService = app()->make(ChatServiceAuxiliaryServices::class);
            $record = $service->transaction(function () use ($info, $serviceRecord, $messageData, $appid, $transfeerService, $service, $kfuUserId, $userId, $kefuToUserId) {
                $record = $serviceRecord->saveRecord(
                    $appid,
                    $userId,
                    $kefuToUserId,
                    $messageData['msn'] ?? '',
                    $info['type'] ?? 1,
                    $messageData['message_type'] ?? 1,
                    0,
                    (int)($info['is_tourist'] ?? 0),
                    $info['nickname'] ?? "",
                    $info['avatar'] ?? ''
                );
                $res = $serviceRecord->delete(['user_id' => $kfuUserId, 'to_user_id' => $userId, 'appid' => $appid]);
                $res = $res && $serviceRecord->delete(['user_id' => $userId, 'to_user_id' => $kfuUserId, 'appid' => $appid]);
                $transfeerService->saveAuxliary([
                    'binding_id' => $userId,
                    'relation_id' => $kefuToUserId,
                    'appid' => $appid
                ]);
                if (!$record && !$res) {
                    throw new ValidateException('转接客服失败');
                }
                return $record;
            });

            $keufInfo = $services->get(['user_id' => $kfuUserId], ['avatar', 'nickname']);
            if ($keufInfo) {
                $keufInfo = $keufInfo->toArray();
            } else {
                $keufInfo = (object)[];
            }
            /** @var ChatUserServices $userService */
            $userService = app()->make(ChatUserServices::class);
            $version = $userService->value(['id' => $userId], 'version');
            if ($version) {
                $record['nickname'] = '[' . $version . ']' . $record['nickname'];
            }
            //给转接的客服发送消息通知
            $kefuToUserIdFd = $this->manager->getUserIdByFds($kefuToUserId);
            $this->manager->pushing($kefuToUserIdFd, $response->message('transfer', [
                'recored' => $record,
                'kefuInfo' => $keufInfo
            ]));
            //给当前客服发送此用户已被转接走的消息通知
            $kefuUserFd = $this->manager->getUserIdByFds($kfuUserId);
            if ($kefuUserFd) {
                $this->manager->pushing($kefuUserFd, $response->message('rm_transfer', [
                    'recored' => $info->toArray()
                ]));
            }
            //告知用户对接此用户聊天
            $keufToInfo = $services->get(['user_id' => $kefuToUserId], ['avatar', 'nickname']);
            $userIdFd = $this->manager->getUserIdByFds($userId);
            $this->manager->pushing($userIdFd, $response->message('to_transfer', [
                'toUid' => $kefuToUserId,
                'avatar' => $keufToInfo['avatar'] ?? '',
                'nickname' => $keufToInfo['nickname'] ?? ''
            ])->getData());

        } catch (\Exception $e) {
            Log::error('自动转接客服失败:' . $e->getMessage());
        }

    }

    /**
     * 切换用户聊天
     * @param array $data
     * @param Response $response
     * @return \think\response\Json
     */
    public function to_chat(array $data = [], Response $response)
    {
        $toUserId = $data['id'] ?? 0;
        $res = $this->room->get($this->fd);
        if ($res) {
            $userId = $res['user_id'];
            $this->manager->updateTabelField($userId, $toUserId);

            //不是游客进入记录
            if (!$res['tourist'] && $toUserId) {
                /** @var ChatServiceRecordServices $service */
                $service = app()->make(ChatServiceRecordServices::class);
                $service->update(['user_id' => $userId, 'to_user_id' => $toUserId], ['mssage_num' => 0]);
                /** @var ChatServiceDialogueRecordServices $logServices */
                $logServices = app()->make(ChatServiceDialogueRecordServices::class);
                $logServices->update(['user_id' => $toUserId, 'to_user_id' => $userId], ['type' => 1]);
            }
            return $response->message('mssage_num', ['user_id' => $toUserId, 'num' => 0, 'recored' => (object)[]]);
        }
    }

    /**
     * @param array $data
     * @param Response $response
     * @return \think\response\Json
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function open(array $data = [], Response $response)
    {
        $open = $data['open'] ?? 0;
        $res = $this->room->get($this->fd);
        if ($res) {
            $userId = $res['user_id'];
            $this->manager->updateTabelField($userId, $open, 'is_open');
        }
        return $response->message('open', ['message' => 'ok']);
    }

    /**
     * 测试原样返回
     * @param array $data
     * @param Response $response
     * @return bool|\think\response\Json|null
     */
    public function test(array $data = [], Response $response)
    {
        return $response->success($data);
    }

    /**
     * 关闭连接触发
     * @param array $data
     * @param Response $response
     */
    /**
     * 该用户是否还有其它存活连接
     *
     * onClose 已先行摘除本次的fd，故此处取到的都是其它连接
     * @param array $room 关闭连接的房间信息
     * @param int $userId
     * @return bool
     */
    protected function hasOtherConnection(array $room, int $userId): bool
    {
        try {
            $fds = Manager::userFd($room['type'] ?? '', $userId, isset($room['tenant_id']) ? (int)$room['tenant_id'] : null);
            if (!$fds) {
                return false;
            }
            /** @var \Swoole\Server $server */
            $server = app()->make(\Swoole\Server::class);
            foreach ($fds as $fd) {
                if ($server->isEstablished((int)$fd)) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            //判断失败时按"没有其它连接"处理，宁可置离线也不要把已下线的客服显示成在线
            Log::error('检查存活连接失败：' . $e->getMessage());
        }
        return false;
    }

    public function close(array $data = [], Response $response)
    {
        $usreId = $data['data']['user_id'] ?? 0;
        $appId = $data['data']['appid'] ?? '';
        //多标签页或刷新时，旧连接的关闭常晚于新连接的登录到达；此处若无条件置离线，
        //会把刚建好的会话标记成离线，服务端便只推未读数不推消息，
        //表现为"对方发了消息不进对话框、不响铃，要再刷新一次"
        if ($usreId && $this->hasOtherConnection($data['data'], $usreId)) {
            return;
        }
        if ($usreId) {
            /** @var ChatServiceServices $service */
            $service = app()->make(ChatServiceServices::class);
            if (!$service->value(['appid' => $appId, 'user_id' => $usreId], 'is_app')) {
                $service->update(['user_id' => $usreId], ['online' => 0]);
                /** @var ChatServiceRecordServices $recordSService */
                $recordSService = app()->make(ChatServiceRecordServices::class);
                $recordSService->updateRecord(['to_user_id' => $usreId], ['online' => 0]);
                /** @var ChatUserServices $userService */
                $userService = app()->make(ChatUserServices::class);
                $userService->update(['id' => $usreId], ['online' => 0]);
            }

            $this->manager->pushing($this->room->getKefuRoomByAppid($appId), $response->message('user_online', [
                'online' => 0,
                'user_id' => $usreId
            ]), $this->fd);

        }
    }
}
