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

namespace app\services\chat;


use app\dao\chat\ChatServiceDao;
use app\jobs\WelcomeWords;
use app\services\ai\AiAgentServices;
use app\services\ai\AiConfigServices;
use app\services\ai\AiDispatcher;
use app\services\ApplicationServices;
use app\services\system\config\SystemConfigServices;
use app\services\TenantPlanServices;
use crmeb\services\tenant\TenantContext;
use crmeb\basic\BaseServices;
use crmeb\exceptions\AdminException;
use crmeb\services\DisyllabicWords;
use crmeb\services\FormBuilder;
use crmeb\services\SwooleTaskService;
use FormBuilder\Exception\FormBuilderException;
use PullWord\PullWord;
use Swoole\Timer;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\App;
use think\facade\Log;

/**
 * Class ChatServiceServices
 * @package app\services\chat
 */
class ChatServiceServices extends BaseServices
{

    /**
     * 创建form表单
     * @var FormBuilder
     */
    protected $builder;

    /**
     * ChatServiceServices constructor.
     * @param ChatServiceDao $dao
     * @param FormBuilder $builder
     */
    public function __construct(ChatServiceDao $dao, FormBuilder $builder)
    {
        $this->dao = $dao;
        $this->builder = $builder;
    }

    /**
     * 获取客服列表
     * @param array $where
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getServiceList(array $where)
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getServiceList($where, $page, $limit);
        $count = $this->dao->count($where);
        return compact('list', 'count');
    }

    /**
     * 创建客服表单
     * @param array $formData
     * @return mixed
     * @throws FormBuilderException
     */
    public function createServiceForm(array $formData = [])
    {
        if ($formData) {
            $field[] = $this->builder->input('appid', '应用ID', $formData['appid'])->disabled(true);
        } else {
            /** @var ApplicationServices $seervice */
            $seervice = app()->make(ApplicationServices::class);
            $field[] = $this->builder->select('appid', '请选择应用')->options($seervice->getOptions())->required();
        }
        $field[] = $this->builder->frameImage('avatar', '客服头像', $this->url('admin/widget.images/index', ['fodder' => 'avatar'], true), $formData['avatar'] ?? '')->icon('ios-add')->width('950px')->height('420px');
        $field[] = $this->builder->input('nickname', '客服名称', $formData['nickname'] ?? '')->col(24)->required();
        $field[] = $this->builder->input('phone', '手机号码', $formData['phone'] ?? '')->col(24)->required();
        if ($formData) {
            $field[] = $this->builder->input('account', '登录账号', $formData['account'] ?? '')->col(24)->required();
            $field[] = $this->builder->input('password', '登录密码')->type('password')->col(24);
            $field[] = $this->builder->input('true_password', '确认密码')->type('password')->col(24);
        } else {
            $field[] = $this->builder->input('account', '登录账号')->col(24)->required();
            $field[] = $this->builder->input('password', '登录密码')->type('password')->col(24)->required();
            $field[] = $this->builder->input('true_password', '确认密码')->type('password')->col(24)->required();
        }
        $field[] = $this->builder->textarea('welcome_words', '欢迎语', $formData['welcome_words'] ?? '');
        $field[] = $this->builder->switches('auto_reply', '自动回复', (int)($formData['auto_reply'] ?? 0))->falseValue(0)->trueValue(1)->openStr('打开')->closeStr('关闭')->size('large');
        $field[] = $this->builder->switches('status', '客服状态', (int)($formData['status'] ?? 0))->falseValue(0)->trueValue(1)->openStr('打开')->closeStr('关闭')->size('large');
        return $field;
    }

    /**
     * 创建客服获取表单
     * @return array
     * @throws FormBuilderException
     */
    public function create()
    {
        return create_form('添加客服', $this->createServiceForm(), $this->url('/chat/kefu'), 'POST');
    }

    /**
     * 编辑获取表单
     * @param int $id
     * @return array
     * @throws FormBuilderException
     */
    public function edit(int $id)
    {
        $serviceInfo = $this->dao->get($id);
        if (!$serviceInfo) {
            throw new AdminException('数据不存在!');
        }
        return create_form('编辑客服', $this->createServiceForm($serviceInfo->toArray()), $this->url('/chat/kefu/' . $id), 'PUT');
    }

    /**
     * 获取某人的聊天记录用户列表
     * @param int $uid
     * @return array|array[]
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getChatUser(int $userId)
    {
        /** @var ChatServiceDialogueRecordServices $serviceLog */
        $serviceLog = app()->make(ChatServiceDialogueRecordServices::class);
        /** @var ChatUserServices $serviceUser */
        $serviceUser = app()->make(ChatUserServices::class);
        $userIds = $serviceLog->getChatUserIds($userId);
        if (!$userIds) {
            return [];
        }
        return $serviceUser->getUserList(['ids' => $userIds], 'nickname,uid,avatar as headimgurl');
    }

    /**
     * 检查用户是否是客服
     * @param array $where
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function checkoutIsService(array $where)
    {
        return $this->dao->count($where) ? true : false;
    }

    /**
     * 查询聊天记录和获取客服uid
     * @param string $appId APPID
     * @param int $uid 当前用户uid
     * @param int $idTo 上翻页id
     * @param int $limit 展示条数
     * @param int $toUserId 客服id
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getRecord(string $appId, array $user, int $idTo, int $limit = 10, int $toUserId = 0, int $cookieUid = 0, int $kefuId = 0)
    {
        $uid = $user['uid'] ?? 0;
        /** @var ChatUserServices $userServices */
        $userServices = app()->make(ChatUserServices::class);
        //查找用户,没用自动生成游客
        $userInfo = $userServices->get(['uid' => $uid, 'appid' => $appId]);
        if (!$uid || !$userInfo) {
            /** @var ApplicationServices $appService */
            $appService = app()->make(ApplicationServices::class);
            //客服端已认证上下文中代建客户档案，无需用户签名
            $userInfo = $appService->createUser($appId, $user, false);
            $uid = $userInfo['uid'];
            $userId = $userInfo['id'];
        } else {
            $userId = $userInfo->id;
            $save = false;
            if (isset($user['nickname']) && $user['nickname'] && $user['nickname'] != $userInfo->nickname) {
                $save = true;
                $userInfo->nickname = $user['nickname'];
            }
            if (isset($user['avatar']) && $user['avatar'] && $user['avatar'] != $userInfo->avatar) {
                $save = true;
                $userInfo->nickname = $user['avatar'];
            }
            if ($save) {
                $userInfo->save();
            }
            $userInfo = $userInfo->toArray();
        }
        //分配决策统一交给AiDispatcher：三条粘性路径（回传坐席/转接绑定/上次坐席）都必须服从接待模式
        $decision = $this->decideAgent($appId, (int)$userId, [
            'passed_id' => (int)$toUserId,
            'kefu_id' => (int)$kefuId,
        ]);
        $toUserId = $decision['to_user_id'];
        if (!$toUserId) {
            //保留原拒客路径，访客端据此进入留言页
            throw new ValidateException('暂无客服人员在线，请稍后联系');
        }
        //真人上线接回访客时解除其与AI坐席的转接绑定，否则下次进线仍会命中旧关系
        if ($decision['switch_from_ai']) {
            /** @var ChatServiceAuxiliaryServices $transfeerService */
            $transfeerService = app()->make(ChatServiceAuxiliaryServices::class);
            $transfeerService->delete(['appid' => $appId, 'binding_id' => $userId]);
        }
        //组合数据
        $toUserInfo = $this->dao->get(['user_id' => $toUserId], ['nickname', 'avatar']);
        /** @var ChatServiceDialogueRecordServices $logServices */
        $logServices = app()->make(ChatServiceDialogueRecordServices::class);
        $result = [
            'serviceList' => [],
            'to_user_id' => $toUserId,
            'is_tourist' => $userInfo['is_tourist'],
            'uid' => $uid,
            'user_id' => $userId,
            'site_name' => sys_config('site_name'),
            'nickname' => $userInfo['nickname'],
            'avatar' => $userInfo['avatar'],
            'to_user_nickname' => $toUserInfo['nickname'],
            'to_user_avatar' => $toUserInfo['avatar']
        ];
        //查找聊天记录
        $serviceLogList = $logServices->getServiceChatList(['appid' => $appId, 'to_user_id' => $userId], $limit, $idTo);
        $result['serviceList'] = array_reverse($logServices->tidyChat($serviceLogList));
        try {
            //欢迎语（Timer回调运行在新协程，wrap携带当前租户上下文）
            $app = app();
            Timer::after(1000, \crmeb\services\tenant\TenantContext::wrap(function () use ($app, $appId, $toUserId, $userId, $userInfo) {
                $this->welcomeWords($app, $appId, $toUserId, $userId, $userInfo);
            }));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
        return $result;
    }

    /**
     * 为访客选择接待坐席（收集上下文后交由纯函数决策）
     * @param string $appId
     * @param int $userId 访客chat_user id
     * @param array $passed 访客端回传的坐席线索 passed_id/kefu_id
     * @return array AiDispatcher::decide 的结果
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function decideAgent(string $appId, int $userId, array $passed): array
    {
        $passedId = (int)$passed['passed_id'];
        //回传坐席仅作线索，是否可用由决策函数按模式判定
        $passedId = $this->dao->count(['appid' => $appId, 'status' => 1, 'user_id' => $passedId]) ? $passedId : 0;
        if (!$passedId && !empty($passed['kefu_id'])) {
            $passedId = (int)$this->dao->value(['appid' => $appId, 'status' => 1, 'id' => (int)$passed['kefu_id']], 'user_id');
        }

        /** @var ChatServiceAuxiliaryServices $transfeerService */
        $transfeerService = app()->make(ChatServiceAuxiliaryServices::class);
        $boundId = (int)$transfeerService->value(['appid' => $appId, 'binding_id' => $userId], 'relation_id');

        /** @var ChatServiceRecordServices $recordServices */
        $recordServices = app()->make(ChatServiceRecordServices::class);
        $latelyId = (int)$recordServices->getLatelyMsgUid(['appid' => $appId, 'to_user_id' => $userId], 'user_id');

        //在线候选只取真人，AI坐席由决策函数按模式单独决定
        $onlineList = $this->getServiceList(['appid' => $appId, 'status' => 1, 'online' => 1, 'is_ai' => 0]);
        $onlineHumanIds = array_map('intval', array_column($onlineList['list'] ?? [], 'user_id'));

        return AiDispatcher::decide([
            'mode' => $this->getAiMode($appId),
            'ai_user_id' => $this->getAiAgentUserId($appId),
            'passed_id' => $passedId,
            'bound_id' => $boundId,
            'lately_id' => $latelyId,
            'online_human_ids' => $onlineHumanIds,
        ]);
    }

    /**
     * 当前应用生效的AI接待模式（未开通或未启用返回空串）
     * @param string $appId
     * @return string
     */
    protected function getAiMode(string $appId): string
    {
        $tenantId = (int)TenantContext::id();
        if (!$tenantId) {
            return '';
        }
        /** @var TenantPlanServices $planServices */
        $planServices = app()->make(TenantPlanServices::class);
        if (!$planServices->canUseAi($tenantId)) {
            return '';
        }
        /** @var AiConfigServices $configServices */
        $configServices = app()->make(AiConfigServices::class);
        $config = $configServices->getConfig($tenantId);
        return AiConfigServices::isEffective($config) ? (string)$config['mode'] : '';
    }

    /**
     * 当前应用的AI坐席chat_user id
     * @param string $appId
     * @return int
     */
    protected function getAiAgentUserId(string $appId): int
    {
        /** @var AiAgentServices $agentServices */
        $agentServices = app()->make(AiAgentServices::class);
        return $agentServices->getAgentUserId($appId);
    }

    /**
     * 自动回复
     * @param string $appId
     * @param int $userId
     * @param int $toUserId
     * @param string $msg
     * @param int $msntype
     * @param array $other
     * @return array|bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function autoReply(App $app, string $appId, int $userId, int $toUserId, string $msg, int $msntype, array $other)
    {
        $this->dao->setApp($app);

        $data = [
            'add_time' => time(),
            'appid' => $appId,
            'user_id' => $userId,
            'to_user_id' => $toUserId,
            'msn_type' => $msntype,
            'type' => 1,
            'is_send' => 1
        ];
        if (in_array($msntype, [5, 6])) {
            $data['other'] = json_encode($other);
        } else {
            $data['other'] = '';
        }
        if (!$msg) {
            return false;
        }
        $data['msn'] = '';
        /** @var PullWord $words */
        $pullWord = $app->make(PullWord::class);
        $result = $pullWord->pull($msg)->toJson()->get();
        $result = json_decode($result, true);
        $keyword = [];
        foreach ($result as $item) {
            $keyword[] = $item['t'];
        }
        array_push($keyword, $msg);
        if ($keyword) {
            /** @var ChatAutoReplyServices $authReplyService */
            $authReplyService = $app->make(ChatAutoReplyServices::class);
            $reply = $authReplyService->setApp($app)->getReplyList(['keyword' => $keyword, 'appid' => $appId, 'user_id' => $userId]);
            if ($reply) {
                $data['msn'] = $reply[0]['content'];
            }
        }
        if (!$data['msn']) {
            return false;
        }
        /** @var ChatServiceDialogueRecordServices $logServices */
        $logServices = $app->make(ChatServiceDialogueRecordServices::class);
        $data = $logServices->setApp($app)->save($data);
        $data = $data->toArray();
        $data['_add_time'] = $data['add_time'];
        $data['add_time'] = strtotime($data['add_time']);

        /** @var ChatUserServices $userService */
        $userService = $app->make(ChatUserServices::class);
        $_userInfo = $userService->setApp($app)->getUserInfo($data['user_id'], ['nickname', 'avatar', 'is_tourist']);
        $isTourist = $_userInfo['is_tourist'];
        $data['nickname'] = $_userInfo['nickname'] ?? '';
        $data['avatar'] = $_userInfo['avatar'] ?? '';

        //用户向客服发送消息，判断当前客服是否在登录中
        /** @var ChatServiceRecordServices $serviceRecored */
        $serviceRecored = $app->make(ChatServiceRecordServices::class);
        $unMessagesCount = $logServices->setApp($app)->getMessageNum(['user_id' => $userId, 'to_user_id' => $toUserId, 'type' => 0]);
        //记录当前用户和他人聊天记录
        $data['recored'] = $serviceRecored->setApp($app)->saveRecord(
            $appId,
            $userId,
            $toUserId,
            $msg,
            $formType ?? 0,
            $msntype,
            $unMessagesCount,
            (int)$isTourist,
            $data['nickname'],
            $data['avatar'],
            0
        );
        return $data;
    }

    /**
     * 欢迎语
     * @param string $appId
     * @param int $userId
     * @param int $toUserId
     * @return array|bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function welcomeWords(App $app, string $appId, int $userId, int $toUserId, array $userInfo)
    {
        $this->dao->setApp($app);
        /** @var ChatServiceDialogueRecordServices $logServices */
        $logServices = $app->make(ChatServiceDialogueRecordServices::class);
        $unMessagesCount = $logServices->setApp($app)->chatCount($appId, $toUserId);
        /** @var ChatServiceDialogueRecordServices $logServices */
        $logServices = $app->make(ChatServiceDialogueRecordServices::class)->setApp($app);
        $msg = $this->dao->value(['user_id' => $userId, 'appid' => $appId], 'welcome_words');
        /** @var ChatUserServices $userService */
        $userService = $app->make(ChatUserServices::class)->setApp($app);
        /** @var ChatServiceRecordServices $serviceRecored */
        $serviceRecored = $app->make(ChatServiceRecordServices::class)->setApp($app);
        if (!$unMessagesCount && $msg) {
            $data = [
                'add_time' => time(),
                'appid' => $appId,
                'user_id' => $userId,
                'to_user_id' => $toUserId,
                'msn_type' => 1,
                'type' => 1,
                'is_send' => 1
            ];
            $data['other'] = '';
            $data['msn'] = $msg;
            $data = $logServices->save($data);
            $data = $data->toArray();
            $data['_add_time'] = $data['add_time'];
            $data['add_time'] = strtotime($data['add_time']);
            $_userInfo = $userService->setApp($app)->getUserInfo($data['user_id'], ['nickname', 'avatar', 'is_tourist', 'type']);
            $isTourist = $_userInfo['is_tourist'];
            $data['nickname'] = $_userInfo['nickname'] ?? '';
            $data['avatar'] = $_userInfo['avatar'] ?? '';
            $formType = $_userInfo['type'] ?? 0;
            $unMessagesCount = $logServices->setApp($app)->getMessageNum(['user_id' => $userId, 'to_user_id' => $toUserId, 'type' => 0]);
            //记录当前用户和他人聊天记录
            $online = $this->dao->value(['appid' => $appId, 'user_id' => $toUserId], 'online');
            $data['recored'] = $serviceRecored->setApp($app)->saveRecord(
                $appId,
                $userId,
                $toUserId,
                $msg,
                (int)$formType,
                1,
                $unMessagesCount,
                (int)$isTourist,
                $data['nickname'],
                $data['avatar'],
                $online ?: 0
            );
            //回复给用户
            SwooleTaskService::user($app)->type('reply')->to($toUserId)->data($data)->push();
        }
        //回复给客服
//        $nickname = $userInfo['nickname'] ?? '';
//        $avatar = $userInfo['avatar'] ?? '';
//        $formType = $userInfo['type'] ?? 0;
//        $isTourist = $userInfo['is_tourist'] ?? 0;
//        $serviceRecored->setApp($app);
//        $count = $serviceRecored->count(['appid' => $appId, 'user_id' => $userId, 'to_user_id' => $toUserId]);
//        $recored = $serviceRecored->saveRecord(
//            $appId,
//            $toUserId,
//            $userId,
//            $count ? '' : $msg,
//            (int)$formType,
//            1,
//            0,
//            (int)$isTourist,
//            $nickname,
//            $avatar,
//            1
//        );
//        if (isset($userInfo['version']) && $userInfo['version']) {
//            $recored['nickname'] = '[' . $userInfo['version'] . ']' . $recored['nickname'];
//        }
//        SwooleTaskService::kefu($app)->type('recored')->to($userId)->data($recored)->push();
    }
}
