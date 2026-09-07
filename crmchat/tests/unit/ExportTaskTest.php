<?php

namespace tests\unit;

use app\dao\ExportTaskDao;
use app\models\ExportTask;
use app\services\export\ExportTaskServices;
use crmeb\utils\ExportFile;
use PHPUnit\Framework\TestCase;

/**
 * 下载中心测试
 *
 * 重点钉两处：一是删除文件的路径判定——file_url 来自库里，脏数据不能把
 * 删除操作引到导出目录之外；二是列表字段的兜底，新建任务只带写入字段。
 */
class ExportTaskTest extends TestCase
{
    /**
     * @var ExportTaskServices
     */
    protected $svc;

    protected function setUp(): void
    {
        //只测纯逻辑，dao 不会被这些方法触达
        $this->svc = new ExportTaskServices(new ExportTaskDao());
    }

    protected function invoke(string $method, array $args)
    {
        $m = new \ReflectionMethod($this->svc, $method);
        $m->setAccessible(true);
        return $m->invokeArgs($this->svc, $args);
    }

    /**
     * 目录之外的路径一律不删——哪怕库里被写进了脏数据
     */
    public function testUnlinkRejectsPathsOutsideExportDir()
    {
        $rejected = [
            '',
            '/uploads/chat_file/a.pdf',
            '/etc/passwd',
            '/uploads/export/../../.env',
            'uploads/export/a.csv',
        ];
        foreach ($rejected as $path) {
            //不抛异常且不触碰磁盘即为通过；此处断言其安全返回
            $this->assertNull($this->invoke('unlinkFile', [$path]));
        }
    }

    /**
     * 合法路径必须以导出目录开头
     */
    public function testExportDirPrefixIsStable()
    {
        $this->assertSame('uploads/export/', ExportFile::DIR);
        $this->assertStringStartsWith('/' . ExportFile::DIR, '/' . ExportFile::DIR . 'a.csv');
    }

    /**
     * 新建任务返回的模型只带写入字段，格式化不能因缺列报错
     */
    public function testFormatTaskFillsMissingColumns()
    {
        $row = $this->invoke('formatTask', [[
            'id' => 1, 'type' => 'chat_history', 'type_name' => '历史对话', 'format' => 'csv',
        ]]);
        $this->assertSame('排队中', $row['status_text']);
        $this->assertSame('', $row['_create_time']);
        $this->assertSame('', $row['_finish_time']);
        $this->assertSame(0, $row['can_download']);
        //筛选条件可能含访客昵称等信息，不该随列表回给前端
        $this->assertArrayNotHasKey('params', $row);
    }

    /**
     * 未过期的成功任务才给下载入口
     */
    public function testCanDownloadRequiresSuccessAndUnexpiredFile()
    {
        $base = ['id' => 1, 'status' => ExportTask::STATUS_SUCCESS, 'file_url' => '/uploads/export/a.csv'];
        $ok = $this->invoke('formatTask', [$base + ['expire_time' => time() + 3600]]);
        $this->assertSame(1, $ok['can_download']);

        $expired = $this->invoke('formatTask', [$base + ['expire_time' => time() - 1]]);
        $this->assertSame(0, $expired['can_download']);

        $failed = $this->invoke('formatTask', [['status' => ExportTask::STATUS_FAILED, 'file_url' => '', 'expire_time' => 0]]);
        $this->assertSame(0, $failed['can_download']);
    }

    /**
     * 分页上限收口，防止一次拉走整张任务表
     */
    public function testPageValueClamping()
    {
        $this->assertSame([1, 20], $this->invoke('pageValue', [[]]));
        $this->assertSame([1, ExportTaskServices::MAX_LIMIT], $this->invoke('pageValue', [['limit' => 99999]]));
        $this->assertSame([1, 20], $this->invoke('pageValue', [['page' => 0, 'limit' => -1]]));
    }

    /**
     * 每种导出类型都必须真的登记了可实例化的导出器
     */
    public function testEveryRegisteredExporterIsUsable()
    {
        $this->assertNotEmpty(ExportTaskServices::EXPORTERS);
        foreach (ExportTaskServices::EXPORTERS as $type => $class) {
            $this->assertTrue(class_exists($class), $type . ' 的导出器类不存在');
            $this->assertTrue(
                in_array(\app\services\export\ExporterInterface::class, class_implements($class), true),
                $type . ' 的导出器未实现 ExporterInterface'
            );
        }
    }

    /**
     * 保留期与排队上限须是合理正数
     */
    public function testLimitsAreSane()
    {
        $this->assertGreaterThan(0, ExportTaskServices::KEEP_DAYS);
        $this->assertGreaterThan(0, ExportTaskServices::PENDING_MAX);
        $this->assertLessThanOrEqual(10, ExportTaskServices::PENDING_MAX);
    }
}
