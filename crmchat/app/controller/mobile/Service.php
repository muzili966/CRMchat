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

namespace app\controller\mobile;


use app\Request;
use app\services\chat\ChatServiceServices;
use app\services\other\CacheServices;
use app\services\system\attachment\SystemAttachmentServices;
use crmeb\services\CacheService;
use crmeb\services\tenant\TenantContext;
use crmeb\services\UploadService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * Class Service
 * @package app\controller\mobile
 */
class Service extends AuthController
{
    /**
     * Service constructor.
     * @param ChatServiceServices $services
     */
    public function __construct(ChatServiceServices $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    /**
     * 获取聊天记录
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getRecordList()
    {
        [$idTo, $limit, $toUserId, $cookieUid, $kefuUd] = $this->request->getMore([
            ['idTo', 0],
            ['limit', 10],
            ['toUserId', 0],
            ['cookieUid', 0],
            ['kefu_id', 0]
        ], true);

        $user = $this->request->getMore([
            ['uid', ''],
            ['nickname', ''],
            ['phone', ''],
            ['sex', ''],
            ['avatar', ''],
            ['openid', ''],
            ['type', ''],
            //账号访客的续接令牌，服务端据此放行绑定过的会话
            ['resume_token', ''],
        ]);
        //优先使用指定客服
        if ($kefuUd && $toUserId) {
            $toUserId = 0;
        }
        return app('json')->successful($this->services->getRecord($this->appId, $user, $idTo, $limit, $toUserId, (int)$cookieUid, (int)$kefuUd));
    }

    /**
     * @param $key
     * @return mixed
     */
    public function getCache($key)
    {
        /** @var CacheServices $cache */
        $cache = app()->make(CacheServices::class);
        $value = $cache->getDbCache($this->visitorCacheKey($key), []);
        return $this->success(compact('value'));
    }

    /**
     * 访客侧缓存key
     *
     * 该接口无需登录且key由前端任意指定，若直接透传会读写到平台或其他租户的缓存行；
     * 强制加租户与应用前缀，把可写范围限制在本应用自己的命名空间内
     * @param string $key
     * @return string
     */
    protected function visitorCacheKey(string $key): string
    {
        $key = preg_replace('/[^a-zA-Z0-9_\-]/', '', $key);
        if ($key === '') {
            throw new \think\exception\ValidateException('key格式不正确');
        }
        return 'visitor:' . (int)TenantContext::id() . ':' . $this->request->appId() . ':' . mb_substr($key, 0, 64);
    }

    /**
     * @param $key
     * @return mixed
     */
    public function setCache()
    {
        [$key, $value] = $this->request->postMore([
            ['key', ''],
            ['value', []],
        ]);
        if (!$key) {
            return $this->fail('key必须存在');
        }
        /** @var CacheServices $cache */
        $cache = app()->make(CacheServices::class);
        $cache->setDbCache($this->visitorCacheKey($key), $value, 600);
        return $this->success('ok');
    }


    /**
     * 图片上传
     * @param Request $request
     * @return mixed
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function upload(Request $request, SystemAttachmentServices $services)
    {
        $data = $request->postMore([
            ['filename', 'file'],
        ]);
        if (!$data['filename']) return $this->fail('参数有误');
        if (CacheService::has('start_uploads_' . $request->appId()) && CacheService::get('start_uploads_' . $request->appId()) >= 500) return $this->fail('非法操作');
        $fileHandle = $request->file($data['filename']);
        if ($fileHandle) {
            /** @var \app\services\TenantPlanServices $planServices */
            $planServices = app()->make(\app\services\TenantPlanServices::class);
            $planServices->checkStorage(\crmeb\services\tenant\TenantContext::id(), (int)$fileHandle->getSize());
        }
        $upload = UploadService::init();
        $info = $upload->to(\crmeb\services\tenant\TenantScope::uploadDir('store/comment'))->validate()->move($data['filename']);
        if ($info === false) {
            return $this->fail($upload->getError());
        }
        $res = $upload->getUploadInfo();
        $services->attachmentAdd($res['name'], $res['size'], $res['type'], $res['dir'], $res['thumb_path'], 1, (int)sys_config('upload_type', 1), $res['time'], 2);
        if (CacheService::has('start_uploads_' . $request->appId()))
            $start_uploads = (int)CacheService::get('start_uploads_' . $request->appId());
        else
            $start_uploads = 0;
        $start_uploads++;
        CacheService::set('start_uploads_' . $request->appId(), $start_uploads, 86400);
        $res['dir'] = path_to_url($res['dir']);
        if (strpos($res['dir'], 'http') === false) $res['dir'] = $request->domain() . $res['dir'];
        return $this->success('图片上传成功!', ['name' => $res['name'], 'url' => $res['dir']]);
    }

}
