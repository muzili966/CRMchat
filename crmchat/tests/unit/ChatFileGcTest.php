<?php

namespace tests\unit;

use app\services\chat\ChatFileGcServices;
use PHPUnit\Framework\TestCase;

/**
 * 聊天文件回收测试
 *
 * 回收会删磁盘文件，路径来自消息数据，稍有不慎就会被构造成越权删除；
 * 故重点钉死：att_dir 只从可信 url 还原，且只允许落在 uploads 目录内。
 */
class ChatFileGcTest extends TestCase
{
    /**
     * @var ChatFileGcServices
     */
    protected $gc;

    protected function setUp(): void
    {
        $this->gc = new ChatFileGcServices();
    }

    protected function call(string $method, ...$args)
    {
        $m = new \ReflectionMethod($this->gc, $method);
        $m->setAccessible(true);
        return $m->invoke($this->gc, ...$args);
    }

    /**
     * att_dir 恰好是 url 的 path 部分，据此还原磁盘路径
     */
    public function testAttDirFromUrl()
    {
        $this->assertSame(
            '/uploads/tenant/1/store/chat_file/20260903/abc.pdf',
            $this->call('attDirFromUrl', 'http://h:20118/uploads/tenant/1/store/chat_file/20260903/abc.pdf')
        );
        //带 query 也只取 path
        $this->assertSame(
            '/uploads/tenant/1/store/chat_file/x.pdf',
            $this->call('attDirFromUrl', 'https://h/uploads/tenant/1/store/chat_file/x.pdf?v=1')
        );
    }

    /**
     * 非 uploads 的 url 一律返回空，绝不据此删任何文件
     */
    public function testAttDirRejectsNonUploads()
    {
        foreach ([
            'http://h/etc/passwd',
            'http://h/statics/avatar/x.svg',
            'http://h/admin/index.html',
            '',
            'not a url',
        ] as $url) {
            $this->assertSame('', $this->call('attDirFromUrl', $url), $url);
        }
    }

    /**
     * unlinkAttDir 的安全闸：非法路径在触碰磁盘前即被拒
     */
    public function testUnlinkRejectsUnsafePaths()
    {
        foreach ([
            '/etc/passwd',
            '/uploads/../../../etc/passwd',
            'uploads/no-leading-slash',
            '/var/uploads/x',
            '',
        ] as $path) {
            $this->assertFalse($this->call('unlinkAttDir', $path), $path);
        }
    }

    /**
     * 文件消息（base64 JSON）能取回 url，含中文文件名也不破坏
     */
    public function testUrlFromFileMsg()
    {
        $meta = ['url' => 'http://h/uploads/tenant/1/store/chat_file/x.pdf', 'name' => '合同<甲>.pdf', 'size' => 9, 'ext' => 'pdf'];
        $msn = base64_encode(json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->assertSame($meta['url'], $this->call('urlFromFileMsg', $msn));
    }

    /**
     * 畸形消息不抛异常，安全返回空串
     */
    public function testMalformedFileMsgReturnsEmpty()
    {
        foreach (['', 'not-base64!!!', base64_encode('not json'), base64_encode('{}')] as $bad) {
            $this->assertSame('', $this->call('urlFromFileMsg', $bad), $bad);
        }
    }
}
