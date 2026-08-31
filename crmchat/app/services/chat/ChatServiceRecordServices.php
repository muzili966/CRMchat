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


use app\dao\chat\ChatServiceRecordDao;
use Carbon\Carbon;
use crmeb\basic\BaseServices;
use crmeb\utils\Str;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Model;

/**
 * Class ChatServiceRecordServices
 * @package app\services\chat
 * @method array|Model|null getLatelyMsgUid(array $where, string $key) 查询最近和用户聊天的uid用户
 */
class ChatServiceRecordServices extends BaseServices
{

    /**
     * ChatServiceRecordServices constructor.
     * @param ChatServiceRecordDao $dao
     */
    public function __construct(ChatServiceRecordDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取某个客服的客户列表
     * @param string $appid
     * @param int $userId
     * @param string $nickname
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getUserList(string $appid, int $userId, string $nickname, string $labelId)
    {
        $labelId = explode(',', $labelId);
        $labelId = array_filter($labelId);
        $list    = $this->dao->getServiceList(['appid' => $appid, 'label_id' => $labelId, 'user_id' => $userId, 'title' => $nickname, 'is_tourist' => 0], 0, 0, ['user']);
        foreach ($list as &$item) {
            if (isset($item['user']['remark_nickname']) && $item['user']['remark_nickname']) {
                $item['nickname'] = $item['user']['remark_nickname'];
            }
        }
        return $list;
    }

    /**
     * 获取客服用户聊天列表
     * @param string $appid
     * @param int $userId
     * @param string $nickname
     * @param int $isTourist
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    /** 会话归属口径：仅当前客服接待的 */
    const SCOPE_MINE = 'mine';

    /** 会话归属口径：本应用全部，含其他客服与AI坐席接待的 */
    const SCOPE_ALL = 'all';

    /**
     * 接待人id到名称与是否AI的映射
     * @param string $appid
     * @param array $userIds 会话行上的接待人chat_user id
     * @return array
     */
    protected function handlerMap(string $appid, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$userIds) {
            return [];
        }
        /** @var ChatServiceServices $services */
        $services = app()->make(ChatServiceServices::class);
        $rows = $services->getColumn([['appid', '=', $appid], ['user_id', 'IN', $userIds]], 'nickname,is_ai,user_id', 'user_id');
        return is_array($rows) ? $rows : [];
    }

    public function getServiceList(string $appid, int $userId, string $nickname, $isTourist = '', string $scope = self::SCOPE_MINE)
    {
        [$page, $limit] = $this->getPageValue();
        $where = ['appid' => $appid, 'title' => $nickname, 'is_tourist' => $isTourist];
        //全部会话不限接待人，AI坐席接待的会话因此也会出现在列表里
        if ($scope !== self::SCOPE_ALL) {
            $where['user_id'] = $userId;
        }
        $list = $this->dao->getServiceList($where, $page, $limit, ['user']);
        $handlers = self::SCOPE_ALL === $scope ? $this->handlerMap($appid, array_column($list, 'user_id')) : [];
        foreach ($list as &$item) {
            if ($item['message_type'] == 1) {
                $item['message'] = $this->getMessage($item['message']);
            }
            $item['_update_time'] = date('Y-m-d H:i', $item['update_time']);
            if (isset($item['user']['remark_nickname']) && $item['user']['remark_nickname']) {
                $item['nickname'] = $item['user']['remark_nickname'];
            }
            if (isset($item['user']['version']) && $item['user']['version']) {
                $item['nickname'] = '[' . $item['user']['version'] . ']' . $item['nickname'];
            }
            //前端据此标记接待人与是否AI接待
            $handler = $handlers[$item['user_id']] ?? null;
            $item['handler_name'] = $handler['nickname'] ?? '';
            $item['is_ai'] = (int)($handler['is_ai'] ?? 0);
            $item['is_mine'] = (int)($item['user_id'] == $userId);
        }
        return $list;
    }

    /**
     * 聊天消息格式化
     * @param string $str
     * @return string
     */
    protected function getMessage(string $str)
    {
        $str = preg_replace('/\[em-[a-z_]+\]/', '[表情]', $str, -1);
        $str = Str::truncateUtf8String($str, 15, '');
        foreach (['[', '[表', '[表情'] as $item) {
            if (($num = mb_strrpos($str, $item)) !== false) {
                $str = mb_substr($str, 0, $num);
                break;
            }
        }
        return $str;
    }

    /**
     * 更新客服用户信息
     * @param int $uid
     * @param array $data
     * @return mixed
     */
    public function updateRecord(array $where, array $data)
    {
        return $this->dao->update($where, $data);
    }

    /**
     * @param App $app
     * @return $this
     */
    public function setApp(App $app)
    {
        $this->dao->setApp($app);
        return $this;
    }

    /**
     * 写入聊天相关人数据
     * @param int $uid
     * @param int $toUid
     * @param string $message
     * @param int $type
     * @param int $messageType
     * @param int $num
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function saveRecord(string $appid, int $userId, int $toUserid, string $message, int $type, int $messageType, int $num, int $isTourist = 0, string $nickname = '', string $avatar = '', int $online = 0)
    {
        if ($message) {
            switch ((int)$messageType) {
                case ChatServiceDialogueRecordServices::MSN_TYPE_EMOT:
                    $message = '[表情]';
                    break;
                case ChatServiceDialogueRecordServices::MSN_TYPE_IME:
                    $message = '[图片]';
                    break;
                case ChatServiceDialogueRecordServices::MSN_TYPE_VOICE:
                    $message = '[音频]';
                    break;
                case ChatServiceDialogueRecordServices::MSN_TYPE_ORDER:
                case ChatServiceDialogueRecordServices::MSN_TYPE_GOODS:
                    $message = '[图文]' . ($message['other']['store_name'] ?? '');
                    break;
            }
        }
        $info = $this->dao->get(['appid' => $appid, 'user_id' => $toUserid, 'to_user_id' => $userId]);
        if ($info) {
            $info->type = $type;
            if ($message !== '') $info->message = $message;
            $info->message_type = $messageType;
            $info->update_time  = time();
            $info->mssage_num   = $num;
            $info->online       = $online;
            if ($avatar) $info->avatar = $avatar;
            if ($nickname) $info->nickname = $nickname;
            $info->save();
            $this->dao->update(['user_id' => $userId, 'to_user_id' => $toUserid], ['update_time' => time(), 'message' => $message, 'message_type' => $messageType]);
//            return $info->toArray();
        } else {
            $info = $this->dao->save([
                'user_id'      => $toUserid,
                'to_user_id'   => $userId,
                'type'         => $type,
                'online'       => $online,
                'message'      => $message,
                'avatar'       => $avatar,
                'nickname'     => $nickname,
                'message_type' => $messageType,
                'mssage_num'   => $num,
                'add_time'     => time(),
                'update_time'  => time(),
                'is_tourist'   => $isTourist,
                'appid'        => $appid
            ]);//->toArray();
        }

        $info->append(['user']);

        $data = $info->toArray();
        if (isset($data['user']['version']) && $data['user']['version']) {
            $data['nickname'] = '[' . $data['user']['version'] . ']' . $data['nickname'];
        }
        return $data;
    }

    /**
     * 统计客户人数
     * @param int $id
     * @return array
     */
    public function getKefuSum(int $id = 0)
    {
        $all          = $this->dao->count(['user_id' => $id]);
        $toDayKefu    = $this->dao->count(['time' => 'today', 'user_id' => $id, 'is_tourist' => 1]);
        $month        = $this->dao->count(['time' => 'month', 'user_id' => $id]);
        $toDayTourist = $this->dao->count(['time' => 'today', 'user_id' => $id, 'is_tourist' => 0]);
        return compact('all', 'toDayKefu', 'month', 'toDayTourist');
    }

    /**
     * 获取统计数据
     * @param int $id
     * @param int $year
     * @param int $month
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getKefuStatistics(int $id, int $year, int $month)
    {
        $date      = Carbon::create($year, $month);
        $startTime = strtotime($date->startOfMonth()->toDateTimeString());
        $endTime   = strtotime($date->lastOfMonth()->toDateTimeString()) + 86399;

        return [
            'list'    => $this->dao->kefuStatistics([
                'user_id'    => $id,
                'is_tourist' => 0,
                'startTime'  => $startTime,
                'endTime'    => $endTime,
            ]),
            'tourist' => $this->dao->kefuStatistics([
                'user_id'    => $id,
                'is_tourist' => 1,
                'startTime'  => $startTime,
                'endTime'    => $endTime,
            ])
        ];
    }
}
