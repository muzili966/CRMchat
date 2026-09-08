import request from '@/libs/request'

/**
 * 敏感词列表
 * @param {Object} params keyword/category/status/page/limit
 */
export function sensitiveWordListApi (params) {
  return request({ url: 'sensitive/word', method: 'get', params })
}

/**
 * 新增敏感词
 * @param {Object} data
 */
export function sensitiveWordSaveApi (data) {
  return request({ url: 'sensitive/word', method: 'post', data })
}

/**
 * 修改敏感词
 * @param {Number} id
 * @param {Object} data
 */
export function sensitiveWordUpdateApi (id, data) {
  return request({ url: `sensitive/word/${id}`, method: 'put', data })
}

/**
 * 删除敏感词
 * @param {Number} id
 */
export function sensitiveWordDeleteApi (id) {
  return request({ url: `sensitive/word/${id}`, method: 'delete' })
}

/**
 * 批量导入敏感词
 * @param {Object} data words/category/action/scope
 */
export function sensitiveWordImportApi (data) {
  return request({ url: 'sensitive/word/import', method: 'post', data })
}

/**
 * 命中记录列表
 * @param {Object} params keyword/handled/scope/start/end/page/limit
 */
export function sensitiveHitListApi (params) {
  return request({ url: 'sensitive/hit', method: 'get', params })
}

/**
 * 标记命中记录已处理
 * @param {Number} id
 */
export function sensitiveHitHandleApi (id) {
  return request({ url: `sensitive/hit/handle/${id}`, method: 'put' })
}
