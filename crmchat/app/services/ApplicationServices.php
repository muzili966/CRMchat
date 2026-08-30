<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2020 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace app\services;


use app\dao\ApplicationDao;
use app\services\chat\ChatUserServices;
use crmeb\basic\BaseServices;
use crmeb\exceptions\AdminException;
use crmeb\exceptions\AuthException;
use crmeb\services\CacheService;
use crmeb\services\tenant\TenantContext;
use crmeb\services\FormBuilder;
use crmeb\utils\Arr;
use crmeb\utils\Encrypter;
use FormBuilder\Exception\FormBuilderException;
use Psr\SimpleCache\InvalidArgumentException;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * Class ApplicationServices
 * @package app\services
 */
class ApplicationServices extends BaseServices
{

    /**
     * ApplicationServices constructor.
     * @param ApplicationDao $dao
     */
    public function __construct(ApplicationDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取应用列表数据
     * @param array $where
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getList(array $where)
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getDataList($where, ['*'], 'id', $page, $limit);
        $count = $this->dao->count();
        return compact('list', 'count');
    }

    /**
     * 获取表单规则
     * @param array $data
     * @return array
     */
    public function getFormRule(array $data = [])
    {
        /** @var TenantServices $tenantServices */
        $tenantServices = app()->make(TenantServices::class);
        return [
            FormBuilder::select('tenant_id', '所属租户', (int)($data['tenant_id'] ?? 0))
                ->setOptions(FormBuilder::setOptions($tenantServices->getTenantOptions()))->required(),
            FormBuilder::frameImage('icon', '应用图标', $this->url('admin/widget.images/index', ['fodder' => 'icon'], true), $data['icon'] ?? '')
                ->icon('ios-image')->width('950px')->height('420px')->col(13)->required(),
            FormBuilder::input('name', '应用名称', $data['name'] ?? '')->required(),
            FormBuilder::textarea('introduce', '应用简介', $data['introduce'] ?? ''),
            FormBuilder::radio('auth_mode', '接入模式', (int)($data['auth_mode'] ?? \app\models\Application::AUTH_MODE_COMPAT))
                ->options([
                    ['value' => \app\models\Application::AUTH_MODE_COMPAT, 'label' => '标准接入(默认，自带客服窗口与嵌入代码均可直接使用)'],
                    ['value' => \app\models\Application::AUTH_MODE_SIGN, 'label' => '签名接入(更安全，需贵司服务端下发签名，自带窗口不适用)'],
                ]),
        ];
    }

    /**
     * 获取创建表单
     * @return array
     * @throws FormBuilderException
     */
    public function getCreateForm()
    {
        return create_form('添加应用', $this->getFormRule(), $this->url('admin/app'), 'post');
    }

    /**
     * 获取修改表单
     * @param int $id
     * @return array
     * @throws FormBuilderException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getUpdateForm(int $id)
    {
        $appInfo = $this->dao->get($id);
        if (!$appInfo) {
            throw new AdminException('修改的应用不存在');
        }
        return create_form('修改应用', $this->getFormRule($appInfo->toArray()), $this->url('admin/app', ['id' => $id]), 'put');
    }

    /**
     * 获取
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getOptions()
    {
        return $this->dao->getDataList(['is_delete' => 0], ['name as label', 'appid as value'], 'id');
    }

    /**
     * 创建或者更新用户
     * @param string $appid
     * @param array $userData
     * @return array|mixed
     * @throws InvalidArgumentException
     */
    /**
     * 签名接入模式下校验客户身份签名，防止知道应用token的人冒充任意uid
     * sign = md5(appid . uid . timestamp . app_secret)，app_secret仅存在于企业服务端
     * @param array $appData 应用数据（须含appid/app_secret/auth_mode）
     * @param array $userData 接入数据（uid/sign/timestamp）
     * @return void
     */
    public static function verifyUserSign(array $appData, array $userData)
    {
        if ((int)($appData['auth_mode'] ?? \app\models\Application::AUTH_MODE_COMPAT) !== \app\models\Application::AUTH_MODE_SIGN) {
            return;
        }
        $uid = $userData['uid'] ?? 0;
        //游客由服务端生成随机uid，无冒充面，无需签名
        if (!$uid) {
            return;
        }
        $sign = strtolower((string)($userData['sign'] ?? ''));
        $timestamp = (int)($userData['timestamp'] ?? 0);
        if (!$sign || !$timestamp) {
            throw new AuthException('签名模式下携带uid接入必须提供sign与timestamp');
        }
        if (abs(time() - $timestamp) > \app\models\Application::SIGN_TTL) {
            throw new AuthException('签名已过期');
        }
        $expect = md5($appData['appid'] . $uid . $timestamp . $appData['app_secret']);
        if (!hash_equals($expect, $sign)) {
            throw new AuthException('用户签名校验失败');
        }
    }

    public function createUser(string $appid, array $userData = [], bool $verifySign = true)
    {
        //验签须在缓存命中之前，否则首个合法接入落缓存后即可被无签名冒充
        if ($verifySign) {
            $appData = TenantContext::withoutTenant(function () use ($appid) {
                return $this->dao->get(['appid' => $appid, 'is_delete' => 0]);
            });
            if (!$appData) {
                throw new AuthException('应用不存在');
            }
            self::verifyUserSign($appData->toArray(), $userData);
        }
        $uid = $userData['uid'] ?? 0;
        $nickname = $userData['nickname'] ?? '';
        $avatar = $userData['avatar'] ?? '';
        $phone = $userData['phone'] ?? '';
        $openid = $userData['openid'] ?? '';
        $type = $userData['type'] ?? 0;

        $redis = CacheService::redisHandler();
        if ($userInfo = $redis->get($appid . '-' . $uid)) {
            return $userInfo;
        }

        /** @var ChatUserServices $userServices */
        $userServices = app()->make(ChatUserServices::class);
        if ($uid && ($userInfo = $userServices->get(['uid' => $uid, 'appid' => $appid]))) {
            $userInfo->nickname = $nickname;
            $userInfo->avatar = $avatar;
            $userInfo->phone = $phone;
            $userInfo->openid = $openid;
            $userInfo->type = $type;
            $userInfo->save();

            $redis->set($appid . '-' . $uid, $userInfo->toArray(), 86400);

        } else {
            $isTourist = 0;
            //游客模式
            if ((int)$uid === 0) {
                $isTourist = 1;
                mt_srand();
                $rand1 = mt_rand(10, 99);
                mt_srand();
                $rand2 = mt_rand(1000, 9999);
                $uid = date('Y') . $rand1 . $rand2;
            }
            if (!$nickname) {
                $nickname = '游客' . $uid;
            }
            if (!$avatar) {
                $touristAvatar = sys_config('tourist_avatar');
                $avatar = Arr::getArrayRandKey(is_array($touristAvatar) ? $touristAvatar : []);
                $avatar = link_url($avatar);
            }
            $userInfo = $userServices->save([
                'uid' => $uid,
                'nickname' => $nickname,
                'avatar' => $avatar,
                'phone' => $phone,
                'appid' => $appid,
                'openid' => $openid,
                'type' => $type,
                'is_tourist' => $isTourist,
            ]);
            if (!$userInfo) {
                throw new AuthException('创建用户失败');
            }
            $redis->set($appid . '-' . $uid, $userInfo->toArray(), 86400);
        }
        return $userInfo->toArray();
    }

    /**
     * @param string $token
     * @param array $other
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws InvalidArgumentException
     * @throws AuthException
     */
    public function parseToken(string $token, array $other = [])
    {
        if (strlen($token) === 32) {
            //token寻址发生在租户上下文建立之前，需逃逸执行
            $token = TenantContext::withoutTenant(function () use ($token) {
                return $this->dao->value(['token_md5' => $token], 'token');
            });
        }
        /** @var Encrypter $encrypter */
        $encrypter = app()->make(Encrypter::class);

        try {
            $appInfo = $encrypter->decrypt($token);
        } catch (\Throwable $e) {
            throw new AuthException('无效TOKEN');
        }

        $appInfo = json_decode($appInfo, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new AuthException('验签失败');
        }

        if (!isset($appInfo['appid'])) {
            throw new AuthException('缺少应用ID');
        }

        $appData = TenantContext::withoutTenant(function () use ($appInfo) {
            return $this->dao->get(['appid' => $appInfo['appid'], 'is_delete' => 0]);
        });

        if (!$appData) {
            throw new AuthException('应用不存在');
        }

        $appSecret = md5($appData['appid'] . $appData['timestamp'] . $appData['rand']);
        if ($appSecret !== $appInfo['app_secret']) {
            throw new AuthException('错误的app_secret值');
        }
        //应用所属租户禁用/到期时拒绝接入
        /** @var TenantServices $tenantServices */
        $tenantServices = app()->make(TenantServices::class);
        $tenantServices->checkUsable((int)($appData['tenant_id'] ?? 0));
        $appInfo['tenant_id'] = (int)($appData['tenant_id'] ?? 0);
        //token解析即完成租户定位，为当前协程建立租户上下文（mobile请求与websocket连接共用此入口）
        TenantContext::set($appInfo['tenant_id']);
        if ($other) {
            return ['user' => $this->createUser($appData['appid'], $other), 'appInfo' => $appInfo];
        } else {
            return ['appInfo' => $appInfo];
        }
    }
}
