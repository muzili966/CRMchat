<?php

namespace tests\unit;

use app\services\ai\AiAgentServices;
use PHPUnit\Framework\TestCase;

/**
 * AI坐席账号生成规则测试
 *
 * 边界：账号必须不通过真人账号格式校验、占位手机号必须不通过 check_phone
 * 且不超过 chat_user.phone 的 11 位上限、同appid幂等、跨appid不撞号。
 */
class AiAgentAccountTest extends TestCase
{
    const APPID = '202116257358989495';
    const OTHER_APPID = '202116257358989496';

    /**
     * 真人客服账号校验规则，见 admin/chat/Service::save
     */
    const HUMAN_ACCOUNT_PATTERN = '/^[a-zA-Z0-9]{4,30}$/';

    /**
     * chat_user.phone 字段长度上限
     */
    const PHONE_MAX_LENGTH = 11;

    public function testAccountCarriesAppidWithPrefix()
    {
        $this->assertSame(AiAgentServices::AI_ACCOUNT_PREFIX . self::APPID, AiAgentServices::buildAccount(self::APPID));
    }

    public function testAccountNeverCollidesWithHumanAccount()
    {
        $this->assertSame(0, preg_match(self::HUMAN_ACCOUNT_PATTERN, AiAgentServices::buildAccount(self::APPID)));
    }

    public function testAccountIsIdempotentAndAppScoped()
    {
        $this->assertSame(AiAgentServices::buildAccount(self::APPID), AiAgentServices::buildAccount(self::APPID));
        $this->assertNotSame(AiAgentServices::buildAccount(self::APPID), AiAgentServices::buildAccount(self::OTHER_APPID));
    }

    public function testPhoneFitsColumnLength()
    {
        $this->assertSame(self::PHONE_MAX_LENGTH, strlen(AiAgentServices::buildPhone(self::APPID)));
    }

    public function testPhoneIsNeverAValidMobile()
    {
        $this->assertSame(0, preg_match('/^1[3456789]\d{9}$/', AiAgentServices::buildPhone(self::APPID)));
    }

    public function testPhoneIsIdempotentAndAppScoped()
    {
        $this->assertSame(AiAgentServices::buildPhone(self::APPID), AiAgentServices::buildPhone(self::APPID));
        $this->assertNotSame(AiAgentServices::buildPhone(self::APPID), AiAgentServices::buildPhone(self::OTHER_APPID));
    }

    public function testServiceRowDisablesKeywordAutoReply()
    {
        $row = AiAgentServices::buildServiceRow(self::APPID, 66, 'hash');
        $this->assertSame(AiAgentServices::AUTO_REPLY_OFF, $row['auto_reply'], 'AI坐席开启关键词自动回复会造成双回复');
        $this->assertSame(AiAgentServices::IS_AI_YES, $row['is_ai']);
        $this->assertSame(66, $row['user_id']);
        $this->assertSame('', $row['welcome_words'], '欢迎语由配置同步写入，建号时须留空');
    }

    public function testServiceRowHasNoTimestampSideEffect()
    {
        $row = AiAgentServices::buildServiceRow(self::APPID, 1, 'hash');
        $this->assertArrayNotHasKey('add_time', $row);
        $this->assertArrayNotHasKey('update_time', $row);
    }

    public function testUserRowMarkedAsKefu()
    {
        $row = AiAgentServices::buildUserRow(self::APPID, 8);
        $this->assertSame(AiAgentServices::IS_KEFU_YES, $row['is_kefu']);
        $this->assertSame(AiAgentServices::IS_DELETE_NO, $row['is_delete']);
        $this->assertSame(8, $row['uid']);
        $this->assertSame(AiAgentServices::buildPhone(self::APPID), $row['phone'], '两行记录须共用同一占位手机号');
    }
}
