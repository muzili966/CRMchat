<?php

namespace tests\unit;

use crmeb\basic\BaseUpload;
use PHPUnit\Framework\TestCase;

/**
 * 上传文件元信息读取测试
 *
 * 原实现拼出文件的完整URL后用get_headers()回源取size与type，即服务端对自己发HTTP请求：
 * 单进程环境必然自锁到超时，多进程下请求虽能返回但异常被吞，导致每条附件记录都存成
 * size=0、type=image/jpeg。文件就在本地磁盘，直接读盘才是对的。
 */
class UploadFileHeadersTest extends TestCase
{
    protected $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/crmchat_upload_test_' . getmypid();
        @mkdir($this->root . '/uploads', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/uploads/*') as $f) {
            @unlink($f);
        }
        @rmdir($this->root . '/uploads');
        @rmdir($this->root);
    }

    /**
     * 写入一个最小PNG，返回其字节数
     * @param string $name
     * @return int
     */
    protected function writePng(string $name): int
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        file_put_contents($this->root . '/uploads/' . $name, $png);
        return strlen($png);
    }

    public function testReadsSizeAndTypeFromDisk()
    {
        $size = $this->writePng('a.png');
        $headers = BaseUpload::readLocalFileHeaders($this->root, '/uploads/a.png');
        $this->assertSame($size, $headers['size']);
        $this->assertSame('image/png', $headers['type']);
    }

    public function testSizeIsNeverZeroForExistingFile()
    {
        $this->writePng('b.png');
        $headers = BaseUpload::readLocalFileHeaders($this->root, '/uploads/b.png');
        $this->assertGreaterThan(0, $headers['size'], '本地文件的大小不能再回落成0');
    }

    public function testTrailingSlashInRootIsTolerated()
    {
        $size = $this->writePng('c.png');
        $headers = BaseUpload::readLocalFileHeaders($this->root . '/', '/uploads/c.png');
        $this->assertSame($size, $headers['size']);
    }

    /**
     * 远程存储(OSS/七牛/COS)的filePath是完整URL，必须交回调用方回源
     */
    public function testRemoteUrlReturnsNull()
    {
        foreach (['http://cdn.a.com/x.png', 'https://cdn.a.com/x.png', '//cdn.a.com/x.png'] as $url) {
            $this->assertNull(BaseUpload::readLocalFileHeaders($this->root, $url));
        }
    }

    public function testMissingFileReturnsNull()
    {
        $this->assertNull(BaseUpload::readLocalFileHeaders($this->root, '/uploads/none.png'));
    }
}
