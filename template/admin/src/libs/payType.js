// 订购记录的支付方式，与后端 TenantPlanOrder::PAY_TYPE_* 一致
const PAY_TYPES = {
  1: { label: '后台开通', color: 'blue' },
  2: { label: '线下转账', color: 'orange' },
  3: { label: '在线支付', color: 'green' }
}

const UNKNOWN = { label: '其他', color: 'default' }

export function payTypeOf (value) {
  return PAY_TYPES[value] || UNKNOWN
}
