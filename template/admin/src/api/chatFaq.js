import request from '@/libs/request'

/**
 * 常见问题列表
 * @param {Object} params appid/page/limit
 */
export function faqListApi (params) {
  return request({
    url: 'chat/faq',
    method: 'get',
    params
  })
}

/**
 * 新增常见问题
 * @param {Object} data appid/title/keyword/content/is_faq/sort
 */
export function faqSaveApi (data) {
  return request({
    url: 'chat/faq',
    method: 'post',
    data
  })
}

/**
 * 编辑常见问题
 * @param {Number} id
 * @param {Object} data
 */
export function faqUpdateApi (id, data) {
  return request({
    url: `chat/faq/${id}`,
    method: 'put',
    data
  })
}

/**
 * 删除常见问题
 * @param {Number} id
 * @param {String} appid
 */
export function faqDeleteApi (id, appid) {
  return request({
    url: `chat/faq/${id}`,
    method: 'delete',
    params: { appid }
  })
}

/**
 * 批量排序，ids 按展示先后排列
 * @param {Object} data appid/ids
 */
export function faqSortApi (data) {
  return request({
    url: 'chat/faq/sort',
    method: 'post',
    data
  })
}
