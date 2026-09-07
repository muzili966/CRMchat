import request from '@/libs/request'

/**
 * 下载中心：导出任务列表
 * @param {Object} params status/type/page/limit
 */
export function exportTaskListApi (params) {
  return request({
    url: 'export/task',
    method: 'get',
    params
  })
}

/**
 * 删除导出任务及其文件
 * @param {Number} id
 */
export function exportTaskDeleteApi (id) {
  return request({
    url: `export/task/${id}`,
    method: 'delete'
  })
}
