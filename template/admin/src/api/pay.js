// 收银台接口：付款人不登录，凭链接上的单号 no 与签名 sign 访问
import request from '@/libs/request'

/**
 * 支付单摘要与可选支付方式
 * @param {Object} params no/sign
 */
export function cashierInfoApi (params) {
  return request({
    url: 'cashier',
    method: 'get',
    params,
    pay: true
  })
}

/**
 * 选定支付方式下单
 * @param {Object} data no/sign/channel
 */
export function cashierPayApi (data) {
  return request({
    url: 'cashier/pay',
    method: 'post',
    data,
    pay: true
  })
}

/**
 * 轮询支付状态
 * @param {Object} params no/sign
 */
export function cashierStatusApi (params) {
  return request({
    url: 'cashier/status',
    method: 'get',
    params,
    pay: true
  })
}
