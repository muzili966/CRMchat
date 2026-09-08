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

    /** 导出格式：按分组拆成多个xlsx再打成zip，便于归档与单独交付 */
    const FORMAT_ZIP = 'zip';

    const FORMATS = [self::FORMAT_CSV, self::FORMAT_XLSX, self::FORMAT_ZIP];

    /**
     * 打包内索引文件名，列出每个子文件对应什么
     */
    const INDEX_NAME = '索引.csv';

    /**
     * 收口外部传入的格式，非白名单一律回落CSV
     *
     * zip/xlsx 都依赖 zip 扩展，缺失时同样回落CSV——导出功能不该因环境差异不可用。
     * @param mixed $format
     * @return string
     */
    public static function normalizeFormat($format): string
    {
        $format = is_string($format) ? $format : '';
        if (!in_array($format, self::FORMATS, true)) {
            return self::FORMAT_CSV;
        }
        if ($format !== self::FORMAT_CSV && !XlsxWriter::isSupported()) {
            return self::FORMAT_CSV;
        }
        return $format;
    }

    /**
     * 把多份表格打成一个zip，返回相对URL路径
     *
     * 用生成器逐个接收，写一个释放一个：分包导出的内存占用只与单份表格有关，
     * 因此上限可以远高于合成一张大表的做法。
     * @param string $prefix
     * @param \Generator $files 逐个产出 ['name'=>文件名(不含扩展), 'rows'=>二维数组, 'index'=>索引行]
     * @param array $indexHeader 索引文件的表头
     * @param string $sheetName
     * @return string
     */
    public static function writeBundle(string $prefix, \Generator $files, array $indexHeader, string $sheetName = 'Sheet1'): string
    {
        if (!XlsxWriter::isSupported()) {
            throw new AdminException('当前环境缺少zip扩展，无法打包导出');
        }
        $fileName = self::fileName($prefix, 'zip');
        $zip = new \ZipArchive();
        if (true !== $zip->open(self::dir() . $fileName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            throw new AdminException('创建导出压缩包失败');
        }
        $index = [$indexHeader];
        $used = [];
        foreach ($files as $file) {
            $name = self::uniqueName((string)($file['name'] ?? ''), $used);
            $zip->addFromString($name, self::xlsxBytes((array)($file['rows'] ?? []), $sheetName));
            $index[] = array_merge((array)($file['index'] ?? []), [$name]);
        }
        if (count($index) <= 1) {
            $zip->close();
            @unlink(self::dir() . $fileName);
            throw new AdminException('没有可导出的内容');
        }
        //索引放最后写，此时才知道每个子文件的最终名字
        $zip->addFromString(self::INDEX_NAME, self::csvContent($index));
        if (!$zip->close()) {
            throw new AdminException('导出压缩包写入失败');
        }
        return '/' . self::DIR . $fileName;
    }

    /**
     * 生成xlsx的字节内容
     *
     * ZipArchive 不能嵌套写，故先在内存里拼出xlsx再作为字符串塞进外层包。
     * @param array $rows
     * @param string $sheetName
     * @return string
     */
    protected static function xlsxBytes(array $rows, string $sheetName): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($tmp === false || !XlsxWriter::write($tmp, $rows, $sheetName)) {
            @unlink((string)$tmp);
            throw new AdminException('生成导出文件失败');
        }
        $bytes = (string)file_get_contents($tmp);
        @unlink($tmp);
        return $bytes;
    }

    /**
     * 清洗并去重包内文件名
     *
     * 名字来自访客昵称等用户输入，可能含路径分隔符、Windows保留字符或超长，
     * 直接用会写坏压缩包甚至写到包外；重名则加序号区分。
     * @param string $name
     * @param array $used 出参：已用名字
     * @return string
     */
    protected static function uniqueName(string $name, array &$used): string
    {
        $name = str_replace(["\r", "\n", "\t"], ' ', $name);
        //昵称可能不是合法UTF-8，此时带u修饰的替换会返回null，故逐步兜底为空串
        $name = (string)preg_replace('#[/\\\\:*?"<>|]#u', '_', $name);
        $name = trim((string)preg_replace('/\s+/u', ' ', $name));
        //按字符截断而非字节，避免把多字节字符切成乱码
        $name = mb_substr($name === '' ? '未命名' : $name, 0, 60);
        $candidate = $name . '.xlsx';
        for ($i = 2; isset($used[$candidate]); $i++) {
            $candidate = $name . '(' . $i . ').xlsx';
        }
        $used[$candidate] = true;
        return $candidate;
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
        if (false === file_put_contents(self::dir() . $fileName, self::csvContent($rows))) {
            throw new AdminException('导出文件写入失败');
        }
        return '/' . self::DIR . $fileName;
    }

    /**
     * 二维数组转CSV文本，带BOM便于Excel识别
     * @param array $rows
     * @return string
     */
    protected static function csvContent(array $rows): string
    {
        $lines = array_map(function ($row) {
            return implode(',', array_map(function ($cell) {
                return '"' . str_replace('"', '""', (string)$cell) . '"';
            }, $row));
        }, $rows);
        return "\xEF\xBB\xBF" . implode("\r\n", $lines);
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
