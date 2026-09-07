import request from '@/libs/request'

/**
 * 历史会话：会话视角列表（一行一次「客服×访客」接待）
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
 * 历史会话：访客视角列表（按访客聚合其全部会话）
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
