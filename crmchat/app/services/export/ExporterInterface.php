<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\export;

/**
 * 导出器
 *
 * 下载中心只负责排队、落盘、保留期与下载，具体导什么由各导出器提供；
 * 新增一种导出只需实现本接口并在 ExportTaskServices::EXPORTERS 登记。
 * Interface ExporterInterface
 * @package app\services\export
 */
interface ExporterInterface
{
    /**
     * 导出类型的中文名，展示在下载中心列表
     * @return string
     */
    public function name(): string;

    /**
     * 文件名前缀
     * @return string
     */
    public function prefix(): string;

    /**
     * xlsx 的工作表名
     * @return string
     */
    public function sheetName(): string;

    /**
     * 产出表格数据
     * @param array $params 创建任务时保存的筛选条件
     * @return array 二维数组，首行为表头
     */
    public function rows(array $params): array;
}
