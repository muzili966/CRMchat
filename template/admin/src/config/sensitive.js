// 与后端 crmeb\utils\SensitiveFilter 的常量对应，改动需两边同步

export const ACTION_BLOCK = 1
export const ACTION_REPLACE = 2
export const ACTION_WARN = 3

export const ACTION_OPTIONS = [
  { value: ACTION_BLOCK, label: '拦截', desc: '消息不发出，发送方收到提示' },
  { value: ACTION_REPLACE, label: '替换', desc: '命中部分替换为 *，消息照常发出' },
  { value: ACTION_WARN, label: '仅告警', desc: '正常发出，只留一条命中记录' }
]

export const SCOPE_VISITOR = 1
export const SCOPE_AGENT = 2
export const SCOPE_AI = 4
export const SCOPE_ALL = SCOPE_VISITOR | SCOPE_AGENT | SCOPE_AI

export const SCOPE_OPTIONS = [
  { value: SCOPE_VISITOR, label: '访客发送' },
  { value: SCOPE_AGENT, label: '客服发送' },
  { value: SCOPE_AI, label: 'AI 回复' }
]

/**
 * 位掩码转成勾选数组
 * @param {Number} mask
 */
export function scopeToArray (mask) {
  return SCOPE_OPTIONS.filter(item => mask & item.value).map(item => item.value)
}

/**
 * 勾选数组转回位掩码
 * @param {Array} list
 */
export function scopeToMask (list) {
  return (list || []).reduce((sum, value) => sum | value, 0)
}

/**
 * 处置动作的中文名
 * @param {Number} action
 */
export function actionText (action) {
  const found = ACTION_OPTIONS.find(item => item.value === action)
  return found ? found.label : '未知'
}

/**
 * 作用范围的中文描述
 * @param {Number} mask
 */
export function scopeText (mask) {
  if (mask === SCOPE_ALL) return '全部'
  const names = SCOPE_OPTIONS.filter(item => mask & item.value).map(item => item.label)
  return names.length ? names.join('、') : '未设置'
}
