<?php

namespace tests\unit;

use PHPUnit\Framework\TestCase;

/**
 * task进程推送方式回归
 *
 * Swoole禁止在task进程内再调用$server->task()，而SwooleTaskService::push()正是这么做的，
 * 且其内部try/catch会把异常吞成一条日志——故障表现为"消息入库了但客户端收不到，刷新才出现"，
 * 极难从现象定位。凡是运行在task进程里的代码，只能用$server->push()直推。
 */
class TaskWorkerPushTest extends TestCase
{
    /**
     * SwooleTaskListen里的方法都跑在task进程，故其调用链上的服务不得再投递task
     * @return array
     */
    public function taskWorkerFileProvider(): array
    {
        return [
            'AI回复服务' => [__DIR__ . '/../../app/services/ai/AiReplyServices.php'],
            'task监听器' => [__DIR__ . '/../../crmeb/listeners/SwooleTaskListen.php'],
        ];
    }

    /**
     * @dataProvider taskWorkerFileProvider
     * @param string $file
     */
    public function testTaskWorkerCodeDoesNotDispatchTask(string $file)
    {
        $this->assertFileExists($file);
        $code = $this->stripComments(file_get_contents($file));
        $this->assertStringNotContainsString(
            'SwooleTaskService::',
            $code,
            basename($file) . ' 运行在task进程，不能再投递task，请改用 $server->push() 直推'
        );
    }

    /**
     * 直推帧必须与Response::message()同构，否则前端匹配不到消息类型
     */
    public function testPushFrameShapeMatchesResponseMessage()
    {
        $type = 'reply';
        $data = ['msn' => 'hi'];
        $this->assertSame(
            json_encode(compact('type', 'data')),
            json_encode(['type' => $type, 'data' => $data])
        );
    }

    /**
     * 去掉注释，避免注释里提到的类名造成误判
     * @param string $code
     * @return string
     */
    protected function stripComments(string $code): string
    {
        $kept = '';
        foreach (token_get_all($code) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $kept .= is_array($token) ? $token[1] : $token;
        }
        return $kept;
    }
}
