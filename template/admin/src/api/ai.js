// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2021 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

import request from '@/libs/request'

/**
 * @description AI客服--配置详情（含近7天用量）
 */
export function aiConfigApi() {
    return request({
        url: 'chat/ai',
        method: 'get'
    })
}

/**
 * @description AI客服--保存配置
 * @param {Object} param data {Object} enable/mode/greeting/system_prompt/faq/transfer_keywords/model
 */
export function aiConfigSaveApi(data) {
    return request({
        url: 'chat/ai',
        method: 'post',
        data
    })
}

/**
 * @description AI客服--用量统计
 * @param {Object} param params {Object} 传值参数
 */
export function aiUsageApi(params) {
    return request({
        url: 'chat/ai/usage',
        method: 'get',
        params
    })
}
