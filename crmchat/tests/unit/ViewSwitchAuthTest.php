<?php

namespace tests\unit;

use app\models\system\admin\SystemAdmin;
use app\services\system\admin\SystemRoleServices;
use PHPUnit\Framework\TestCase;

/**
 * 切换租户视角权限点匹配测试
 *
 * 边界：大小写/空白不敏感（与verifiAuth规则一致）、方法不匹配拒绝、空权限清单拒绝。
 */
class ViewSwitchAuthTest extends TestCase
{
    public function testMatchesGrantedPermission()
    {
        $auth = [
            ['api_url' => 'api/admin/setting/tenant/orders', 'methods' => 'GET'],
            ['api_url' => SystemAdmin::VIEW_SWITCH_AUTH, 'methods' => 'GET'],
        ];
        $this->assertTrue(SystemRoleServices::matchApiAuth($auth, SystemAdmin::VIEW_SWITCH_AUTH, 'GET'));
    }

    public function testCaseAndWhitespaceInsensitive()
    {
        $auth = [['api_url' => ' API/Admin/Setting/Tenant/View_Switch ', 'methods' => 'get ']];
        $this->assertTrue(SystemRoleServices::matchApiAuth($auth, SystemAdmin::VIEW_SWITCH_AUTH, 'GET'));
    }

    public function testMethodMismatchRejected()
    {
        $auth = [['api_url' => SystemAdmin::VIEW_SWITCH_AUTH, 'methods' => 'POST']];
        $this->assertFalse(SystemRoleServices::matchApiAuth($auth, SystemAdmin::VIEW_SWITCH_AUTH, 'GET'));
    }

    public function testEmptyAuthRejected()
    {
        $this->assertFalse(SystemRoleServices::matchApiAuth([], SystemAdmin::VIEW_SWITCH_AUTH, 'GET'));
    }

    public function testUngrantedUrlRejected()
    {
        $auth = [['api_url' => 'api/admin/setting/tenant/orders', 'methods' => 'GET']];
        $this->assertFalse(SystemRoleServices::matchApiAuth($auth, SystemAdmin::VIEW_SWITCH_AUTH, 'GET'));
    }
}
