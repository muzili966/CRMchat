<?php

namespace tests\unit;

use app\services\chat\VisitorAccountServices;
use PHPUnit\Framework\TestCase;

/**
 * 访客账号安全测试
 *
 * 续接令牌是账号访客换设备接续会话的唯一凭据，签名一旦能被伪造或改写，
 * “猜到 uid 即可读历史”的老洞就会在账号访客身上重新打开。
 */
class VisitorAccountTest extends TestCase
{
    const SECRET = 'app-secret-abcdef0123456789';

    /**
     * 正常签发的令牌能验回原文
     */
    public function testSignedTokenRoundtrips()
    {
        $payload = '821|2|1788400000';
        $token = VisitorAccountServices::sign($payload, self::SECRET);
        $this->assertSame($payload, VisitorAccountServices::unsign($token, self::SECRET));
    }

    /**
     * 改一个字节即验签失败
     */
    public function testTamperedTokenIsRejected()
    {
        $token = VisitorAccountServices::sign('821|2|1788400000', self::SECRET);
        $this->assertNull(VisitorAccountServices::unsign($token . 'x', self::SECRET));
        //篡改签名段
        [$body, $sig] = explode('.', $token);
        $this->assertNull(VisitorAccountServices::unsign($body . '.' . strrev($sig), self::SECRET));
    }

    /**
     * 换个密钥验不过——令牌绑定在应用的 app_secret 上，跨应用不通用
     */
    public function testTokenIsBoundToSecret()
    {
        $token = VisitorAccountServices::sign('821|2|1788400000', self::SECRET);
        $this->assertNull(VisitorAccountServices::unsign($token, 'another-secret'));
    }

    /**
     * 空密钥不能签出可用令牌，否则未配 app_secret 的应用会放行任意令牌
     */
    public function testEmptySecretNeverVerifies()
    {
        $token = VisitorAccountServices::sign('821|2|1788400000', '');
        $this->assertNull(VisitorAccountServices::unsign($token, ''));
    }

    /**
     * 畸形令牌不抛异常，安全地判为无效
     */
    public function testMalformedTokenReturnsNull()
    {
        foreach (['', 'nodot', 'a.b.c', '.', 'x.'] as $bad) {
            $this->assertNull(VisitorAccountServices::unsign($bad, self::SECRET), $bad);
        }
    }

    /**
     * payload 里的分隔符不能破坏结构：uid|version|issuedAt 三段应稳定取出
     */
    public function testPayloadStructureSurvivesRoundtrip()
    {
        $token = VisitorAccountServices::sign('821|3|1788400000', self::SECRET);
        $payload = VisitorAccountServices::unsign($token, self::SECRET);
        [$userId, $version, $issuedAt] = explode('|', $payload);
        $this->assertSame('821', $userId);
        $this->assertSame('3', $version);
        $this->assertSame('1788400000', $issuedAt);
    }
}
