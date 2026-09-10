import request from '@/libs/request'

/**
 * 平台客服入口：返回会话窗口地址
 *
 * 平台自营租户拿到 enabled=false —— 它是被联系的一方，不展示这个入口。
 */
export function platformSupportApi () {
  return request({
    url: 'platform/support',
    method: 'get'
  })
}
