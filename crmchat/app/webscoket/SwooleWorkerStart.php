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


use crmeb\interfaces\ListenerInterface;
use Swoole\Server;
use Swoole\Timer;
use think\Config;
use think\swoole\App;

/**
 * Class SwooleWorkerStart
 * @package app\webscoket
 */
class SwooleWorkerStart implements ListenerInterface
{

    /**
     * @var \Swoole\WebSocket\Server
     */
    protected $server;

    /**
     * @var Config
     */
    protected $config;

    /**
     * 定时器执行间隔(毫秒)
     * @var int
     */
    protected $interval = 2000;

    /**
     * SwooleWorkerStart constructor.
     * @param Server $server
     * @param Config $config
     */
    public function __construct(Server $server, Config $config)
    {
        $this->server = $server;
        $this->config = $config;
    }

    /**
     * @param $event
     */
    public function handle($event): void
    {
        if (0 == $this->server->worker_id) {
            $this->clearStaleFds();
            $this->timer($event);
        }
        if ($this->server->worker_id == ($this->config->get('swoole.server.options.worker_num')) && $this->config->get('swoole.websocket.enable', false)) {
            $this->ping();
        }
    }

    /**
     * 清理上一次运行残留的连接注册表
     *
     * fd由onClose负责清理，但容器重启时连接直接消失、回调不会触发，Redis里的fd就成了孤儿。
     * 之后给这些用户推消息会抛"session#N does not exists"，中断整个消息处理：
     * 消息已入库却不再回帧，发送方看不到自己刚发的内容。每次部署都会踩到，故启动时清空。
     * @return void
     */
    protected function clearStaleFds()
    {
        try {
            $redis = \crmeb\services\CacheService::redisHandler();
            $keys = $redis->keys(Manager::WS_KEY_PREFIX . '*');
            foreach ($keys as $key) {
                //Redis扩展按配置可能返回带前缀的键名，删除时需剥离
                $redis->delete(str_replace($redis->getOption(\Redis::OPT_PREFIX) ?: '', '', $key));
            }
        } catch (\Throwable $e) {
            \think\facade\Log::error('清理websocket连接注册表失败：' . $e->getMessage());
        }
    }

    /**
     *
     */
    protected function ping()
    {
        /**
         * @var $pingService Ping
         */
        $pingService = app()->make(Ping::class);
        $server = $this->server;
        $timeout = intval($this->config->get('swoole.websocket.ping_timeout', 60000) / 1000);
        Timer::tick(1500, function (int $timer_id) use (&$server, &$pingService, $timeout) {
            $nowTime = time();
            foreach ($server->connections as $fd) {
                if ($server->isEstablished($fd)) {
                    $last = $pingService->getLastTime($fd);
                    if ($last && ($nowTime - $last) > $timeout) {
                        $server->push($fd, json_encode(['type' => 'timeout']));
                        $server->close($fd);
                    }
                }
            }
        });
    }

    /**
     * 开启定时器
     */
    protected function timer(App $app)
    {
        //开启定时器
        $last = time();
        $task = [6 => $last, 10 => $last, 30 => $last, 60 => $last, 180 => $last, 300 => $last];
        $this->timer = Timer::tick($this->interval, function () use (&$task, $app) {
            try {
                $now = time();
                event('Task_2');
                foreach ($task as $sec => $time) {
                    if ($now - $time >= $sec) {
                        event('Task_' . $sec);
                        $task[$sec] = $now;
                    }
                }
            } catch (\Throwable $e) {
                $app->log->error($e->getMessage());
            }
        });
    }

    /**
     * @param App $app
     */
    public function configMysql(App $app)
    {

    }

}
