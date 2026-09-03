/**
 * 订阅能力的展示文案
 *
 * 能力是否可用、最低需要哪个套餐都由后端按真实套餐数据下发，这里只负责怎么说；
 * 文案统一收在一处，避免各页面自己编一套说法。
 */
export const PLAN_FEATURE_TEXT = {
  brand_custom: {
    name: '客户端装修',
    desc: '自定义访客窗口的标题、LOGO、主题色与布局风格，让客户看到的是你的品牌。'
  },
  white_label: {
    name: '去除平台标识',
    desc: '隐藏访客窗口底部的平台署名，对外完全呈现为贵司自有的服务。'
  },
  custom_ad: {
    name: '自定义广告位',
    desc: '在访客窗口投放自有的轮播图与推广内容，替代平台的默认广告。'
  },
  custom_domain: {
    name: '独立域名',
    desc: '用贵司自己的域名承载客服窗口，链接与邮件中不再出现平台域名。'
  },
  ai_reply: {
    name: 'AI 智能客服',
    desc: '由大模型 7×24 小时自动应答，识别到复杂问题再转接人工。'
  },
  auto_reply: {
    name: '自动回复',
    desc: '按关键词自动回复常见问题，客服不在线时也能第一时间响应。'
  },
  app_push: {
    name: 'APP 消息推送',
    desc: '客服离线时把新消息推送到手机，避免漏接客户咨询。'
  },
  data_export: {
    name: '数据导出',
    desc: '将会话记录与经营数据导出为表格，用于存档与二次分析。'
  },
  file_send: {
    name: '文件收发',
    desc: '访客与客服可互发办公文档、压缩包与原图附件（单文件20MB），免费版仅图片与文字。'
  }
}

/**
 * @param {string} feature
 * @returns {{name: string, desc: string}}
 */
export const getPlanFeatureText = feature => (
  PLAN_FEATURE_TEXT[feature] || { name: '该功能', desc: '' }
)

/**
 * 能力展示顺序：套餐卡片、平台端标签与开关表单都按这一份渲染
 *
 * 曾经这三处各写一份，新增能力时只更新了后端与业务逻辑，展示层全部漏改：
 * 租户看不到新能力，平台端编辑套餐时未提交的字段还会被后端按默认值清零。
 * 今后新增能力只需在 PLAN_FEATURE_TEXT 里补一条并加进本数组。
 */
export const PLAN_FEATURE_FIELDS = [
  'auto_reply',
  'brand_custom',
  'ai_reply',
  'custom_ad',
  'data_export',
  'app_push',
  'white_label',
  'custom_domain',
  'file_send'
]

/**
 * 配额类字段，zeroText 为 0 值时的说法（0 一律表示不限制）
 *
 * requires 声明该配额依赖的能力：能力关闭时配额没有意义，
 * 否则会出现"AI能力关着、AI配额却显示不限"这种自相矛盾的展示
 */
export const PLAN_QUOTA_FIELDS = [
  { key: 'app_limit', label: '接入应用', unit: '个' },
  { key: 'seat_limit', label: '客服坐席', unit: '个' },
  { key: 'daily_msg_limit', label: '日消息量', unit: '条' },
  { key: 'daily_ai_limit', label: 'AI日回复', unit: '次', requires: 'ai_reply' },
  { key: 'storage_limit_mb', label: '存储空间', unit: 'MB' },
  { key: 'record_keep_days', label: '记录保留', unit: '天', zeroText: '永久' }
]

/**
 * 套餐上与能力、配额相关的全部字段，用于编辑回填避免漏字段被清零
 * @returns {string[]}
 */
export const planEditableFields = () => [
  'id', 'name', 'price', 'sort',
  ...PLAN_QUOTA_FIELDS.map(item => item.key),
  ...PLAN_FEATURE_FIELDS
]
