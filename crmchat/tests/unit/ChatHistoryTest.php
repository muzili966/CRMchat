<?php

namespace tests\unit;

use app\services\chat\ChatHistoryServices;
use app\services\chat\ChatServiceDialogueRecordServices;
use crmeb\utils\ExportFile;
use PHPUnit\Framework\TestCase;

/**
 * 历史会话测试
 *
 * 这个接口对外暴露分页参数，limit 不设上限时客户端一次就能把整张消息表拉走；
 * 边界收敛在 pageValue 里，故重点钉死它。
 */
class ChatHistoryTest extends TestCase
{
    /**
     * @var ChatHistoryServices
     */
    protected $svc;

    protected function setUp(): void
    {
        $this->svc = new ChatHistoryServices();
    }

    protected function pageValue(array $where): array
    {
        $m = new \ReflectionMethod($this->svc, 'pageValue');
        $m->setAccessible(true);
        return $m->invoke($this->svc, $where);
    }

    public function testDefaultsWhenNothingGiven()
    {
        $this->assertSame([1, 20], $this->pageValue([]));
    }

    /**
     * 超大 limit 必须被夹到上限，否则一次请求可拖走整表
     */
    public function testOversizeLimitIsClamped()
    {
        [, $limit] = $this->pageValue(['limit' => 100000]);
        $this->assertSame(ChatHistoryServices::MAX_LIMIT, $limit);
    }

    /**
     * 非法页码/条数不能变成 0 或负数传进 SQL
     */
    public function testInvalidValuesFallBackToSafeDefaults()
    {
        $this->assertSame([1, 20], $this->pageValue(['page' => 0, 'limit' => 0]));
        $this->assertSame([1, 20], $this->pageValue(['page' => -5, 'limit' => -1]));
        $this->assertSame([1, 20], $this->pageValue(['page' => 'abc', 'limit' => 'xyz']));
    }

    public function testNormalValuesPassThrough()
    {
        $this->assertSame([3, 50], $this->pageValue(['page' => 3, 'limit' => 50]));
        $this->assertSame([2, ChatHistoryServices::MAX_LIMIT], $this->pageValue(['page' => 2, 'limit' => ChatHistoryServices::MAX_LIMIT]));
    }

    public function testMaxLimitIsSane()
    {
        $this->assertGreaterThan(0, ChatHistoryServices::MAX_LIMIT);
        $this->assertLessThanOrEqual(200, ChatHistoryServices::MAX_LIMIT);
    }

    protected function invoke(string $method, array $args)
    {
        $m = new \ReflectionMethod($this->svc, $method);
        $m->setAccessible(true);
        return $m->invokeArgs($this->svc, $args);
    }

    /**
     * 导出正文：富文本必须压成单行纯文本，否则会把表格撑破
     */
    public function testPlainContentFlattensRichText()
    {
        $txt = ChatServiceDialogueRecordServices::MSN_TYPE_TXT;
        $this->assertSame('你好 请问在吗', $this->invoke('plainContent', [$txt, "<b>你好</b>\n请问在吗"]));
        $this->assertSame('a & b', $this->invoke('plainContent', [$txt, 'a &amp; b']));
    }

    /**
     * 文件消息的 msn 是 base64(JSON)，导出要还原成「文件名 URL」
     */
    public function testPlainContentDecodesFileMessage()
    {
        $file = ChatServiceDialogueRecordServices::MSN_TYPE_FILE;
        $msn = base64_encode(json_encode(['url' => '/uploads/a.pdf', 'name' => '报价单.pdf', 'size' => 1, 'ext' => 'pdf']));
        $this->assertSame('报价单.pdf /uploads/a.pdf', $this->invoke('plainContent', [$file, $msn]));
        //解不开也不能抛异常，整段导出不该被一条坏消息毁掉
        $this->assertSame('[文件]', $this->invoke('plainContent', [$file, '不是base64@@@']));
    }

    /**
     * 表头必须在首行，且与每行列数一致，否则 Excel 打开错位
     */
    public function testExportRowsShapeIsConsistent()
    {
        $rows = $this->invoke('exportRows', [[
            ['add_time' => 1700000000, 'nickname' => '小王', 'is_agent' => 1, 'msn_type' => 1, 'msn' => '在的'],
            ['add_time' => 0, 'nickname' => '', 'is_agent' => 0, 'msn_type' => 0, 'msn' => '（已截断）'],
        ]]);
        $this->assertSame(['时间', '发送者', '身份', '类型', '内容'], $rows[0]);
        foreach ($rows as $row) {
            $this->assertCount(5, $row);
        }
        $this->assertSame('客服', $rows[1][2]);
        $this->assertSame('文本', $rows[1][3]);
        //截断说明行没有时间，不能被当成一条真实消息渲染出身份和类型
        $this->assertSame(['', '', '', '', '（已截断）'], $rows[2]);
    }

    /**
     * 导出上限须能被批次整除地覆盖，且批次不能大于上限
     */
    public function testExportBoundsAreSane()
    {
        $this->assertGreaterThan(0, ChatHistoryServices::EXPORT_CHUNK);
        $this->assertLessThanOrEqual(ChatHistoryServices::EXPORT_MAX, ChatHistoryServices::EXPORT_CHUNK);
    }

    /**
     * 格式来自前端，非白名单一律回落CSV，避免拼进文件名
     */
    public function testExportFormatIsWhitelisted()
    {
        $this->assertSame('xlsx', ExportFile::normalizeFormat('xlsx'));
        $this->assertSame('csv', ExportFile::normalizeFormat('csv'));
        $this->assertSame('csv', ExportFile::normalizeFormat('../../evil.php'));
        $this->assertSame('csv', ExportFile::normalizeFormat(null));
    }
}
