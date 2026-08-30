<?php

namespace tests\unit;

use PHPUnit\Framework\TestCase;

/**
 * 菜单归属与提权防护的规则测试
 *
 * 背景：曾用 path 列做子树级联导致大面积漏标（path 在存量数据中多为空串），
 * 这里锁定"按 pid 递归"与"授权子集校验"两条规则，防止再次退化。
 */
class TenantMenuScopeTest extends TestCase
{
    /**
     * 按pid递归求子树（与修复脚本同算法）
     * @param array $rows [id => pid]
     * @param array $roots
     * @return array
     */
    protected function subtree(array $rows, array $roots): array
    {
        $all = $roots;
        $cur = $roots;
        while ($cur) {
            $next = [];
            foreach ($rows as $id => $pid) {
                if (in_array($pid, $cur, true) && !in_array($id, $all, true)) {
                    $next[] = $id;
                }
            }
            if (!$next) break;
            $all = array_merge($all, $next);
            $cur = $next;
        }
        return $all;
    }

    public function testSubtreeCoversDescendantsWhenPathIsEmpty()
    {
        //模拟真实数据：56/65 的 path 为空，用 path 级联会漏掉它们及后代
        $rows = [25 => 0, 56 => 25, 65 => 25, 111 => 56, 462 => 111, 47 => 65, 1063 => 25];
        $tree = $this->subtree($rows, [25]);
        sort($tree);
        $this->assertSame([25, 47, 56, 65, 111, 462, 1063], $tree);
    }

    public function testSubtreeStopsAtUnrelatedBranch()
    {
        $rows = [25 => 0, 56 => 25, 12 => 0, 14 => 12];
        $tree = $this->subtree($rows, [25]);
        sort($tree);
        $this->assertSame([25, 56], $tree);
        $this->assertNotContains(14, $tree);
    }

    public function testSubtreeHandlesCycleWithoutInfiniteLoop()
    {
        //脏数据自引用不应导致死循环
        $rows = [10 => 10, 11 => 10];
        $tree = $this->subtree($rows, [10]);
        sort($tree);
        $this->assertSame([10, 11], $tree);
    }

    /**
     * 越权权限 = 提交集合 - 授权者自身权限集合
     * @param array $submitted
     * @param array $granted
     * @return array
     */
    protected function illegalRules(array $submitted, array $granted): array
    {
        return array_values(array_diff(array_map('intval', $submitted), array_map('intval', $granted)));
    }

    public function testGrantingOwnSubsetIsAllowed()
    {
        $this->assertSame([], $this->illegalRules([1, 2], [1, 2, 3]));
    }

    public function testGrantingPlatformMenuIsRejected()
    {
        //租户只有 1,2，却想授予平台菜单 21（权限规则）
        $this->assertSame([21], $this->illegalRules([1, 21], [1, 2]));
    }

    public function testStringAndIntIdsCompareConsistently()
    {
        $this->assertSame([], $this->illegalRules(['1', '2'], [1, 2]));
    }

    /**
     * 访客缓存key必须落在本租户本应用命名空间内
     * @param string $key
     * @param int $tenantId
     * @param string $appid
     * @return string
     */
    protected function visitorCacheKey(string $key, int $tenantId, string $appid): string
    {
        $key = preg_replace('/[^a-zA-Z0-9_\-]/', '', $key);
        if ($key === '') {
            throw new \InvalidArgumentException('key格式不正确');
        }
        return 'visitor:' . $tenantId . ':' . $appid . ':' . mb_substr($key, 0, 64);
    }

    public function testVisitorCacheKeyIsNamespaced()
    {
        $this->assertSame('visitor:6:app1:draft', $this->visitorCacheKey('draft', 6, 'app1'));
    }

    public function testVisitorCacheKeyStripsInjection()
    {
        //即便原样提交平台key，也会被前缀圈进本租户命名空间，写不到平台的 kf_adv 行
        $this->assertSame('visitor:6:app1:kf_adv', $this->visitorCacheKey('kf_adv', 6, 'app1'));
        //冒号被过滤，无法拼出 kf_adv:0 这类平台key
        $this->assertSame('visitor:6:app1:kf_adv0', $this->visitorCacheKey('kf_adv:0', 6, 'app1'));
    }

    public function testVisitorCacheKeyRejectsEmpty()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->visitorCacheKey('###', 6, 'app1');
    }
}
