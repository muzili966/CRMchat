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

namespace app\services\ai;


use app\dao\ApplicationDao;
use app\dao\chat\ChatServiceDao;
use app\services\chat\ChatUserServices;
use crmeb\basic\BaseServices;
use crmeb\exceptions\AdminException;
use crmeb\services\tenant\TenantContext;

/**
 * 虚拟AI坐席生命周期
 *
 * AI坐席复用真人坐席的数据结构（chat_service + chat_user），
 * 从而分配、转接、消息推送、会话记录等既有链路无需为AI单开分支；
 * 坐席按appid隔离，故按(租户,应用)各建一枚。
 *
 * Class AiAgentServices
 * @package app\services\ai
 */
class AiAgentServices extends BaseServices
{

    /**
     * AI坐席昵称
     */
    const AI_NICKNAME = 'AI智能客服';

    /**
     * AI坐席登录账号前缀，双下划线开头必然不通过真人账号的 [a-zA-Z0-9]{4,30} 校验，天然避开撞号
     */
    const AI_ACCOUNT_PREFIX = '__ai_';

    /**
     * AI坐席默认头像（沿用项目自带客服头像CDN，可由租户在配置中覆盖）
     */
    const AI_AVATAR = 'https://chat.crmeb.net/uploads/attach/2021/09/20210906/c79d19dbfda66026ec891d188386cbb7.png';

    /**
     * AI坐席占位手机号前缀，含字母必然不通过 check_phone 校验
     */
    const AI_PHONE_PREFIX = 'ai';

    /**
     * 占位手机号中appid摘要的截取长度（chat_user.phone 仅 varchar(11)）
     */
    const PHONE_HASH_LENGTH = 9;

    /**
     * 随机密码原文字节数
     */
    const PASSWORD_RANDOM_BYTES = 32;

    /**
     * chat_service.welcome_words 为 varchar(255)，而AI欢迎语配置为 varchar(500)，同步时须截断
     */
    const WELCOME_WORDS_MAX_LENGTH = 255;

    /**
     * 是否AI虚拟坐席
     */
    const IS_AI_YES = 1;
    const IS_AI_NO = 0;

    /**
     * 坐席启用状态
     */
    const STATUS_ENABLE = 1;
    const STATUS_DISABLE = 0;

    /**
     * 坐席在线状态
     */
    const ONLINE_YES = 1;
    const ONLINE_NO = 0;

    /**
     * 关键词自动回复开关：AI坐席必须关闭，否则与AI回复形成双回复
     */
    const AUTO_REPLY_OFF = 0;

    /**
     * 非APP端登录
     */
    const IS_APP_NO = 0;

    /**
     * chat_user 侧标记
     */
    const IS_KEFU_YES = 1;
    const IS_DELETE_NO = 0;
    const USER_TYPE_PC = 0;

    /**
     * AI坐席判定缓存，键为 租户ID:chat_user id
     *
     * 消息热路径每条消息都要判定，故做进程内缓存；
     * is_ai 建号后不再变更，且新建坐席的 user_id 是全新自增值不可能命中旧缓存，无脏读风险
     * @var array
     */
    protected static $agentFlagCache = [];

    /**
     * AiAgentServices constructor.
     * @param ChatServiceDao $dao
     */
    public function __construct(ChatServiceDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 确保指定应用下存在AI坐席，返回其 chat_user id（幂等）
     * @param int $tenantId
     * @param string $appid
     * @return int
     */
    public function ensureAgent(int $tenantId, string $appid): int
    {
        if ($appid === '') {
            throw new AdminException('创建AI坐席缺少应用ID');
        }
        return TenantContext::runAs($tenantId, function () use ($appid) {
            $userId = $this->getAgentUserId($appid);
            return $userId ?: $this->transaction(function () use ($appid) {
                return $this->createAgent($appid);
            });
        });
    }

    /**
     * 为租户下全部未删除应用建号，返回 [appid => chat_user id]
     * @param int $tenantId
     * @return array
     */
    public function ensureAgentsForTenant(int $tenantId): array
    {
        $appids = $this->getTenantAppids($tenantId);
        if (!$appids) {
            return [];
        }
        $userIds = array_map(function (string $appid) use ($tenantId) {
            return $this->ensureAgent($tenantId, $appid);
        }, $appids);
        return array_combine($appids, $userIds);
    }

    /**
     * 停用租户全部AI坐席（不物理删除，历史会话仍需能查到坐席信息）
     * @param int $tenantId
     * @return void
     */
    public function disableAgentsForTenant(int $tenantId): void
    {
        $this->switchAgents($tenantId, self::STATUS_DISABLE);
    }

    /**
     * 启用租户全部AI坐席
     * @param int $tenantId
     * @return void
     */
    public function enableAgentsForTenant(int $tenantId): void
    {
        $this->switchAgents($tenantId, self::STATUS_ENABLE);
    }

    /**
     * 获取应用下AI坐席的 chat_user id，不存在返回0
     * @param string $appid
     * @return int
     */
    public function getAgentUserId(string $appid): int
    {
        return (int)$this->dao->value(['appid' => $appid, 'is_ai' => self::IS_AI_YES], 'user_id');
    }

    /**
     * 判定 chat_user id 是否AI坐席
     * @param int $userId
     * @return bool
     */
    public function isAiAgent(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $key = TenantContext::id() . ':' . $userId;
        if (!array_key_exists($key, self::$agentFlagCache)) {
            //必须走getCount：count() 依赖搜索器，模型未定义 searchIsAiAttr 会静默丢弃该条件
            self::$agentFlagCache[$key] = (bool)$this->dao->getCount(['user_id' => $userId, 'is_ai' => self::IS_AI_YES]);
        }
        return self::$agentFlagCache[$key];
    }

    /**
     * 同步欢迎语到租户全部AI坐席，复用系统既有欢迎语下发链路
     * @param int $tenantId
     * @param string $greeting
     * @return void
     */
    public function syncGreeting(int $tenantId, string $greeting): void
    {
        $words = mb_substr($greeting, 0, self::WELCOME_WORDS_MAX_LENGTH);
        TenantContext::runAs($tenantId, function () use ($words) {
            $this->dao->update(['is_ai' => self::IS_AI_YES], [
                'welcome_words' => $words,
                'update_time' => time(),
            ]);
        });
    }

    /**
     * 租户全部AI坐席的 chat_user id 列表
     * @param int $tenantId
     * @return array
     */
    public function getAgentIdsByTenant(int $tenantId): array
    {
        $ids = TenantContext::runAs($tenantId, function () {
            return $this->dao->getColumn(['is_ai' => self::IS_AI_YES], 'user_id');
        });
        return array_values(array_map('intval', $ids ?: []));
    }

    /**
     * AI坐席登录账号
     * @param string $appid
     * @return string
     */
    public static function buildAccount(string $appid): string
    {
        return self::AI_ACCOUNT_PREFIX . $appid;
    }

    /**
     * AI坐席占位手机号
     *
     * chat_user.phone 仅 varchar(11) 装不下appid，改取appid摘要，保证应用之间不撞号
     * @param string $appid
     * @return string
     */
    public static function buildPhone(string $appid): string
    {
        return self::AI_PHONE_PREFIX . substr(md5($appid), 0, self::PHONE_HASH_LENGTH);
    }

    /**
     * 组装AI坐席的客户档案行
     * @param string $appid
     * @param int $uid
     * @return array
     */
    public static function buildUserRow(string $appid, int $uid): array
    {
        return [
            'uid' => $uid,
            'appid' => $appid,
            'nickname' => self::AI_NICKNAME,
            'avatar' => self::AI_AVATAR,
            'phone' => self::buildPhone($appid),
            'is_kefu' => self::IS_KEFU_YES,
            'is_delete' => self::IS_DELETE_NO,
            'type' => self::USER_TYPE_PC,
        ];
    }

    /**
     * 组装AI坐席行（时间戳由调用方补充，保持本函数无副作用）
     * @param string $appid
     * @param int $userId chat_user id
     * @param string $passwordHash
     * @return array
     */
    public static function buildServiceRow(string $appid, int $userId, string $passwordHash): array
    {
        return [
            'appid' => $appid,
            'user_id' => $userId,
            'account' => self::buildAccount($appid),
            'password' => $passwordHash,
            'nickname' => self::AI_NICKNAME,
            'avatar' => self::AI_AVATAR,
            'phone' => self::buildPhone($appid),
            'status' => self::STATUS_ENABLE,
            'online' => self::ONLINE_YES,
            'is_ai' => self::IS_AI_YES,
            'auto_reply' => self::AUTO_REPLY_OFF,
            'welcome_words' => '',
            'client_id' => '',
            'is_app' => self::IS_APP_NO,
        ];
    }

    /**
     * 建号：先落客户档案再落坐席行，由调用方包裹事务
     * @param string $appid
     * @return int
     */
    protected function createAgent(string $appid): int
    {
        /** @var ChatUserServices $userServices */
        $userServices = app()->make(ChatUserServices::class);
        $uid = (int)$userServices->max(['appid' => $appid]) + 1;
        $userInfo = $userServices->save(self::buildUserRow($appid, $uid));
        if (!$userInfo) {
            throw new AdminException('AI坐席客户档案创建失败');
        }
        $userId = (int)$userInfo->id;
        $row = self::buildServiceRow($appid, $userId, $this->randomPasswordHash());
        $row['add_time'] = $row['update_time'] = time();
        if (!$this->dao->save($row)) {
            throw new AdminException('AI坐席创建失败');
        }
        return $userId;
    }

    /**
     * AI坐席永不登录，用随机明文的哈希占位，避免空密码被撞开
     * @return string
     */
    protected function randomPasswordHash(): string
    {
        return $this->passwordHash(bin2hex(random_bytes(self::PASSWORD_RANDOM_BYTES)));
    }

    /**
     * 切换租户全部AI坐席的启停状态
     * @param int $tenantId
     * @param int $status
     * @return void
     */
    protected function switchAgents(int $tenantId, int $status): void
    {
        $online = $status === self::STATUS_ENABLE ? self::ONLINE_YES : self::ONLINE_NO;
        TenantContext::runAs($tenantId, function () use ($status, $online) {
            $this->dao->update(['is_ai' => self::IS_AI_YES], [
                'status' => $status,
                'online' => $online,
                'update_time' => time(),
            ]);
        });
    }

    /**
     * 租户下全部未删除应用的appid
     *
     * 应用表受租户隔离，此处按tenant_id显式查询，须逃逸出当前上下文
     * @param int $tenantId
     * @return array
     */
    protected function getTenantAppids(int $tenantId): array
    {
        $appids = TenantContext::withoutTenant(function () use ($tenantId) {
            /** @var ApplicationDao $applicationDao */
            $applicationDao = app()->make(ApplicationDao::class);
            return $applicationDao->getColumn(['tenant_id' => $tenantId, 'is_delete' => 0], 'appid');
        });
        return array_values(array_filter($appids ?: []));
    }
}
