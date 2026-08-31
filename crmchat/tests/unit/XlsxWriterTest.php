<?php

namespace tests\unit;

use crmeb\utils\XlsxWriter;
use PHPUnit\Framework\TestCase;

/**
 * xlsx导出测试
 *
 * 重点是单元格类型判定：对账单号是长数字串，若按数值写入会被Excel转成科学计数法、
 * 超过15位还会丢精度，导出的单号就对不上账；而金额必须是数值否则无法求和。
 */
class XlsxWriterTest extends TestCase
{
    public function testColumnNameCoversMultiLetter()
    {
        $this->assertSame('A', XlsxWriter::columnName(0));
        $this->assertSame('Z', XlsxWriter::columnName(25));
        $this->assertSame('AA', XlsxWriter::columnName(26));
        $this->assertSame('AZ', XlsxWriter::columnName(51));
        $this->assertSame('BA', XlsxWriter::columnName(52));
    }

    public function testAmountsAreNumeric()
    {
        foreach ([99, 99.5, '128.00', '0.5', -12] as $value) {
            $this->assertSame(XlsxWriter::TYPE_NUMBER, XlsxWriter::cellType($value), var_export($value, true));
        }
    }

    /**
     * 长单号按数值写入会被Excel截断，必须保持文本
     */
    public function testLongDigitStringStaysText()
    {
        $this->assertSame(XlsxWriter::TYPE_TEXT, XlsxWriter::cellType('20260831000123456789'));
    }

    /**
     * 前导零是编号的一部分，按数值写入会被吃掉
     */
    public function testLeadingZeroStaysText()
    {
        $this->assertSame(XlsxWriter::TYPE_TEXT, XlsxWriter::cellType('0071'));
    }

    public function testNonNumericStaysText()
    {
        foreach (['', '永久', '2026-08-31', '已生效', '12,800'] as $value) {
            $this->assertSame(XlsxWriter::TYPE_TEXT, XlsxWriter::cellType($value), var_export($value, true));
        }
    }

    /**
     * 特殊字符未转义会让Excel判定文件损坏
     */
    public function testSheetXmlStaysWellFormedWithSpecialChars()
    {
        $parts = XlsxWriter::buildParts([
            ['标题'],
            ['含"引号"与<标签>与&符号'],
            ["含\x07控制字符"],
        ]);
        $sheet = $parts['xl/worksheets/sheet1.xml'];
        $this->assertNotFalse(simplexml_load_string($sheet), 'sheet1.xml 必须是合法XML');
        $this->assertStringNotContainsString("\x07", $sheet);
    }

    public function testPackageContainsRequiredParts()
    {
        $parts = XlsxWriter::buildParts([['A'], ['1']]);
        foreach ([
            '[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml',
            'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/worksheets/sheet1.xml',
        ] as $name) {
            $this->assertArrayHasKey($name, $parts);
            $this->assertNotFalse(simplexml_load_string($parts[$name]), $name . ' 必须是合法XML');
        }
    }

    public function testSheetNameIsEscaped()
    {
        $parts = XlsxWriter::buildParts([['A']], '订单&<报表>');
        $this->assertNotFalse(simplexml_load_string($parts['xl/workbook.xml']));
    }
}
