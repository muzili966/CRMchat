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

namespace crmeb\exceptions;


/**
 * 租户上下文异常：在未初始化租户上下文时访问受租户隔离约束的数据
 * Class TenantContextException
 * @package crmeb\exceptions
 */
class TenantContextException extends \RuntimeException
{
}
