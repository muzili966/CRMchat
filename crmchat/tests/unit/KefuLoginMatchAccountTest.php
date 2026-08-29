<?php

namespace tests\unit;

use app\dao\chat\ChatServiceDao;
use app\services\kefu\LoginServices;
use PHPUnit\Framework\TestCase;
use think\Collection;
use think\exception\ValidateException;

/**
 * 客服登录账号消歧逻辑测试
 *
 * 不同应用允许同名账号后，matchAccount 必须通过密码比对定位唯一客服，
 * 边界：无此账号、免密多匹配、密码不匹配、密码多匹配。
 */
class KefuLoginMatchAccountTest extends TestCase
{
    const PASSWORD = 'abc123';
    const OTHER_PASSWORD = 'xyz789';

    /**
     * @param array $rows
     * @return LoginServices
     */
    protected function makeServices(array $rows): LoginServices
    {
        $dao = $this->createMock(ChatServiceDao::class);
        $dao->method('getListByAccount')->willReturn(new Collection($rows));
        return new LoginServices($dao);
    }

    /**
     * @param LoginServices $services
     * @param string $account
     * @param string|null $password
     * @return mixed
     */
    protected function invokeMatch(LoginServices $services, string $account, ?string $password)
    {
        $method = new \ReflectionMethod($services, 'matchAccount');
        $method->setAccessible(true);
        return $method->invoke($services, $account, $password);
    }

    /**
     * @param int $id
     * @param string $password
     * @return object
     */
    protected function makeKefuRow(int $id, string $password)
    {
        $row = new \stdClass();
        $row->id = $id;
        $row->password = password_hash($password, PASSWORD_DEFAULT);
        return $row;
    }

    public function testAccountNotFound()
    {
        $this->expectException(ValidateException::class);
        $this->invokeMatch($this->makeServices([]), 'kefu1', self::PASSWORD);
    }

    public function testPasswordMatchesSingleRow()
    {
        $services = $this->makeServices([
            $this->makeKefuRow(1, self::PASSWORD),
            $this->makeKefuRow(2, self::OTHER_PASSWORD),
        ]);
        $matched = $this->invokeMatch($services, 'kefu1', self::PASSWORD);
        $this->assertSame(1, $matched->id);
    }

    public function testWrongPasswordRejected()
    {
        $services = $this->makeServices([$this->makeKefuRow(1, self::PASSWORD)]);
        $this->expectException(ValidateException::class);
        $this->expectExceptionMessage('账号或密码错误');
        $this->invokeMatch($services, 'kefu1', 'wrong-password');
    }

    public function testDuplicatePasswordRejected()
    {
        $services = $this->makeServices([
            $this->makeKefuRow(1, self::PASSWORD),
            $this->makeKefuRow(2, self::PASSWORD),
        ]);
        $this->expectException(ValidateException::class);
        $this->invokeMatch($services, 'kefu1', self::PASSWORD);
    }

    public function testPasswordlessLoginWithSingleRow()
    {
        $services = $this->makeServices([$this->makeKefuRow(1, self::PASSWORD)]);
        $matched = $this->invokeMatch($services, 'kefu1', null);
        $this->assertSame(1, $matched->id);
    }

    public function testPasswordlessLoginWithDuplicatesRejected()
    {
        $services = $this->makeServices([
            $this->makeKefuRow(1, self::PASSWORD),
            $this->makeKefuRow(2, self::OTHER_PASSWORD),
        ]);
        $this->expectException(ValidateException::class);
        $this->invokeMatch($services, 'kefu1', null);
    }
}
