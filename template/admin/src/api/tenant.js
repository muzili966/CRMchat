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
 * @description 租户--列表
 * @param {Object} param data {Object} 传值参数
 */
export function tenantListApi(data) {
    return request({
        url: 'setting/tenant',
        method: 'get',
        params: data
    })
}

/**
 * @description 租户--创建
 * @param {Object} param data {Object} 传值参数
 */
export function tenantSaveApi(data) {
    return request({
        url: 'setting/tenant',
        method: 'post',
        data
    })
}

/**
 * @description 租户--修改
 * @param {Number} param id {Number} 租户id
 * @param {Object} param data {Object} 传值参数
 */
export function tenantUpdateApi(id, data) {
    return request({
        url: `setting/tenant/${id}`,
        method: 'put',
        data
    })
}

/**
 * @description 租户--启用禁用
 * @param {Object} param data {Object} id与status
 */
export function tenantSetStatusApi(data) {
    return request({
        url: `setting/tenant/set_status/${data.id}/${data.status}`,
        method: 'put'
    })
}

/**
 * @description 租户--创建租户管理员
 * @param {Object} param data {Object} 传值参数
 */
export function tenantCreateAdminApi(data) {
    return request({
        url: 'setting/tenant/admin',
        method: 'post',
        data
    })
}

/**
 * @description 租户--开通/续费套餐
 * @param {Object} param data {Object} 传值参数
 */
export function tenantSubscribeApi(data) {
    return request({
        url: 'setting/tenant/subscribe',
        method: 'post',
        data
    })
}

/**
 * @description 套餐--列表
 * @param {Object} param data {Object} 传值参数
 */
export function planListApi(data) {
    return request({
        url: 'setting/tenant/plan',
        method: 'get',
        params: data
    })
}

/**
 * @description 套餐--在售下拉选项
 */
export function planAllApi() {
    return request({
        url: 'setting/tenant/plan/all',
        method: 'get'
    })
}

/**
 * @description 套餐--创建
 * @param {Object} param data {Object} 传值参数
 */
export function planSaveApi(data) {
    return request({
        url: 'setting/tenant/plan',
        method: 'post',
        data
    })
}

/**
 * @description 套餐--修改
 * @param {Number} param id {Number} 套餐id
 * @param {Object} param data {Object} 传值参数
 */
export function planUpdateApi(id, data) {
    return request({
        url: `setting/tenant/plan/${id}`,
        method: 'put',
        data
    })
}

/**
 * @description 套餐--上架/停售
 * @param {Object} param data {Object} id与status
 */
export function planSetStatusApi(data) {
    return request({
        url: `setting/tenant/plan/set_status/${data.id}/${data.status}`,
        method: 'put'
    })
}

/**
 * @description 订购对账--列表
 * @param {Object} param data {Object} 传值参数
 */
export function orderListApi(data) {
    return request({
        url: 'setting/tenant/orders',
        method: 'get',
        params: data
    })
}

/**
 * @description 订购对账--导出CSV
 * @param {Object} param data {Object} 传值参数
 */
/**
 * @description 订阅能力门禁：各功能页据此决定展示内容还是升级提示
 */
export function planFeatureApi() {
    return request({
        url: 'setting/tenant/features',
        method: 'get'
    })
}

export function orderExportApi(data) {
    return request({
        url: 'setting/tenant/orders/export',
        method: 'get',
        params: data
    })
}

/**
 * @description 发票--列表
 * @param {Object} param data {Object} 传值参数
 */
export function invoiceListApi(data) {
    return request({
        url: 'setting/tenant/invoice',
        method: 'get',
        params: data
    })
}

/**
 * @description 发票--开具/驳回
 * @param {Number} param id {Number} 发票id
 * @param {Object} param data {Object} status/invoice_no/remark
 */
export function invoiceAuditApi(id, data) {
    return request({
        url: `setting/tenant/invoice/audit/${id}`,
        method: 'put',
        data
    })
}

/**
 * @description 租户通知--列表
 * @param {Object} param data {Object} 传值参数
 */
export function noticeListApi(data) {
    return request({
        url: 'setting/tenant/notice',
        method: 'get',
        params: data
    })
}

/**
 * @description 租户通知--标记已读
 * @param {Number} param id {Number} 通知id
 */
export function noticeReadApi(id) {
    return request({
        url: `setting/tenant/notice/read/${id}`,
        method: 'put'
    })
}

/**
 * @description 通知--平台发送公告
 * @param {Object} param data {Object} tenant_ids与content
 */
export function noticeSendApi(data) {
    return request({
        url: 'setting/tenant/notice',
        method: 'post',
        data
    })
}

/**
 * @description 租户端--我的订阅概览
 */
export function mySubscriptionApi() {
    return request({
        url: 'setting/tenant/my',
        method: 'get'
    })
}

/**
 * @description 租户端--在售套餐展示（升级续订选择）
 */
export function tenantPlansApi() {
    return request({
        url: 'setting/tenant/plans',
        method: 'get'
    })
}

/**
 * @description 租户端--申请开票
 * @param {Object} param data {Object} order_id/title/tax_no/type/email
 */
export function invoiceApplyApi(data) {
    return request({
        url: 'setting/tenant/invoice',
        method: 'post',
        data
    })
}
