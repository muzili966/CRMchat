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

namespace app\controller\kefu;


use app\Request;
use app\services\chat\ChatAutoReplyServices;
use app\services\chat\ChatServiceSpeechcraftServices;
use app\services\kefu\KefuServices;
use app\services\message\service\StoreServiceServices;
use app\services\other\CategoryServices;
use app\validate\kefu\SpeechcraftValidate;
use crmeb\services\CacheService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * Class Service
 * @package app\controller\kefu
 */
class Service extends AuthController
{

    /**
     * Service constructor.
     * @param KefuServices $services
     */
    public function __construct(KefuServices $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    /**
     * 转接客服列表
     * @param Request $request
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getServiceList(Request $request, $userId = 0)
    {
        $where = $request->getMore([
            ['nickname', ''],
        ]);
        return $this->success($this->services->getServiceList($where, [$this->kefuInfo['user_id'], $userId]));
    }

    /**
     * 话术列表
     * @param Request $request
     * @param ChatServiceSpeechcraftServices $services
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getSpeechcraftList(Request $request, ChatServiceSpeechcraftServices $services)
    {
        $where = $request->getMore([
            ['title', ''],
            ['cate_id', ''],
            ['type', 0]
        ]);
        if ($where['type']) {
            $where['kefu_id'] = $this->kefuId;
        } else {
            $where['kefu_id'] = 0;
        }
        $data = $services->getSpeechcraftList($where);
        return $this->success($data['list']);
    }

    /**
     * 添加分类
     * @param Request $request
     * @param CategoryServices $services
     * @return mixed
     */
    public function saveCate(Request $request, CategoryServices $services)
    {
        $data = $request->postMore([
            ['name', ''],
            [['sort', 'd'], 0],
        ]);

        if (!$data['name']) {
            return $this->fail('分类名称不能为空');
        }
        $data['add_time'] = time();
        $data['owner_id'] = $this->kefuId;
        $data['type'] = 1;

        $services->save($data);
        return $this->success('添加成功');
    }

    /**
     * 修改分类
     * @param Request $request
     * @param CategoryServices $services
     * @param $id
     * @return mixed
     */
    public function editCate(Request $request, CategoryServices $services, $id)
    {
        $data = $request->postMore([
            ['name', ''],
            [['sort', 'd'], 0],
        ]);

        if (!$data['name']) {
            return $this->fail('分类不能为空');
        }
        if (mb_strlen($data['name']) > 10) {
            return $this->fail('分类字数长度不能超过10个字');
        }

        $cateInfo = $services->get($id);
        if (!$cateInfo) {
            return $this->fail('分类没有查到无法删除');
        }
        if ($cateInfo->owner_id != $this->kefuId || $cateInfo->type != 1) {
            return $this->fail('只能修改自己创建的分类');
        }
        $cateInfo->name = $data['name'];
        $cateInfo->sort = $data['sort'];

        if ($cateInfo->save()) {
            return $this->success('分类修改成功');
        } else {
            return $this->fail('分类没有查到无法删除');
        }
    }

    /**
     * 删除分类
     * @param CategoryServices $services
     * @param $id
     * @return mixed
     */
    public function deleteCate(CategoryServices $services, ChatServiceSpeechcraftServices $speechcraftServices, $id)
    {
        $cateInfo = $services->get($id);
        if (!$cateInfo) {
            return $this->fail('分类不存在');
        }
        if ($cateInfo->owner_id != $this->kefuId || $cateInfo->type != 1) {
            return $this->fail('只能删除自己创建的分类');
        }
        if ($speechcraftServices->count(['cate_id' => $id])) {
            return $this->fail('请先删除分类下的话术');
        }
        if ($cateInfo->delete()) {
            return $this->success('删除成功');
        } else {
            return $this->fail('删除失败');
        }
    }

    /**
     * 获取当前客服分类
     * @param CategoryServices $services
     * @param $type
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getCateList(CategoryServices $services, $type)
    {
        return $this->success($services->getCateList(['owner_id' => $type ? $this->kefuId : 0, 'type' => 1], ['id', 'name', 'sort']));
    }

    /**
     * 添加话术
     * @param Request $request
     * @param ChatServiceSpeechcraftServices $services
     * @param CategoryServices $categoryServices
     * @return mixed
     */
    public function saveSpeechcraft(Request $request, ChatServiceSpeechcraftServices $services, CategoryServices $categoryServices)
    {
        $data = $request->postMore([
            ['title', ''],
            ['cate_id', 0],
            ['message', ''],
            ['sort', 0]
        ]);

        validate(SpeechcraftValidate::class)->check($data);

        if (!$categoryServices->count(['owner_id' => $this->kefuId, 'type' => 1, 'id' => $data['cate_id']])) {
            return $this->fail('您选择的分类不存在');
        }
        if ($services->count(['message' => $data['message']])) {
            return $this->fail('添加的内容重复');
        }
        $data['add_time'] = time();
        $data['kefu_id'] = $this->kefuId;

        $res = $services->save($data);
        if ($res) {
            return $this->success('添加话术成功', $res->toArray());
        } else {
            return $this->fail('添加话术失败');
        }
    }

    /**
     * 修改话术
     * @param Request $request
     * @param ChatServiceSpeechcraftServices $services
     * @param CategoryServices $categoryServices
     * @param $id
     * @return mixed
     */
    public function editSpeechcraft(Request $request, ChatServiceSpeechcraftServices $services, CategoryServices $categoryServices, $id)
    {
        $data = $request->postMore([
            ['title', ''],
            ['cate_id', 0],
            ['message', ''],
        ]);

        if (!$data['message']) {
            return $this->fail('话术标题内容不能为空');
        }
        if (!$categoryServices->count(['owner_id' => $this->kefuId, 'type' => 1, 'id' => $data['cate_id']])) {
            return $this->fail('您选择的分类不存在');
        }
        $speechcraft = $services->get($id);
        if (!$speechcraft) {
            return $this->fail('话术没有被查到');
        }
        if (!$speechcraft->kefu_id) {
            return $this->fail('公共话术不能修改');
        }
        if ($speechcraft->kefu_id != $this->kefuId) {
            return $this->fail('只能修改自己创建的话术');
        }
        $speechcraft->title = $data['title'];
        if ($data['cate_id']) {
            $speechcraft->cate_id = $data['cate_id'];
        }
        $speechcraft->message = $data['message'];

        if ($speechcraft->save()) {
            return $this->success('修改成功');
        } else {
            return $this->fail('修改失败');
        }
    }

    /**
     * 删除话术
     * @param ChatServiceSpeechcraftServices $services
     * @param $id
     * @return mixed
     */
    public function deleteSpeechcraft(ChatServiceSpeechcraftServices $services, $id)
    {
        $speechcraft = $services->get($id);
        if (!$speechcraft) {
            return $this->fail('话术没有被查到');
        }
        if ($speechcraft->kefu_id != $this->kefuId) {
            return $this->fail('只能删除自己创建的话术');
        }
        if ($speechcraft->delete()) {
            return $this->success('删除成功');
        } else {
            return $this->fail('删除失败');
        }
    }

    /**
     * 聊天记录
     * @param Request $request
     * @return mixed
     */
    public function getChatList(Request $request)
    {
        [$userId, $upperId, $is_tourist] = $request->postMore([
            ['user_id', 0],
            ['upperId', 0],
            ['is_tourist', 0],
        ], true);
        if (!$userId) {
            return $this->fail('缺少参数');
        }
        return $this->success($this->services->getChatList($this->kefuInfo['user_id'], $userId, (int)$upperId, $this->kefuInfo['appid']));
    }

    /**
     * 当前客服详细信息
     * @return mixed
     */
    public function getServiceInfo()
    {
        $this->kefuInfo['site_name'] = sys_config('site_name');
        /** @var \app\services\TenantPlanServices $planServices */
        $planServices = app()->make(\app\services\TenantPlanServices::class);
        //数据导出开关同时受平台配置与套餐功能约束
        $exportOpen = sys_config('config_export_open');
        if (!$planServices->hasFeature(\crmeb\services\tenant\TenantContext::id(), 'data_export')) {
            $exportOpen = 0;
        }
        $this->kefuInfo['config_export_open'] = $exportOpen;
        $this->kefuInfo['user_ids'] = $this->services->getColumn(['appid' => $this->kefuInfo['appid']], 'user_id');
        //会话转线索是平台自己的销售动作，租户客服看不到这个入口
        $this->kefuInfo['can_to_lead'] = (int)(\crmeb\services\tenant\TenantContext::id() === \app\models\Tenant::DEFAULT_TENANT_ID);
        return $this->success($this->kefuInfo->toArray());
    }

    /**
     * 客服转接
     * @return mixed
     */
    /**
     * AI正在接待的会话列表
     * @return mixed
     */
    public function getAiSessions()
    {
        [$nickname] = $this->request->getMore([['nickname', '']], true);
        /** @var \app\services\ai\AiTransferServices $transferServices */
        $transferServices = app()->make(\app\services\ai\AiTransferServices::class);
        return $this->success($transferServices->getAiSessions($this->kefuInfo['appid'], (string)$nickname));
    }

    /**
     * 人工接管AI会话
     * @return mixed
     */
    public function takeOverAiSession()
    {
        [$userId] = $this->request->postMore([['user_id', 0]], true);
        if (!$userId) {
            return $this->fail('缺少访客id');
        }
        /** @var \app\services\ai\AiTransferServices $transferServices */
        $transferServices = app()->make(\app\services\ai\AiTransferServices::class);
        if (!$transferServices->takeOver($this->kefuInfo['appid'], (int)$userId, (int)$this->kefuInfo['user_id'])) {
            return $this->fail('接管失败，该会话可能已被处理');
        }
        return $this->success('接管成功');
    }

    public function transfer()
    {
        [$kefuToUserId, $userId] = $this->request->postMore([
            ['kefuToUserId', 0],
            ['user_id', 0]
        ], true);
        if (!$kefuToUserId || !$userId) {
            return $this->fail('缺少转接人id');
        }
        $this->services->setTransfer($this->kefuInfo['appid'], $this->kefuInfo['user_id'], (int)$userId, (int)$kefuToUserId);
        return $this->success('转接成功');
    }

    /**
     * 确认登录
     * @param Request $request
     * @param string $code
     * @return mixed
     */
    public function setLoginCode(Request $request)
    {
        $code = $request->post('code');
        if (!$code) {
            return app('json')->fail('登录CODE不存在');
        }
        $cacheCode = CacheService::get($code);
        if ($cacheCode === false || $cacheCode === null) {
            return app('json')->fail('二维码已过期请重新扫描');
        }
        $userInfo = $this->services->get(['id' => $request->kefuId()]);
        if (!$userInfo) {
            return app('json')->fail('您不是客服无法登录');
        }
        $userInfo->uniqid = $code;
        $userInfo->save();
        CacheService::set($code, '0', 600);
        return app('json')->success('登录成功');
    }

    /**
     * @param ChatAutoReplyServices $services
     * @return mixed
     */
    public function getAuthReply(ChatAutoReplyServices $services)
    {
        return $this->success($services->getAuthReply($this->kefuInfo['appid'], (int)$this->kefuInfo['user_id']));
    }

    /**
     * 修改和添加
     * @param Request $request
     * @param ChatAutoReplyServices $services
     * @param $id
     * @return mixed
     */
    public function saveAuthReply(Request $request, ChatAutoReplyServices $services, $id)
    {
        $data = $request->postMore([
            ['keyword', ''],
            ['content', ''],
            ['sort', 0]
        ]);
        if (!$data['keyword']) {
            return $this->fail('缺少关键字');
        }
        if (!$data['content']) {
            return $this->fail('缺少回复内容');
        }
        /** @var \app\services\TenantPlanServices $planServices */
        $planServices = app()->make(\app\services\TenantPlanServices::class);
        $planServices->assertFeature(\crmeb\services\tenant\TenantContext::id(), 'auto_reply', '当前套餐不支持自动回复，请联系管理员升级套餐');

        if ($id) {
            $where = ['id' => $id, 'user_id' => $this->kefuInfo['user_id'], 'appid' => $this->kefuInfo['appid']];
            if (!$services->getCount($where)) {
                return $this->fail('自动回复不存在或无权操作');
            }
            $services->update(['id' => $id], $data);
        } else {
            $data['user_id'] = $this->kefuInfo['user_id'];
            $data['appid'] = $this->kefuInfo['appid'];
            $services->save($data);
        }

        return $this->success('保存自动回复成功');
    }

    /**
     * 删除自动回复
     * @param ChatAutoReplyServices $services
     * @param $id
     * @return mixed
     */
    public function deleteAuthReply(ChatAutoReplyServices $services, $id)
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }
        $where = ['id' => $id, 'user_id' => $this->kefuInfo['user_id'], 'appid' => $this->kefuInfo['appid']];
        if (!$services->getCount($where)) {
            return $this->fail('自动回复不存在或无权操作');
        }

        if ($services->delete($where)) {
            return $this->success('删除成功');
        } else {
            return $this->fail('删除失败');
        }

    }

    /**
     * 设置自动回复
     * @param $value
     * @return mixed
     */
    public function setAutoReply($value)
    {
        if ($value) {
            /** @var \app\services\TenantPlanServices $planServices */
            $planServices = app()->make(\app\services\TenantPlanServices::class);
            $planServices->assertFeature(\crmeb\services\tenant\TenantContext::id(), 'auto_reply', '当前套餐不支持自动回复，请联系管理员升级套餐');
        }
        $this->services->update(['id' => $this->kefuId], ['auto_reply' => $value]);
        return $this->success('设置成功');
    }

    /**
     * 设置是否后台
     * @param $backstage
     * @return mixed
     */
    public function backstage($backstage)
    {
        $this->services->update(['id' => $this->kefuId], ['is_backstage' => $backstage]);
        return $this->success('设置成功');
    }


}
