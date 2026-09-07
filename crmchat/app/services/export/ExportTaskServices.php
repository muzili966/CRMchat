<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\export;

use app\dao\ExportTaskDao;
use app\models\ExportTask;
use crmeb\basic\BaseServices;
use crmeb\exceptions\AdminException;
use crmeb\services\tenant\TenantContext;
use crmeb\utils\ExportFile;

/**
 * 下载中心
 *
 * 同步导出受限于请求超时与单进程内存，数据一多只能靠上限截断。
 * 这里把导出拆成"落一条任务"与"异步产出文件"，请求立即返回；
 * 文件带保留期，到期连同任务记录一起清掉。
 * Class ExportTaskServices
 * @package app\services\export
 */
class ExportTaskServices extends BaseServices
{
    /**
     * 导出类型：历史对话
     */
    const TYPE_CHAT_HISTORY = 'chat_history';

    /**
     * 类型到导出器的登记表，新增导出在此挂一行即可
     */
    const EXPORTERS = [
        self::TYPE_CHAT_HISTORY => ChatHistoryExporter::class,
    ];

    /**
     * 文件保留天数，到期由 gc 删除文件与记录
     */
    const KEEP_DAYS = 7;

    /**
     * 同一租户允许同时排队的任务数
     *
     * 导出是重活，不限制的话连点几十次就能把执行进程占满，
     * 后面所有租户的任务都被拖住。
     */
    const PENDING_MAX = 3;

    /**
     * 列表每页条数上限
     */
    const MAX_LIMIT = 100;

    /**
     * ExportTaskServices constructor.
     * @param ExportTaskDao $dao
     */
    public function __construct(ExportTaskDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 创建导出任务
     * @param array $data type/params/format/admin_id/admin_name
     * @return array 任务信息
     */
    public function create(array $data): array
    {
        $type = (string)($data['type'] ?? '');
        //类型来自前端，先确认登记过再落库，避免存进无人能执行的任务
        $this->exporter($type);
        $tenantId = (int)TenantContext::id();
        if ($this->pendingCount($tenantId) >= self::PENDING_MAX) {
            throw new AdminException('已有 ' . self::PENDING_MAX . ' 个导出在排队，请等待完成后再试');
        }
        $now = time();
        $task = $this->dao->save([
            'tenant_id' => $tenantId,
            'admin_id' => (int)($data['admin_id'] ?? 0),
            'admin_name' => (string)($data['admin_name'] ?? ''),
            'type' => $type,
            'type_name' => $this->exporter($type)->name(),
            'params' => json_encode($data['params'] ?? [], JSON_UNESCAPED_UNICODE),
            'format' => ExportFile::normalizeFormat($data['format'] ?? ''),
            'status' => ExportTask::STATUS_PENDING,
            'create_time' => $now,
        ]);
        if (!$task) {
            throw new AdminException('创建导出任务失败');
        }
        return $this->formatTask($task->toArray());
    }

    /**
     * 任务列表
     * @param array $where status/type/page/limit
     * @return array
     */
    public function getList(array $where): array
    {
        $search = ['status' => $where['status'] ?? '', 'type' => $where['type'] ?? ''];
        [$page, $limit] = $this->pageValue($where);
        $list = $this->dao->getTaskList($search, $page, $limit);
        return [
            'list' => array_map([$this, 'formatTask'], $list),
            'count' => $this->dao->count($search),
        ];
    }

    /**
     * 删除任务及其文件
     * @param int $id
     * @return bool
     */
    public function remove(int $id): bool
    {
        $task = $this->dao->get($id);
        if (!$task) {
            throw new AdminException('任务不存在');
        }
        if ((int)$task['status'] === ExportTask::STATUS_RUNNING) {
            throw new AdminException('任务正在生成中，请稍后再删');
        }
        $this->unlinkFile((string)$task['file_url']);
        return (bool)$this->dao->delete($id);
    }

    /**
     * 清理过期任务：先删文件再删记录
     *
     * 顺序不能反——先删记录再删文件的话，中途失败就再也找不到这个文件，
     * 它会永远留在导出目录里。
     * @return int 清理条数
     */
    public function gc(): int
    {
        $now = time();
        return (int)TenantContext::withoutTenant(function () use ($now) {
            $expired = $this->dao->expired($now);
            foreach ($expired as $task) {
                $this->unlinkFile((string)$task['file_url']);
            }
            $this->dao->deleteByIds(array_column($expired, 'id'));
            return count($expired);
        });
    }

    /**
     * 取类型对应的导出器
     * @param string $type
     * @return ExporterInterface
     */
    public function exporter(string $type): ExporterInterface
    {
        if (!isset(self::EXPORTERS[$type])) {
            throw new AdminException('不支持的导出类型');
        }
        return app()->make(self::EXPORTERS[$type]);
    }

    /**
     * 当前租户排队中的任务数
     * @param int $tenantId
     * @return int
     */
    protected function pendingCount(int $tenantId): int
    {
        return $this->dao->countActive($tenantId);
    }

    /**
     * 删除导出文件
     *
     * file_url 是入库的相对路径，仍要确认它落在导出目录内再删，
     * 防止脏数据把删除操作引到目录之外。
     * @param string $fileUrl
     * @return void
     */
    protected function unlinkFile(string $fileUrl)
    {
        $prefix = '/' . ExportFile::DIR;
        if (!$fileUrl || strpos($fileUrl, $prefix) !== 0 || strpos($fileUrl, '..') !== false) {
            return;
        }
        $path = root_path() . 'public' . $fileUrl;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * 补齐展示字段
     * @param array $task
     * @return array
     */
    protected function formatTask(array $task): array
    {
        //新建任务返回的模型只带写入字段，其余列取默认值而非报未定义索引
        $task += ['status' => ExportTask::STATUS_PENDING, 'file_url' => '', 'file_size' => 0,
            'row_count' => 0, 'message' => '', 'expire_time' => 0, 'create_time' => 0, 'finish_time' => 0];
        $task['status_text'] = ExportTask::STATUS_TEXT[(int)$task['status']] ?? '未知';
        $task['_create_time'] = $task['create_time'] ? date('Y-m-d H:i:s', (int)$task['create_time']) : '';
        $task['_finish_time'] = $task['finish_time'] ? date('Y-m-d H:i:s', (int)$task['finish_time']) : '';
        $task['_expire_time'] = $task['expire_time'] ? date('Y-m-d H:i', (int)$task['expire_time']) : '';
        //已过期的文件可能还没被 gc 扫到，列表里就不该再给下载入口
        $task['can_download'] = (int)((int)$task['status'] === ExportTask::STATUS_SUCCESS
            && $task['file_url'] && (int)$task['expire_time'] > time());
        unset($task['params']);
        return $task;
    }

    /**
     * @param array $where
     * @return array [page, limit]
     */
    protected function pageValue(array $where): array
    {
        $page = max(1, (int)($where['page'] ?? 1));
        $limit = (int)($where['limit'] ?? 20);
        $limit = $limit > 0 ? min($limit, self::MAX_LIMIT) : 20;
        return [$page, $limit];
    }
}
