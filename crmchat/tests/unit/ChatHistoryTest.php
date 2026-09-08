<?php

namespace tests\unit;

use app\services\chat\ChatHistoryServices;
use app\services\chat\ChatServiceDialogueRecordServices;
use crmeb\utils\ExportFile;
use crmeb\utils\XlsxWriter;
use PHPUnit\Framework\TestCase;

/**
 * 历史对话测试
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
        $this->assertGreaterThan(0, ChatHistoryServices::EXPORT_SESSION_MAX);
        $this->assertGreaterThan(0, ChatHistoryServices::EXPORT_TOTAL_MAX);
    }

    /**
     * 全局导出多两列会话归属，截断说明行必须与表头等宽，否则 Excel 错位
     */
    public function testNoteRowMatchesGlobalHeaderWidth()
    {
        $width = count(ChatHistoryServices::SESSION_HEADER) + count(ChatHistoryServices::MESSAGE_HEADER);
        $note = $this->invoke('noteRow', ['已截断']);
        $this->assertCount($width, $note);
        $this->assertSame('（已截断）', $note[$width - 1]);
        //说明只落在最后一列，前面必须留空
        $this->assertSame(array_fill(0, $width - 1, ''), array_slice($note, 0, $width - 1));
    }

    /**
     * 全局导出的每一行都要带上会话归属，且与表头列数一致
     */
    public function testSessionRowsCarryOwnershipColumns()
    {
        $header = array_merge(ChatHistoryServices::SESSION_HEADER, ChatHistoryServices::MESSAGE_HEADER);
        $row = array_merge(['小王', '游客A'], $this->invoke('messageRow', [
            ['add_time' => 1700000000, 'nickname' => '小王', 'is_agent' => 1, 'msn_type' => 1, 'msn' => '在的'],
        ]));
        $this->assertCount(count($header), $row);
        $this->assertSame('小王', $row[0]);
        $this->assertSame('游客A', $row[1]);
        $this->assertSame('客服', $row[4]);
    }

    /**
     * 格式来自前端，非白名单一律回落CSV，避免拼进文件名
     *
     * xlsx 还额外依赖 ext-zip：缺扩展时 normalizeFormat 按设计降级为 csv。
     * 生产镜像装了 zip，而单测容器是裸 php:7.4-cli 没有，故这一条随环境断言，
     * 写死 'xlsx' 会让 CI 恒挂。
     */
    public function testExportFormatIsWhitelisted()
    {
        //白名单成员资格与环境无关，单独钉住，避免上面那条随环境的断言退化成照抄实现
        $this->assertContains('xlsx', ExportFile::FORMATS);
        $this->assertSame(XlsxWriter::isSupported() ? 'xlsx' : 'csv', ExportFile::normalizeFormat('xlsx'));
        $this->assertSame('csv', ExportFile::normalizeFormat('csv'));
        $this->assertSame('csv', ExportFile::normalizeFormat('../../evil.php'));
        $this->assertSame('csv', ExportFile::normalizeFormat(null));
    }
}
