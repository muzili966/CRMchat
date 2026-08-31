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


use app\services\ApplicationServices;
use app\services\TenantServices;
use crmeb\services\CacheService;
use crmeb\services\tenant\TenantContext;
use think\facade\Log;
use Swoole\Server;
use Swoole\Websocket\Frame;
use think\Event;
use think\response\Json;
use think\swoole\Websocket;
use think\swoole\websocket\Room;
use app\webscoket\Room as NowRoom;

/**
 * Class Manager
 * @package app\webscoket
 */
class Manager extends Websocket
{

    /**
     * @var Ping
     */
    protected $pingService;

    /**
     * @var int
     */
    protected $cache_timeout;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var \Redis
     */
    protected $cache;

    /**
     * @var NowRoom
     */
    protected $nowRoom;

    const USER_TYPE = ['admin', 'user', 'kefu'];

    /**
     * Manager constructor.
     * @param Server $server
     * @param Room $room
     * @param Event $event
     * @param Response $response
     * @param Ping $ping
     * @param \app\webscoket\Room $nowRoom
     */
    public function __construct(\think\App $app, Server $server, Room $room, Event $event, Response $response, Ping $ping, NowRoom $nowRoom)
    {
        parent::__construct($app, $server, $room, $event);
        $this->response = $response;
        $this->pingService = $ping;
        $this->nowRoom = $nowRoom;
        $this->cache = CacheService::redisHandler();
        $this->nowRoom->setCache($this->cache);
        $this->cache_timeout = intval(app()->config->get('swoole.websocket.ping_timeout', 60000) / 1000) + 2;
    }

    /**
     * @param int $fd
     * @param Request $request
     * @return mixed
     */
    public function onOpen($fd, \think\Request $request)
    {
        $type = $request->get('type');
        $token = $request->get('token');
        $app = $request->get('app', 0);
        if (!$token || !in_array($type, self::USER_TYPE)) {
            return $this->server->close($fd);
        }

        $this->nowRoom->type($type);

        try {
            $data = $this->exec($type, 'login', [$fd, $request->get('form_type', null), ['token' => $token, 'app' => $app], $this->response])->getData();
        } catch (\Throwable $e) {
            return $this->server->close($fd);
        }

        if ($data['status'] != 200) {
            return $this->server->close($fd);
        }

        //各端login内已建立租户上下文；此处兜底按appid解析，保证后续在线态key与fd表携带租户
        if (is_null(TenantContext::get())) {
            /** @var TenantServices $tenantServices */
            $tenantServices = app()->make(TenantServices::class);
            TenantContext::set($tenantServices->tenantIdByAppid((string)($data['data']['appid'] ?? '')));
        }

        $uid = $data['data']['uid'] ?? 0;

        if ($uid) {
            $this->login($type, $uid, $fd);
        }

        $this->nowRoom->add($fd, $data['data']['appid'] ?? '', $uid);
        $this->pingService->createPing($fd, time(), $this->cache_timeout);
        $this->send($fd, $this->response->message('ping', ['now' => time()]));
        return $this->send($fd, $this->response->success($data['data']));
    }

    public function login($type, $uid, $fd)
    {
        $key = self::wsKey($type);
        $this->cache->sadd($key, $fd);
        $this->cache->sadd($key . $uid, $fd);
        $this->refresh($type, $uid);
    }

    /**
     * 在线态key按租户隔离，避免跨租户定位到对方连接
     * @param string $type
     * @param string|int $uid
     * @param int|null $tenantId 不传时取当前租户上下文
     * @return string
     */
    /** 连接注册表的键前缀，启动时按此清理上次运行的残留 */
    const WS_KEY_PREFIX = '_ws_';

    public static function wsKey(string $type, $uid = '', ?int $tenantId = null): string
    {
        return self::WS_KEY_PREFIX . ($tenantId ?? TenantContext::id()) . '_' . $type . $uid;
    }

    /**
     * 用用户id获取fd
     * @param int $userId
     * @param string $type
     * @return bool|mixed|string
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getUserIdByFd(int $userId, string $type = '')
    {
        return $this->cache->sMembers(self::wsKey($type, $userId));
    }

    /**
     * 刷新key
     * @param $type
     * @param $uid
     */
    public function refresh($type, $uid)
    {
        $key = self::wsKey($type);
        $this->cache->expire($key, 1800);
        $this->cache->expire($key . $uid, 1800);
    }


    /**
     * @param int $userId
     * @return array
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getUserIdByFds(int $userId)
    {
        $toUserFd = [];
        foreach (['user', 'kefu'] as $type) {
            $toUserFd = array_merge($toUserFd, $this->getUserIdByFd($userId, $type) ?: []);
        }
        return array_merge(array_unique($toUserFd));
    }

    /**
     * @param int $userId
     * @param int $toUserId
     * @param string $field
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function updateTabelField(int $userId, int $toUserId, string $field = 'to_user_id')
    {
        $fds = $this->getUserIdByFds($userId);
        foreach ($fds as $fd) {
            $this->nowRoom->update($fd, $field, $toUserId);
        }
    }

    public function logout($type, $uid, $fd)
    {
        $key = self::wsKey($type);
        $this->cache->srem($key, $fd);
        $this->cache->srem($key . $uid, $fd);
    }

    /**
     * @param $type
     * @param string $uid
     * @param int|null $tenantId 任务进程等无协程上下文场景需显式传入
     * @return array
     */
    public static function userFd($type, $uid = '', ?int $tenantId = null)
    {
        return CacheService::redisHandler()->smembers(self::wsKey((string)$type, $uid, $tenantId)) ?: [];
    }

    /**
     * 执行事件调度
     * @param $type
     * @param $method
     * @param $result
     * @return null|Json
     */
    protected function exec($type, $method, $result)
    {
        if (!in_array($type, self::USER_TYPE)) {
            return null;
        }
        if (!is_array($result)) {
            return null;
        }
        /** @var Json $response */
        return $this->event->until('swoole.websocket.' . $type, [$method, $result, $this, $this->nowRoom]);
    }

    /**
     * @param Frame $frame
     * @return bool
     */
    public function onMessage(Frame $frame)
    {
        $info = $this->nowRoom->get($frame->fd);
        $result = json_decode($frame->data, true) ?: [];

        if (!isset($result['type']) || !$result['type']) return true;
        //消息处理运行在独立协程，按连接归属重建租户上下文
        TenantContext::set((int)($info['tenant_id'] ?? 0));
        $this->refresh($info['type'], $info['user_id']);
        if ($result['type'] == 'ping') {
            return $this->send($frame->fd, $this->response->message('ping', ['now' => time()]));
        }

        $data = $result['data'] ?? [];

        try {
            /** @var Response $res */
            $res = $this->exec($info['type'], $result['type'], [$frame->fd, $result['form_type'] ?? null, $data, $this->response]);
        } catch (\Throwable $e) {
            //原先不捕获：handler一旦抛错，客户端收不到任何帧只能干等，
            //消息其实已入库，于是表现为"发出去了但要刷新才看得到"
            Log::error(sprintf(
                'websocket事件[%s.%s]执行失败: %s @%s:%d',
                $info['type'] ?? '-', $result['type'], $e->getMessage(), $e->getFile(), $e->getLine()
            ));
            return $this->send($frame->fd, $this->response->message('err_tip', [
                'msg' => '消息处理失败，请重试',
                //客服与后台是已认证的内部账号，回传原因便于定位；访客端只给通用提示
                'reason' => in_array($info['type'] ?? '', ['kefu', 'admin'], true) ? $e->getMessage() : '',
            ]));
        }
        if ($res) return $this->send($frame->fd, $res);
        return true;
    }

    /**
     * 发送文本响应
     * @param $fd
     * @param Json $json
     * @return bool
     */
    public function send($fd, \think\response\Json $json)
    {
        $this->pingService->createPing($fd, time(), $this->cache_timeout);
        return $this->pushing($fd, $json->getData());
    }

    /**
     * 发送
     * @param $data
     * @return bool
     */
    public function pushing($fds, $data, $exclude = null)
    {
        if ($data instanceof \think\response\Json) {
            $data = $data->getData();
        }
        $data = is_array($data) ? json_encode($data) : $data;
        $fds = is_array($fds) ? $fds : [$fds];
        foreach ($fds as $fd) {
            if (!$fd) {
                continue;
            }
            if ($exclude && is_array($exclude) && !in_array($fd, $exclude)) {
                continue;
            } elseif ($exclude && $exclude == $fd) {
                continue;
            }
            //访客关闭页面后fd仍可能残留在注册表中，直接push会抛
            //"session#N does not exists"并中断整个消息处理：消息已入库却不再回帧，
            //发送方于是看不到自己刚发的内容。与task进程的推送口径保持一致，先判活。
            if (!$this->server->isEstablished((int)$fd)) {
                continue;
            }
            try {
                $this->server->push((int)$fd, $data);
            } catch (\Throwable $e) {
                //判活与推送之间仍可能断开，单个接收方失败不应影响其余接收方与发送方回显
                Log::warning('websocket推送失败 fd=' . $fd . ' ' . $e->getMessage());
            }
        }
        return true;
    }

    /**
     * 关闭连接
     * @param int $fd
     * @param int $reactorId
     */
    public function onClose($fd, $reactorId)
    {
        $tabfd = (string)$fd;
        if ($this->nowRoom->exist($fd)) {
            $data = $this->nowRoom->get($tabfd);
            //关闭回调运行在独立协程，按连接归属重建租户上下文
            TenantContext::set((int)($data['tenant_id'] ?? 0));
            $this->nowRoom->deleteFd($data['type'], $data['user_id'], $fd);
            $this->logout($data['type'], $data['user_id'], $fd);
            $this->nowRoom->type($data['type'])->del($tabfd);
            $this->exec($data['type'], 'close', [$fd, null, ['data' => $data], $this->response]);
        }
        $this->pingService->removePing($fd);
    }
}
