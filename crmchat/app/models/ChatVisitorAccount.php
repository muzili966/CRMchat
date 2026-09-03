<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\models;

use crmeb\basic\BaseModel;

/**
 * 访客账号
 *
 * 凭据表，不吃环境里的租户上下文：查询一律显式带 tenant_id + appid，
 * 免得公网入口在上下文缺失时把别的租户的账号也捞进来。
 * Class ChatVisitorAccount
 * @package app\models
 */
class ChatVisitorAccount extends BaseModel
{
    protected $name = 'chat_visitor_account';

    protected $pk = 'id';

    //凭据查询自带 tenant_id 过滤，不依赖全局 Scope
    protected $tenantScoped = false;

    //int 存时间戳
    protected $autoWriteTimestamp = false;

    /**
     * 连续失败几次后锁定
     */
    const MAX_FAILED = 5;

    /**
     * 锁定时长（秒）
     */
    const LOCK_SECONDS = 900;
}
