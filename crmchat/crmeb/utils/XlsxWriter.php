<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\utils;

/**
 * 最小化 xlsx 生成器
 *
 * 项目 vendor 里的 PHPExcel 既未进 composer 依赖也未被自动加载，且已停止维护；
 * 引入 PhpSpreadsheet 又要把体积不小的 vendor 一并提交（构建期不执行 composer install）。
 * 导出的是一张扁平表格，用不上完整库，故直接按 OOXML 规范拼出必需的几个 XML 打成 zip。
 */
class XlsxWriter
{
    /** 单元格按文本写入，避免长单号被识别成科学计数法 */
    const TYPE_TEXT = 'text';

    /** 单元格按数值写入，可在Excel中直接求和 */
    const TYPE_NUMBER = 'number';

    /** 超过该长度的纯数字仍按文本处理：Excel只保证15位有效数字，再长会被截断 */
    const NUMERIC_SAFE_LENGTH = 15;

    /**
     * 生成xlsx并写入指定路径
     * @param string $filePath 目标文件绝对路径
     * @param array $rows 二维数组，首行为表头
     * @param string $sheetName
     * @return bool
     */
    public static function write(string $filePath, array $rows, string $sheetName = 'Sheet1'): bool
    {
        if (!self::isSupported()) {
            return false;
        }
        $zip = new \ZipArchive();
        if (true !== $zip->open($filePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            return false;
        }
        foreach (self::buildParts($rows, $sheetName) as $name => $content) {
            $zip->addFromString($name, $content);
        }
        return $zip->close();
    }

    /**
     * 运行环境是否具备生成条件
     * @return bool
     */
    public static function isSupported(): bool
    {
        return class_exists('\ZipArchive');
    }

    /**
     * xlsx包内的全部文件
     * @param array $rows
     * @param string $sheetName
     * @return array
     */
    public static function buildParts(array $rows, string $sheetName = 'Sheet1'): array
    {
        return [
            '[Content_Types].xml' => self::contentTypes(),
            '_rels/.rels' => self::rootRels(),
            'xl/workbook.xml' => self::workbook($sheetName),
            'xl/_rels/workbook.xml.rels' => self::workbookRels(),
            'xl/styles.xml' => self::styles(),
            'xl/worksheets/sheet1.xml' => self::sheet($rows),
        ];
    }

    /**
     * 列序号转Excel列名，0=>A、25=>Z、26=>AA
     * @param int $index
     * @return string
     */
    public static function columnName(int $index): string
    {
        $name = '';
        for ($i = $index; $i >= 0; $i = intdiv($i, 26) - 1) {
            $name = chr(65 + $i % 26) . $name;
        }
        return $name;
    }

    /**
     * 判定单元格应写成数值还是文本
     * @param mixed $value
     * @return string
     */
    public static function cellType($value): string
    {
        if (is_int($value) || is_float($value)) {
            return self::TYPE_NUMBER;
        }
        $text = (string)$value;
        if ($text === '' || !is_numeric($text)) {
            return self::TYPE_TEXT;
        }
        //前导零是有意义的编号，长数字超出Excel精度，两者都必须保持文本
        if ($text[0] === '0' && strpos($text, '.') !== 1) {
            return self::TYPE_TEXT;
        }
        return strlen($text) > self::NUMERIC_SAFE_LENGTH ? self::TYPE_TEXT : self::TYPE_NUMBER;
    }

    /**
     * @param array $rows
     * @return string
     */
    protected static function sheet(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . self::columnWidths($rows) . '<sheetData>';
        foreach (array_values($rows) as $rowIndex => $row) {
            $rowNo = $rowIndex + 1;
            $xml .= '<row r="' . $rowNo . '">';
            foreach (array_values($row) as $colIndex => $value) {
                $xml .= self::cell(self::columnName($colIndex) . $rowNo, $value, $rowIndex === 0);
            }
            $xml .= '</row>';
        }
        return $xml . '</sheetData></worksheet>';
    }

    /**
     * @param string $ref
     * @param mixed $value
     * @param bool $isHeader
     * @return string
     */
    protected static function cell(string $ref, $value, bool $isHeader): string
    {
        $style = $isHeader ? ' s="1"' : '';
        if (!$isHeader && self::cellType($value) === self::TYPE_NUMBER) {
            return '<c r="' . $ref . '"' . $style . '><v>' . $value . '</v></c>';
        }
        return '<c r="' . $ref . '"' . $style . ' t="inlineStr"><is><t xml:space="preserve">'
            . self::escape((string)$value) . '</t></is></c>';
    }

    /**
     * 按内容估算列宽，避免打开后满屏 ####
     * @param array $rows
     * @return string
     */
    protected static function columnWidths(array $rows): string
    {
        $widths = [];
        foreach ($rows as $row) {
            foreach (array_values($row) as $i => $value) {
                //中文按两个字符宽度计
                $text = (string)$value;
                $len = mb_strlen($text) + (strlen($text) - mb_strlen($text)) / 2;
                $widths[$i] = max($widths[$i] ?? 8, min((int)ceil($len) + 4, 50));
            }
        }
        if (!$widths) {
            return '';
        }
        $cols = '';
        foreach ($widths as $i => $w) {
            $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        }
        return '<cols>' . $cols . '</cols>';
    }

    /**
     * @param string $text
     * @return string
     */
    protected static function escape(string $text): string
    {
        //XML 1.0 不接受这些控制字符，未过滤会导致Excel报文件损坏
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text);
        return htmlspecialchars((string)$text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    protected static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    protected static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    protected static function workbook(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::escape($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    protected static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    /**
     * 仅两种样式：0默认、1表头加粗带底色
     * @return string
     */
    protected static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFEFF3F9"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            . '</styleSheet>';
    }
}
