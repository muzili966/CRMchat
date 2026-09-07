<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\utils;

use crmeb\exceptions\AdminException;

/**
 * 导出文件落盘
 *
 * 二维数组（首行表头）按格式写到 public 下的导出目录，返回可直接下载的相对URL。
 * 原本只在对账导出里私有实现，历史会话导出同样需要，抽出来避免两份。
 * Class ExportFile
 * @package crmeb\utils
 */
class ExportFile
{
    /**
     * 导出目录（public 下，静态可直接访问）
     */
    const DIR = 'uploads/export/';

    /** 逗号分隔，供外部系统直接导入 */
    const FORMAT_CSV = 'csv';

    /** xlsx，表头加粗、列宽自适应，便于人工核对 */
    const FORMAT_XLSX = 'xlsx';

    const FORMATS = [self::FORMAT_CSV, self::FORMAT_XLSX];

    /**
     * 收口外部传入的格式，非白名单一律回落CSV
     * @param mixed $format
     * @return string
     */
    public static function normalizeFormat($format): string
    {
        $format = is_string($format) ? $format : '';
        return in_array($format, self::FORMATS, true) ? $format : self::FORMAT_CSV;
    }

    /**
     * 按格式写出文件，返回相对URL路径
     * @param string $prefix 文件名前缀，须为安全字符
     * @param array $rows 二维数组，首行为表头
     * @param string $format
     * @param string $sheetName xlsx 的工作表名
     * @return string
     */
    public static function write(string $prefix, array $rows, string $format, string $sheetName = 'Sheet1'): string
    {
        //xlsx 依赖 zip 扩展，缺失时回落CSV而不是直接失败，导出功能不能因环境差异不可用
        if ($format !== self::FORMAT_XLSX || !XlsxWriter::isSupported()) {
            return self::writeCsv($prefix, $rows);
        }
        $fileName = self::fileName($prefix, 'xlsx');
        if (!XlsxWriter::write(self::dir() . $fileName, $rows, $sheetName)) {
            throw new AdminException('导出文件写入失败');
        }
        return '/' . self::DIR . $fileName;
    }

    /**
     * 写出CSV文件（带BOM便于Excel识别），返回相对URL路径
     * @param string $prefix
     * @param array $rows
     * @return string
     */
    protected static function writeCsv(string $prefix, array $rows): string
    {
        $fileName = self::fileName($prefix, 'csv');
        $lines = array_map(function ($row) {
            return implode(',', array_map(function ($cell) {
                return '"' . str_replace('"', '""', (string)$cell) . '"';
            }, $row));
        }, $rows);
        $content = "\xEF\xBB\xBF" . implode("\r\n", $lines);
        if (false === file_put_contents(self::dir() . $fileName, $content)) {
            throw new AdminException('导出文件写入失败');
        }
        return '/' . self::DIR . $fileName;
    }

    /**
     * 文件名：前缀 + 时间 + 随机串
     *
     * 导出目录在 public 下静态可访问，文件名就是唯一的访问凭证；
     * 随机部分必须足够长且不可预测，否则知道导出时间即可枚举出别人的文件。
     * @param string $prefix
     * @param string $ext
     * @return string
     */
    protected static function fileName(string $prefix, string $ext): string
    {
        //前缀可能来自业务拼接，剔除路径分隔等字符，防止写出目录之外
        $prefix = preg_replace('/[^A-Za-z0-9_\-]/', '', $prefix);
        return $prefix . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    }

    /**
     * 导出目录，不存在则创建
     * @return string
     */
    protected static function dir(): string
    {
        $dir = root_path() . 'public/' . self::DIR;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new AdminException('创建导出目录失败');
        }
        return $dir;
    }
}
