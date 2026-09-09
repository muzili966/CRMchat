import request from '@/libs/request'

/**
 * 历史对话：会话视角列表（一行一次「客服×访客」接待）
 * @param {Object} params keyword/kefu_user_id/appid/start/end/page/limit
 */
export function historySessionsApi (params) {
  return request({
    url: 'chat/history/sessions',
    method: 'get',
    params
  })
}

/**
 * 历史对话：访客视角列表（按访客聚合其全部会话）
 * @param {Object} params
 */
export function historyVisitorsApi (params) {
  return request({
    url: 'chat/history/visitors',
    method: 'get',
    params
  })
}

/**
 * 某访客的全部会话
 * @param {Number} id 访客ID
 */
export function historyVisitorSessionsApi (id) {
  return request({
    url: `chat/history/visitor/${id}`,
    method: 'get'
  })
}

/**
 * 对话内容
 * @param {Object} params agent_user_id/visitor_user_id/page/limit
 */
export function historyRecordsApi (params) {
  return request({
    url: 'chat/history/records',
    method: 'get',
    params
  })
}

/**
 * 导出整段对话，返回可下载的相对URL
 * @param {Object} params agent_user_id/visitor_user_id/format
 */
export function historyExportApi (params) {
  return request({
    url: 'chat/history/export',
    method: 'get',
    params
  })
}

/**
 * 按当前筛选条件全局导出对话
 *
 * 数据量不可预期，走下载中心异步产出，接口只负责排队，不直接返回文件。
 * @param {Object} params 与列表相同的筛选条件，另含 format
 */
export function historyExportAllApi (params) {
  return request({
    url: 'chat/history/export_all',
    method: 'post',
    params
  })
}

/**
 * 某访客与全部客服的往来，合并成一条时间线
 * @param {Object} params visitor_user_id/page/limit
 */
export function historyVisitorRecordsApi (params) {
  return request({
    url: 'chat/history/visitor_records',
    method: 'get',
    params
  })
}

/**
 * 导出访客的全量对话，返回可下载的相对URL
 * @param {Object} params visitor_user_id/format
 */
export function historyVisitorExportApi (params) {
  return request({
    url: 'chat/history/visitor_export',
    method: 'get',
    params
  })
}
