<?php

namespace tests\unit;

use app\models\TenantPlan;
use app\services\chat\ChatServiceDialogueRecordServices;
use PHPUnit\Framework\TestCase;

/**
 * 聊天文件消息测试
 *
 * 文件消息是任意上传/下载的入口，扩展名白名单是核心安全控制；
 * 消息类型与套餐字段的登记漏一处，功能就会静默失效或越权。
 */
class ChatFileTest extends TestCase
{
    /**
     * 文件消息类型必须纳入合法类型白名单，否则 WS 入库时被判"格式错误"
     */
    public function testFileTypeIsRegistered()
    {
        $this->assertSame(7, ChatServiceDialogueRecordServices::MSN_TYPE_FILE);
        $this->assertContains(
            ChatServiceDialogueRecordServices::MSN_TYPE_FILE,
            ChatServiceDialogueRecordServices::MSN_TYPE
        );
    }

    /**
     * 绝不放行可执行或可内联脚本的类型——它们被静态目录按类型处理即成漏洞
     */
    public function testDangerousExtensionsAreNotWhitelisted()
    {
        $danger = ['php', 'php5', 'phtml', 'html', 'htm', 'svg', 'js', 'exe', 'sh', 'jsp', 'asp'];
        foreach ($danger as $ext) {
            $this->assertNotContains($ext, ChatServiceDialogueRecordServices::FILE_EXT, $ext);
        }
    }

    /**
     * 承诺支持的办公文档/压缩包/图片都在白名单内
     */
    public function testExpectedExtensionsAreWhitelisted()
    {
        $expected = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', '7z', 'jpg', 'png'];
        foreach ($expected as $ext) {
            $this->assertContains($ext, ChatServiceDialogueRecordServices::FILE_EXT, $ext);
        }
    }

    public function testSizeLimitIs20Mb()
    {
        $this->assertSame(20 * 1024 * 1024, ChatServiceDialogueRecordServices::FILE_MAX_SIZE);
    }

    /**
     * file_send 必须登记进套餐功能字段，否则 admin 编辑套餐时该列会被按默认值清零
     */
    public function testFileSendIsAPlanFeature()
    {
        $this->assertContains('file_send', TenantPlan::FEATURE_FIELDS);
    }

    /**
     * base64(JSON) 的载荷要能安然穿过 chat() 的 strip_tags/htmlspecialchars 清洗，
     * 连文件名里的尖括号也不能破坏结构
     */
    public function testEncodedPayloadSurvivesSanitize()
    {
        $meta = ['url' => 'http://h/uploads/x.pdf', 'name' => '方案<v2>.pdf', 'size' => 100, 'ext' => 'pdf'];
        $encoded = base64_encode(json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        //复刻 BaseHandler::chat 对 msn 的清洗
        $cleaned = trim(strip_tags(str_replace(["\n", "\t", "\r", "&nbsp;"], '', htmlspecialchars_decode($encoded))));
        $this->assertSame($encoded, $cleaned);
        $decoded = json_decode(base64_decode($cleaned), true);
        $this->assertSame('方案<v2>.pdf', $decoded['name']);
        $this->assertSame($meta['url'], $decoded['url']);
    }
}
