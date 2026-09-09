<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\chat;

use app\dao\chat\ChatServiceDialogueRecordDao;
use app\dao\TenantPlanOrderDao;
use app\models\TenantPlan;
use app\services\TenantPlanServices;
use crmeb\services\tenant\TenantContext;
use think\facade\Db;
use think\facade\Log;

/**
 * 聊天文件回收
 *
 * 两类清理：
 *  A 孤儿文件——上传了但发送失败、没有任何消息引用的文件，超时即删；
 *  B 保留期到期——会话记录按套餐 record_keep_days 过期时，连带删掉它引用的文件。
 *
 * 文件的磁盘路径可由消息里的 url 直接还原：att_dir 恰好等于 url 的 path 部分
 * （形如 /uploads/tenant/1/store/chat_file/...），磁盘路径 = public + att_dir。
 * 删除前一律校验路径落在 uploads 目录内，绝不按数据里的路径越权删除。
 * Class ChatFileGcServices
 * @package app\services\chat
 */
class ChatFileGcServices
{
    /**
     * 孤儿文件的宽限期（秒）：上传后这么久仍未被任何消息引用即判为孤儿
     */
    const ORPHAN_GRACE = 172800;

    /**
     * 聊天文件的存储目录标识
     */
    const CHAT_FILE_DIR = '/store/chat_file/';

    /**
     * 降级宽限期（天）：付费到期后这么久内，历史记录一条不动
     *
     * 没有它就是数据悬崖——租户到期回落免费版的次日，付费期间攒下的记录
     * 会按免费版的保留天数被清空，客户往往正是发现数据没了才想起续费。
     * 180 天覆盖半年的决策周期，也留足了导出时间。
     */
    const DOWNGRADE_GRACE_DAYS = 180;

    /**
     * 已被消息引用的附件标记：chat_file 目录内 pid 无其他用途，借作引用标志
     */
    const REF_FLAG = 1;

    /**
     * 跑一轮清理：先按保留期删过期记录及其文件，再回收孤儿文件
     * @return array 各租户清理计数，便于日志
     */
    public function run(): array
    {
        $result = [];
        foreach ($this->tenantPlans() as $tenantId => $planId) {
            $plan = app()->make(TenantPlanServices::class)->getTenantPlan((int)$tenantId);
            $keepDays = (int)($plan['record_keep_days'] ?? 0);
            //运营豁免与降级宽限期都会挡下保留期清理；孤儿文件与它们无关，照常回收
            $inGrace = $this->isExempt((int)$tenantId) || $this->inDowngradeGrace((int)$tenantId);
            $result[$tenantId] = TenantContext::runAs((int)$tenantId, function () use ($tenantId, $keepDays, $inGrace) {
                //0=不限，保持现状语义：既不删记录也不删文件
                $expired = ($keepDays > 0 && !$inGrace) ? $this->purgeExpired((int)$tenantId, $keepDays) : 0;
                $orphan = $this->purgeOrphans((int)$tenantId);
                return ['expired' => $expired, 'orphan' => $orphan, 'grace' => (int)$inGrace];
            });
        }
        return $result;
    }

    /**
     * 消息发送时标记其引用的文件为已引用，孤儿回收据此区分未发送成功的文件
     * @param int $tenantId
     * @param string $url 文件消息里的 url
     */
    public function markReferenced(int $tenantId, string $url)
    {
        $attDir = $this->attDirFromUrl($url);
        if ($attDir === '') {
            return;
        }
        try {
            TenantContext::withoutTenant(function () use ($tenantId, $attDir) {
                Db::name('system_attachment')
                    ->where(['tenant_id' => $tenantId, 'att_dir' => $attDir, 'pid' => 0])
                    ->update(['pid' => self::REF_FLAG]);
            });
        } catch (\Throwable $e) {
            //标记失败不影响发送；最坏是该文件在宽限期后被误判孤儿，属可接受降级
            Log::warning('聊天文件引用标记失败：' . $e->getMessage());
        }
    }

    /**
     * 该租户是否被运营单独豁免清理
     *
     * 诉讼举证期、重点客户的历史资料、长周期 POC 都可能需要单独放行，
     * 套餐里表达不了，交由平台运营在租户设置里按需开。
     * @param int $tenantId
     * @return bool
     */
    protected function isExempt(int $tenantId): bool
    {
        $until = (int)TenantContext::withoutTenant(function () use ($tenantId) {
            return Db::name('tenant')->where('id', $tenantId)->value('record_exempt_until');
        });
        return $until > time();
    }

    /**
     * 该租户是否处于降级宽限期
     * @param int $tenantId
     * @return bool
     */
    protected function inDowngradeGrace(int $tenantId): bool
    {
        /** @var TenantPlanOrderDao $orderDao */
        $orderDao = app()->make(TenantPlanOrderDao::class);
        //订单表按租户隔离，查历史订单要逃逸出当前上下文
        $paidUntil = TenantContext::withoutTenant(function () use ($orderDao, $tenantId) {
            return $orderDao->lastPaidExpireAt($tenantId);
        });
        return self::isInGrace($paidUntil, time());
    }

    /**
     * 宽限期判定
     *
     * 从未付费（0）不豁免，否则数据永远删不掉；付费买的永久套餐同样按 0 处理，
     * 那种租户的保留天数本来就由套餐决定，走不到这里。
     * @param int $paidUntil 最后一次付费订阅的到期时间
     * @param int $now
     * @return bool
     */
    public static function isInGrace(int $paidUntil, int $now): bool
    {
        if ($paidUntil <= 0) {
            return false;
        }
        return $now < $paidUntil + self::DOWNGRADE_GRACE_DAYS * 86400;
    }

    /**
     * B 保留期到期：删过期记录引用的文件，再删记录
     * @param int $tenantId
     * @param int $keepDays
     * @return int 删除的过期记录数
     */
    protected function purgeExpired(int $tenantId, int $keepDays): int
    {
        $before = time() - $keepDays * 86400;
        //先删文件：过期记录里带文件的（图片3/文件7），逐条还原磁盘路径删除
        $fileRows = Db::name('chat_service_dialogue_record')
            ->where('tenant_id', $tenantId)
            ->where('add_time', '<', $before)
            ->whereIn('msn_type', [3, 7])
            ->field('msn_type, msn')
            ->select()->toArray();
        foreach ($fileRows as $row) {
            $this->deleteByRecord((int)$row['msn_type'], (string)$row['msn'], $tenantId);
        }
        /** @var ChatServiceDialogueRecordDao $recordDao */
        $recordDao = app()->make(ChatServiceDialogueRecordDao::class);
        return (int)$recordDao->deleteBeforeTime($before);
    }

    /**
     * A 孤儿回收：chat_file 目录内、超宽限期、仍未被引用（pid=0）的文件
     * @param int $tenantId
     * @return int 删除的孤儿数
     */
    protected function purgeOrphans(int $tenantId): int
    {
        $rows = TenantContext::withoutTenant(function () use ($tenantId) {
            return Db::name('system_attachment')
                ->where('tenant_id', $tenantId)
                ->where('pid', 0)
                ->where('time', '<', time() - self::ORPHAN_GRACE)
                ->where('att_dir', 'like', '%' . self::CHAT_FILE_DIR . '%')
                ->field('att_id, att_dir')
                ->select()->toArray();
        });
        $count = 0;
        foreach ($rows as $row) {
            if ($this->unlinkAttDir((string)$row['att_dir'])) {
                $count++;
            }
            TenantContext::withoutTenant(function () use ($row) {
                Db::name('system_attachment')->where('att_id', $row['att_id'])->delete();
            });
        }
        return $count;
    }

    /**
     * 删除一条记录引用的文件与附件行
     * @param int $msnType
     * @param string $msn
     * @param int $tenantId
     */
    protected function deleteByRecord(int $msnType, string $msn, int $tenantId)
    {
        $url = $msnType === 7 ? $this->urlFromFileMsg($msn) : $msn;
        $attDir = $this->attDirFromUrl($url);
        if ($attDir === '') {
            return;
        }
        $this->unlinkAttDir($attDir);
        TenantContext::withoutTenant(function () use ($tenantId, $attDir) {
            Db::name('system_attachment')->where(['tenant_id' => $tenantId, 'att_dir' => $attDir])->delete();
        });
    }

    /**
     * 按 att_dir 删磁盘文件，校验落在 uploads 目录内
     * @param string $attDir 形如 /uploads/tenant/1/store/chat_file/xxx.pdf
     * @return bool
     */
    protected function unlinkAttDir(string $attDir): bool
    {
        if ($attDir === '' || strpos($attDir, '..') !== false || strpos($attDir, '/uploads/') !== 0) {
            return false;
        }
        $path = rtrim(app()->getRootPath(), '/\\') . '/public' . $attDir;
        if (!is_file($path)) {
            return true;
        }
        return @unlink($path);
    }

    /**
     * 从 url 还原 att_dir（url 的 path 部分即 att_dir）
     * @param string $url
     * @return string
     */
    protected function attDirFromUrl(string $url): string
    {
        $path = (string)parse_url(trim($url), PHP_URL_PATH);
        return strpos($path, '/uploads/') === 0 ? $path : '';
    }

    /**
     * 从文件消息（base64 JSON）里取出 url
     * @param string $msn
     * @return string
     */
    protected function urlFromFileMsg(string $msn): string
    {
        $json = base64_decode(trim($msn), true);
        if ($json === false) {
            return '';
        }
        $data = json_decode($json, true);
        return is_array($data) ? (string)($data['url'] ?? '') : '';
    }

    /**
     * 全部租户及其套餐
     * @return array tenantId => planId
     */
    protected function tenantPlans(): array
    {
        return TenantContext::withoutTenant(function () {
            return Db::name('tenant')->where('is_delete', 0)->column('plan_id', 'id');
        });
    }
}
