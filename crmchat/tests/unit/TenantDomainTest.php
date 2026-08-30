<?php

namespace tests\unit;

use app\services\TenantServices;
use PHPUnit\Framework\TestCase;

/**
 * 租户独立域名的规整与校验
 *
 * 域名是访客入口的寻址依据，脏数据会导致反查不到归属租户
 */
class TenantDomainTest extends TestCase
{
    public function testStripsProtocolAndPath()
    {
        $this->assertSame('kefu.example.com', TenantServices::normalizeDomain('https://kefu.example.com/chat'));
        $this->assertSame('kefu.example.com', TenantServices::normalizeDomain('http://kefu.example.com'));
    }

    public function testLowercasesAndTrims()
    {
        $this->assertSame('kefu.example.com', TenantServices::normalizeDomain('  KeFu.Example.COM  '));
    }

    public function testEmptyMeansUnbind()
    {
        $this->assertSame('', TenantServices::normalizeDomain('   '));
    }

    public function testAcceptsMultiLevelDomain()
    {
        $this->assertTrue(TenantServices::isValidDomain('kefu.example.com'));
        $this->assertTrue(TenantServices::isValidDomain('a.b.c.example.com.cn'));
        $this->assertTrue(TenantServices::isValidDomain('my-shop.example.com'));
    }

    public function testRejectsBareHostWithoutDot()
    {
        //单段主机名无法在公网解析，也无法与平台自有域名区分
        $this->assertFalse(TenantServices::isValidDomain('localhost'));
    }

    public function testRejectsIllegalCharacters()
    {
        $this->assertFalse(TenantServices::isValidDomain('kefu_example.com'));
        $this->assertFalse(TenantServices::isValidDomain('kefu example.com'));
        $this->assertFalse(TenantServices::isValidDomain('中文.com'));
    }

    public function testRejectsLeadingOrTrailingHyphen()
    {
        $this->assertFalse(TenantServices::isValidDomain('-kefu.example.com'));
        $this->assertFalse(TenantServices::isValidDomain('kefu-.example.com'));
    }

    public function testRejectsOverlongDomain()
    {
        $this->assertFalse(TenantServices::isValidDomain(str_repeat('a', 95) . '.example.com'));
    }

    public function testNormalizeThenValidateWorksTogether()
    {
        $domain = TenantServices::normalizeDomain('HTTPS://KeFu.Example.com/path?a=1');
        $this->assertSame('kefu.example.com', $domain);
        $this->assertTrue(TenantServices::isValidDomain($domain));
    }
}
