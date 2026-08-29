<?php

namespace tests\unit;

use app\models\chat\ChatAutoReply;
use app\models\chat\ChatService;
use app\models\chat\ChatServiceRecord;
use PHPUnit\Framework\TestCase;

/**
 * 搜索器存在性回归测试
 *
 * BaseDao::withSearchSelect() 会静默丢弃没有对应搜索器的 where 条件，
 * 这里断言业务代码依赖的搜索器必须存在，防止过滤条件再次变成死代码。
 */
class SearchAttrRegressionTest extends TestCase
{
    /**
     * 各模型必须存在的搜索器清单
     * @return array
     */
    public function searcherProvider(): array
    {
        return [
            'ChatServiceRecord.appid'  => [ChatServiceRecord::class, 'searchAppidAttr'],
            'ChatService.appid'        => [ChatService::class, 'searchAppidAttr'],
            'ChatService.account'      => [ChatService::class, 'searchAccountAttr'],
            'ChatService.phone'        => [ChatService::class, 'searchPhoneAttr'],
            'ChatAutoReply.appid'      => [ChatAutoReply::class, 'searchAppidAttr'],
        ];
    }

    /**
     * @dataProvider searcherProvider
     * @param string $modelClass
     * @param string $method
     */
    public function testSearcherExists(string $modelClass, string $method)
    {
        $reflection = new \ReflectionClass($modelClass);
        $this->assertTrue(
            $reflection->hasMethod($method),
            sprintf('%s 缺少 %s，对应 where 条件会被 withSearchSelect 静默丢弃', $modelClass, $method)
        );
    }
}
