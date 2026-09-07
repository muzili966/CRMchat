<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\export;

use app\models\ExportTask;
use crmeb\services\tenant\TenantContext;
use crmeb\utils\ExportFile;
use think\facade\Db;

/**
 * 导出任务执行器
 *
 * 常驻进程定时调用（见 SwooleWorkerStart），也可用 php think export:run 手动跑。
 * 领取用条件更新做原子占位，多个进程同时扫表也不会把同一条任务跑两遍。
 * Class ExportRunnerServices
 * @package app\services\export
 */
class ExportRunnerServices
{
    /**
     * 单次调用最多处理的任务数，避免一次占用进程过久
     */
    const BATCH = 5;

    /**
     * 处理中超过该秒数视为进程已崩溃，允许重新领取
     */
    const STALE_SECONDS = 1800;

    /**
     * @var ExportTaskServices
     */
    protected $taskServices;

    /**
     * @param ExportTaskServices $taskServices
     */
    public function __construct(ExportTaskServices $taskServices)
    {
        $this->taskServices = $taskServices;
    }

    /**
     * 领取并执行待处理任务
     * @param int $batch
     * @return array [成功数, 失败数]
     */
    public function run(int $batch = self::BATCH): array
    {
        $this->requeueStale();
        $ok = $failed = 0;
        for ($i = 0; $i < $batch; $i++) {
            $task = $this->claim();
            if (!$task) {
                break;
            }
            $this->execute($task) ? $ok++ : $failed++;
        }
        return [$ok, $failed];
    }

    /**
     * 原子领取一条待处理任务
     *
     * 条件更新的影响行数就是有没有抢到，不需要额外的锁。
     * @return array|null
     */
    protected function claim()
    {
        $task = $this->table()->where('status', ExportTask::STATUS_PENDING)->order('id ASC')->find();
        if (!$task) {
            return null;
        }
        $affected = $this->table()
            ->where('id', $task['id'])
            ->where('status', ExportTask::STATUS_PENDING)
            ->update(['status' => ExportTask::STATUS_RUNNING, 'start_time' => time()]);
        //没抢到说明已被别的进程领走，交给下一轮
        return $affected ? $task : null;
    }

    /**
     * 把卡在处理中的任务放回队列
     *
     * 执行进程被 kill 时任务会永远停在"生成中"，用户既等不到文件也删不掉，
     * 超时后放回队列由下一轮重跑。
     * @return void
     */
    protected function requeueStale()
    {
        $this->table()
            ->where('status', ExportTask::STATUS_RUNNING)
            ->where('start_time', '<', time() - self::STALE_SECONDS)
            ->update(['status' => ExportTask::STATUS_PENDING, 'start_time' => 0]);
    }

    /**
     * 执行一条任务
     * @param array $task
     * @return bool
     */
    protected function execute(array $task): bool
    {
        try {
            //任务跨租户执行，必须切到任务所属租户，否则导出器读不到数据
            $result = TenantContext::runAs((int)$task['tenant_id'], function () use ($task) {
                $exporter = $this->taskServices->exporter((string)$task['type']);
                $params = json_decode((string)$task['params'], true);
                $rows = $exporter->rows(is_array($params) ? $params : []);
                $url = ExportFile::write($exporter->prefix(), $rows, (string)$task['format'], $exporter->sheetName());
                return ['url' => $url, 'count' => max(0, count($rows) - 1)];
            });
            $this->finish($task, $result);
            return true;
        } catch (\Throwable $e) {
            //失败原因要留给用户看，不能只写进日志
            $this->table()->where('id', $task['id'])->update([
                'status' => ExportTask::STATUS_FAILED,
                'message' => mb_substr($e->getMessage(), 0, 200),
                'finish_time' => time(),
            ]);
            return false;
        }
    }

    /**
     * 标记完成并记录文件信息
     * @param array $task
     * @param array $result
     * @return void
     */
    protected function finish(array $task, array $result)
    {
        $path = root_path() . 'public' . $result['url'];
        $this->table()->where('id', $task['id'])->update([
            'status' => ExportTask::STATUS_SUCCESS,
            'file_url' => $result['url'],
            'file_size' => is_file($path) ? (int)filesize($path) : 0,
            'row_count' => (int)$result['count'],
            'expire_time' => time() + ExportTaskServices::KEEP_DAYS * 86400,
            'finish_time' => time(),
        ]);
    }

    /**
     * 任务表：执行器跨租户扫描，显式绕过租户隔离
     * @return \think\db\Query
     */
    protected function table()
    {
        return Db::name('export_task');
    }
}
