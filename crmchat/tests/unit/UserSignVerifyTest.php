<?php

namespace tests\unit;

use app\models\Application;
use app\services\ApplicationServices;
use crmeb\exceptions\AuthException;
use PHPUnit\Framework\TestCase;

/**
 * 访客接入签名校验测试
 *
 * 边界：兼容模式与游客不校验、缺参/过期/错签拒绝、大小写不敏感。
 */
class UserSignVerifyTest extends TestCase
{
    protected function appData(int $authMode): array
    {
        return [
            'appid' => '202116257358989495',
            'app_secret' => 'secret-abc',
            'auth_mode' => $authMode,
        ];
    }

    protected function signedUser(int $uid, ?int $timestamp = null): array
    {
        $timestamp = $timestamp ?? time();
        return [
            'uid' => $uid,
            'timestamp' => $timestamp,
            'sign' => md5('202116257358989495' . $uid . $timestamp . 'secret-abc'),
        ];
    }

    public function testCompatModeSkipsVerification()
    {
        ApplicationServices::verifyUserSign($this->appData(Application::AUTH_MODE_COMPAT), ['uid' => 123]);
        $this->assertTrue(true);
    }

    public function testTouristSkipsVerification()
    {
        ApplicationServices::verifyUserSign($this->appData(Application::AUTH_MODE_SIGN), ['uid' => 0]);
        $this->assertTrue(true);
    }

    public function testValidSignaturePasses()
    {
        ApplicationServices::verifyUserSign($this->appData(Application::AUTH_MODE_SIGN), $this->signedUser(123));
        $this->assertTrue(true);
    }

    public function testUppercaseSignaturePasses()
    {
        $user = $this->signedUser(123);
        $user['sign'] = strtoupper($user['sign']);
        ApplicationServices::verifyUserSign($this->appData(Application::AUTH_MODE_SIGN), $user);
        $this->assertTrue(true);
    }

    public function testMissingSignRejected()
    {
        $this->expectException(AuthException::class);
        ApplicationServices::verifyUserSign($this->appData(Application::AUTH_MODE_SIGN), ['uid' => 123]);
    }

    public function testExpiredSignatureRejected()
    {
        $this->expectException(AuthException::class);
        ApplicationServices::verifyUserSign(
            $this->appData(Application::AUTH_MODE_SIGN),
            $this->signedUser(123, time() - Application::SIGN_TTL - 10)
        );
    }

    public function testWrongSignatureRejected()
    {
        $user = $this->signedUser(123);
        $user['sign'] = md5('forged');
        $this->expectException(AuthException::class);
        ApplicationServices::verifyUserSign($this->appData(Application::AUTH_MODE_SIGN), $user);
    }

    public function testSignForOtherUidRejected()
    {
        $user = $this->signedUser(123);
        $user['uid'] = 456;
        $this->expectException(AuthException::class);
        ApplicationServices::verifyUserSign($this->appData(Application::AUTH_MODE_SIGN), $user);
    }
}
