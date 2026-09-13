<?php

namespace tests\unit;

use PHPUnit\Framework\TestCase;

/**
 * 支付凭据写入 .env 的引号约束
 *
 * 框架按 ini 普通模式解析 .env（parse_ini_file 默认模式），支付宝、微信的密钥常以
 * 去掉头尾的一行 base64 配置，末尾带 =。不加双引号时整份 .env 解析失败，
 * 数据库、Redis 配置一起丢，应用直接起不来。这类改动在代码评审里很难一眼看出来，
 * 所以用测试钉住。
 */
class PaymentEnvQuotingTest extends TestCase
{
    /**
     * 生成 .env 的脚本
     */
    const ENTRYPOINT = __DIR__ . '/../../deploy/docker/entrypoint.sh';

    /**
     * 容器入口写出的每个支付凭据都必须带双引号
     */
    public function testEntrypointQuotesEveryPaymentValue()
    {
        preg_match_all('/^(PAY_[A-Z0-9_]+) = (.*)$/m', (string)file_get_contents(self::ENTRYPOINT), $matches, PREG_SET_ORDER);
        $this->assertNotEmpty($matches, '入口脚本里没找到支付凭据，可能被误删');
        foreach ($matches as [, $key, $value]) {
            $this->assertMatchesRegularExpression('/^".*"$/', trim($value), "{$key} 必须用双引号包住");
        }
    }

    /**
     * 反面佐证：不加引号的 base64 确实会让整份配置解析失败，
     * 若哪天框架换了解析方式、这条不再成立，上面的约束才可以放宽
     */
    public function testUnquotedBase64BreaksWholeIni()
    {
        $unquoted = "PAY_ALIPAY_PUBLIC_KEY = MIIBIjANBgkq+/abc==\n[DATABASE]\nHOSTNAME = 127.0.0.1\n";
        $quoted = "PAY_ALIPAY_PUBLIC_KEY = \"MIIBIjANBgkq+/abc==\"\n[DATABASE]\nHOSTNAME = 127.0.0.1\n";

        $this->assertFalse(@parse_ini_string($unquoted, true));
        $parsed = parse_ini_string($quoted, true);
        $this->assertSame('MIIBIjANBgkq+/abc==', $parsed['PAY_ALIPAY_PUBLIC_KEY']);
        $this->assertSame('127.0.0.1', $parsed['DATABASE']['HOSTNAME']);
    }
}
