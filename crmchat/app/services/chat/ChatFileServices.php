<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\chat;

use app\services\system\attachment\SystemAttachmentServices;
use app\services\TenantPlanServices;
use crmeb\services\tenant\TenantContext;
use crmeb\services\tenant\TenantScope;
use crmeb\services\UploadService;
use think\exception\ValidateException;
use think\Request;

/**
 * 聊天文件上传
 *
 * 访客端与客服端共用一套规则：套餐门禁、扩展名白名单、大小上限、
 * 存储配额。图片走原有压缩上传，这里只管“文件消息”的原文件。
 * Class ChatFileServices
 * @package app\services\chat
 */
class ChatFileServices
{
    /**
     * 上传一个聊天文件，返回渲染文件卡片所需的元数据
     *
     * @param Request $request
     * @param string $field 表单字段名
     * @return array {url, name, size, ext}
     */
    public function upload(Request $request, string $field): array
    {
        $this->assertFeature();
        $fileHandle = $request->file($field);
        if (!$fileHandle) {
            throw new ValidateException('请选择文件');
        }
        $this->assertSize((int)$fileHandle->getSize());
        $this->assertExt(strtolower((string)$fileHandle->getOriginalExtension()));

        /** @var TenantPlanServices $planServices */
        $planServices = app()->make(TenantPlanServices::class);
        $planServices->checkStorage(TenantContext::id(), (int)$fileHandle->getSize());

        $upload = UploadService::init();
        //显式传白名单校验，不吃全局图片上传配置；文件不做压缩，保留原文件
        $info = $upload->to(TenantScope::uploadDir('store/chat_file'))
            ->validate([
                'filesize' => ChatServiceDialogueRecordServices::FILE_MAX_SIZE,
                'fileExt' => ChatServiceDialogueRecordServices::FILE_EXT,
            ])
            ->move($field);
        if ($info === false) {
            throw new ValidateException($upload->getError());
        }
        $res = $upload->getUploadInfo();

        /** @var SystemAttachmentServices $attachmentServices */
        $attachmentServices = app()->make(SystemAttachmentServices::class);
        $attachmentServices->attachmentAdd(
            $res['name'], $res['size'], $res['type'], $res['dir'], $res['thumb_path'],
            1, (int)sys_config('upload_type', 1), $res['time'], 2
        );

        $url = path_to_url($res['dir']);
        if (strpos($url, 'http') === false) {
            $url = $request->domain() . $url;
        }
        return [
            'url' => $url,
            //原始文件名截断到合理长度，去掉路径分隔符防止渲染时被误解析
            'name' => mb_substr(str_replace(['/', '\\'], '', (string)$fileHandle->getOriginalName()), 0, 120),
            'size' => (int)$fileHandle->getSize(),
            'ext' => strtolower((string)$fileHandle->getOriginalExtension()),
        ];
    }

    /**
     * 套餐门禁
     */
    protected function assertFeature()
    {
        /** @var TenantPlanServices $planServices */
        $planServices = app()->make(TenantPlanServices::class);
        if (!$planServices->hasFeature(TenantContext::id(), 'file_send')) {
            throw new ValidateException('当前套餐不支持发送文件，请升级套餐');
        }
    }

    /**
     * @param int $size
     */
    protected function assertSize(int $size)
    {
        if ($size <= 0) {
            throw new ValidateException('文件为空');
        }
        if ($size > ChatServiceDialogueRecordServices::FILE_MAX_SIZE) {
            throw new ValidateException('文件不能超过 20MB');
        }
    }

    /**
     * @param string $ext
     */
    protected function assertExt(string $ext)
    {
        if (!in_array($ext, ChatServiceDialogueRecordServices::FILE_EXT, true)) {
            throw new ValidateException('不支持的文件类型');
        }
    }
}
