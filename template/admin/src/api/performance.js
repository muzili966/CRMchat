import request from '@/libs/request'

/**
 * 绩效概览
 * @param {Object} params start/end/kefu_user_id/appid
 */
export function performanceOverviewApi (params) {
  return request({ url: 'chat/performance/overview', method: 'get', params })
}

/**
 * 按客服拆分的绩效
 * @param {Object} params
 */
export function performanceAgentsApi (params) {
  return request({ url: 'chat/performance/agents', method: 'get', params })
}

/**
 * 按天趋势
 * @param {Object} params
 */
export function performanceTrendApi (params) {
  return request({ url: 'chat/performance/trend', method: 'get', params })
}

/**
 * 会话明细
 * @param {Object} params 另含 rated/page/limit
 */
export function performanceSessionsApi (params) {
  return request({ url: 'chat/performance/sessions', method: 'get', params })
}
