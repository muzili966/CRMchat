// 支付单（平台专属）：平台向租户收款的单据
import request from '@/libs/request'

/**
 * @description 支付单列表
 * @param {Object} params status/channel/abnormal/pay_no/page/limit
 */
export function paymentListApi (params) {
  return request({
    url: 'setting/tenant/payment',
    method: 'get',
    params
  })
}

/**
 * @description 渠道与状态选项
 */
export function paymentOptionsApi () {
  return request({
    url: 'setting/tenant/payment/options',
    method: 'get'
  })
}

/**
 * @description 创建套餐续费支付单，返回链接与二维码
 * @param {Object} data tenant_id/plan_id/months/remark
 */
export function paymentCreateApi (data) {
  return request({
    url: 'setting/tenant/payment',
    method: 'post',
    data
  })
}

/**
 * @description 待支付单的链接与二维码
 * @param {Number} id
 */
export function paymentLinksApi (id) {
  return request({
    url: `setting/tenant/payment/deliver/${id}`,
    method: 'get'
  })
}

/**
 * @description 人工确认到账
 * @param {Number} id
 * @param {Object} data paid_amount/trade_no/remark
 */
export function paymentConfirmApi (id, data) {
  return request({
    url: `setting/tenant/payment/confirm/${id}`,
    method: 'post',
    data
  })
}

/**
 * @description 关闭待支付单
 * @param {Number} id
 */
export function paymentCloseApi (id) {
  return request({
    url: `setting/tenant/payment/close/${id}`,
    method: 'post'
  })
}

/**
 * @description 向渠道查询并同步支付状态
 * @param {Number} id
 */
export function paymentSyncApi (id) {
  return request({
    url: `setting/tenant/payment/sync/${id}`,
    method: 'post'
  })
}

/**
 * @description 已支付未开通的单补开通
 * @param {Number} id
 */
export function paymentFulfillApi (id) {
  return request({
    url: `setting/tenant/payment/fulfill/${id}`,
    method: 'post'
  })
}
