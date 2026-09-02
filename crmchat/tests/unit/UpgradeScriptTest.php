<?php

namespace tests\unit;

use crmeb\command\Upgrade;
use PHPUnit\Framework\TestCase;

/**
 * 增量升级脚本测试
 *
 * 切分错一条语句，升级就会在半途失败并留下改了一半的库，
 * 所以注释、字符串里的分号、PREPARE 这类必须独立下发的语句都要覆盖。
 */
class UpgradeScriptTest extends TestCase
{
    public function testSplitsOnSemicolon()
    {
        $sql = "CREATE TABLE `eb_a` (`id` int);\nCREATE TABLE `eb_b` (`id` int);";
        $this->assertCount(2, Upgrade::split($sql));
    }

    /**
     * 注释里出现分号不能把语句切断
     */
    public function testCommentsAreStripped()
    {
        $sql = "-- 说明；这里有分号\nSELECT 1;\n-- 尾注释\n";
        $statements = Upgrade::split($sql);
        $this->assertCount(1, $statements);
        $this->assertSame('SELECT 1', $statements[0]);
    }

    /**
     * COMMENT 文本里的分号属于字符串内容
     */
    public function testSemicolonInsideStringIsNotASplitPoint()
    {
        $sql = "ALTER TABLE `eb_a` ADD `b` int COMMENT '取值1;2;3';";
        $statements = Upgrade::split($sql);
        $this->assertCount(1, $statements);
        $this->assertStringContainsString('1;2;3', $statements[0]);
    }

    /**
     * 加列的幂等写法里嵌套了转义引号，切错会拆散 PREPARE
     */
    public function testEscapedQuotesKeepStringBoundaries()
    {
        $sql = "SET @s := IF(@c = 0, 'ALTER TABLE `eb_a` ADD `b` int DEFAULT '''' COMMENT ''说明''', 'DO 0');\nPREPARE st FROM @s;\nEXECUTE st;";
        $statements = Upgrade::split($sql);
        $this->assertCount(3, $statements);
        $this->assertStringStartsWith('SET @s', $statements[0]);
        $this->assertSame('PREPARE st FROM @s', $statements[1]);
        $this->assertSame('EXECUTE st', $statements[2]);
    }

    /**
     * 反引号里的分号同样是标识符内容
     */
    public function testBacktickIdentifierIsOpaque()
    {
        $sql = "SELECT `a;b` FROM `eb_t`;";
        $this->assertCount(1, Upgrade::split($sql));
    }

    public function testTrailingSemicolonDoesNotYieldEmptyStatement()
    {
        $this->assertCount(1, Upgrade::split("SELECT 1;\n\n   \n"));
        $this->assertSame([], Upgrade::split("-- 只有注释\n\n"));
    }

    /**
     * 版本号即执行顺序，文件名格式错了应被忽略而不是乱序执行
     */
    public function testFilePatternAcceptsOnlyVersionedNames()
    {
        $accepted = ['V20260902_01__platform_crm.sql', 'V20261231_99__a-b_c.sql'];
        foreach ($accepted as $name) {
            $this->assertSame(1, preg_match(Upgrade::FILE_PATTERN, $name), $name);
        }
        $rejected = ['V20260902__no_seq.sql', '20260902_01__no_prefix.sql', 'V2026090_01__short.sql',
            'V20260902_01__中文.sql', 'V20260902_01__missing_ext', 'README.md'];
        foreach ($rejected as $name) {
            $this->assertSame(0, preg_match(Upgrade::FILE_PATTERN, $name), $name);
        }
    }

    public function testVersionSortIsChronological()
    {
        $names = ['V20261001_01__c.sql', 'V20260902_02__b.sql', 'V20260902_01__a.sql'];
        $versions = array_map(function ($name) {
            preg_match(Upgrade::FILE_PATTERN, $name, $matches);
            return $matches[1];
        }, $names);
        usort($versions, 'strcmp');
        $this->assertSame(['20260902_01', '20260902_02', '20261001_01'], $versions);
    }
}
