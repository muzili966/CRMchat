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
 * @description 客户端装修--配置详情
 * @param {Object} param params {Object} appid {String} 应用ID
 */
export function appThemeApi(params) {
    return request({
        url: 'chat/theme',
        method: 'get',
        params
    })
}

/**
 * @description 客户端装修--保存配置
 * @param {Object} param data {Object} appid/title/logo/theme_color/pc_icon/mobile_icon/banners/custom_html/show_platform_brand
 */
export function appThemeSaveApi(data) {
    return request({
        url: 'chat/theme',
        method: 'post',
        data
    })
}
