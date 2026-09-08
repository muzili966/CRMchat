<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\export;

/**
 * 支持分包导出的导出器
 *
 * 单表明细适合分析（筛选、排序、透视），分包适合归档与单独交付。
 * 实现本接口的导出器可以接受 zip 格式；未实现的仍只出单表。
 * Interface BundleExporterInterface
 * @package app\services\export
 */
interface BundleExporterInterface extends ExporterInterface
{
    /**
     * 逐个产出包内文件
     *
     * 必须是生成器：打包器写一个释放一个，内存只与单份表格有关。
     * @param array $params
     * @return \Generator 产出 ['name'=>文件名(不含扩展), 'rows'=>二维数组, 'index'=>索引行]
     */
    public function bundle(array $params): \Generator;

    /**
     * 索引文件的表头，末列须为文件名
     * @return array
     */
    public function indexHeader(): array;
}
