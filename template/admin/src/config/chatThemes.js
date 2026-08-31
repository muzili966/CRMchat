/**
 * 访客端主题预设的唯一数据源
 *
 * 桌面版、移动版与后台装修预览是三套独立 markup，若各自写一份预设样式必然漂移。
 * 这里把预设拆成两类下发给三端：
 *   气泡形状 —— 绝对值，三端完全一致（圆角、内边距、阴影、描边）
 *   布局密度 —— 倍率，三端各自声明基准尺寸后相乘，保证比例一致而不强求像素相同
 * 调整预设只改本文件，三端同步生效。
 */

export const DEFAULT_CHAT_LAYOUT = 'modern'
export const DEFAULT_BUBBLE_STYLE = 'soft'

export const CHAT_LAYOUT_PRESETS = [
  { value: 'modern', name: '标准布局', description: '均衡的信息密度，适合大多数场景', size: 'medium' },
  { value: 'minimal', name: '紧凑布局', description: '减少留白，同屏展示更多消息', size: 'compact' },
  { value: 'soft', name: '舒展布局', description: '更大头像与间距，阅读更轻松', size: 'spacious' },
  { value: 'midnight', name: '专注布局', description: '弱化头像，让注意力集中在内容', size: 'focus' }
]

export const CHAT_BUBBLE_PRESETS = [
  { value: 'soft', name: '柔和圆角', description: '自然圆角与轻阴影', radius: '14px 14px 14px 4px' },
  { value: 'clean', name: '利落方角', description: '小圆角、无阴影', radius: '6px' },
  { value: 'pill', name: '胶囊圆润', description: '饱满圆润的对话感', radius: '22px' },
  { value: 'outline', name: '轻描边', description: '低填充、强调边界', radius: '12px' },
  { value: 'card', name: '悬浮卡片', description: '层次更强的卡片质感', radius: '12px' }
]

/**
 * 气泡形状：绝对值，三端共用
 * radiusIn 收到的消息，radiusOut 自己发出的消息（尖角在相反侧）
 */
const BUBBLE_TOKENS = {
  soft: {
    radiusIn: '14px 14px 14px 4px',
    radiusOut: '14px 14px 4px 14px',
    padX: '14px',
    shadow: '0 3px 12px rgba(31, 45, 61, .05)',
    borderWidth: '1px',
    fill: 'var(--chat-incoming)'
  },
  clean: {
    radiusIn: '6px',
    radiusOut: '6px',
    padX: '14px',
    shadow: 'none',
    borderWidth: '1px',
    fill: 'var(--chat-incoming)'
  },
  pill: {
    radiusIn: '22px',
    radiusOut: '22px',
    padX: '17px',
    shadow: '0 3px 12px rgba(31, 45, 61, .05)',
    borderWidth: '1px',
    fill: 'var(--chat-incoming)'
  },
  outline: {
    radiusIn: '12px',
    radiusOut: '12px',
    padX: '14px',
    shadow: 'none',
    borderWidth: '1px',
    fill: 'transparent'
  },
  card: {
    radiusIn: '12px',
    radiusOut: '12px',
    padX: '14px',
    shadow: '0 7px 18px rgba(31, 45, 61, .14)',
    borderWidth: '0px',
    fill: 'var(--chat-incoming)'
  }
}

/**
 * 布局密度：倍率与结构开关
 * density 作用于行间距与内边距，avatarScale 作用于头像与顶部图标，
 * 各端用自己的基准尺寸乘以倍率，因此比例一致而绝对值可各自适配屏幕
 */
const LAYOUT_TOKENS = {
  modern: { density: 1, avatarScale: 1, avatarDisplay: 'block', headerJustify: 'space-between', headerLogo: 'block', bubbleMaxWidth: '1' },
  minimal: { density: 0.55, avatarScale: 0.85, avatarDisplay: 'block', headerJustify: 'space-between', headerLogo: 'block', bubbleMaxWidth: '1' },
  soft: { density: 1.45, avatarScale: 1.15, avatarDisplay: 'block', headerJustify: 'space-between', headerLogo: 'block', bubbleMaxWidth: '1' },
  midnight: { density: 1, avatarScale: 1, avatarDisplay: 'none', headerJustify: 'center', headerLogo: 'none', bubbleMaxWidth: '1.25' }
}

export const getChatLayout = style => (
  CHAT_LAYOUT_PRESETS.find(item => item.value === style) || CHAT_LAYOUT_PRESETS[0]
)

export const getBubbleStyle = style => (
  CHAT_BUBBLE_PRESETS.find(item => item.value === style) || CHAT_BUBBLE_PRESETS[0]
)

/**
 * 生成三端共用的CSS变量
 * @param {string} primary 主题色
 * @param {string} layout 布局预设
 * @param {string} bubble 气泡预设
 * @returns {Object}
 */
export const getChatThemeVariables = (primary, layout, bubble) => {
  const l = LAYOUT_TOKENS[layout] || LAYOUT_TOKENS[DEFAULT_CHAT_LAYOUT]
  const b = BUBBLE_TOKENS[bubble] || BUBBLE_TOKENS[DEFAULT_BUBBLE_STYLE]
  return {
    '--chat-primary': primary || '#2d8cf0',
    '--chat-page-bg': '#f3f6fb',
    '--chat-surface': '#ffffff',
    '--chat-incoming': '#ffffff',
    '--chat-text': '#172033',
    '--chat-muted': '#7f8ba5',
    '--chat-border': '#e5ebf5',
    '--chat-shadow': '0 12px 36px rgba(31, 45, 61, 0.14)',

    '--chat-bubble-radius-in': b.radiusIn,
    '--chat-bubble-radius-out': b.radiusOut,
    '--chat-bubble-pad-x': b.padX,
    '--chat-bubble-shadow': b.shadow,
    '--chat-bubble-border-width': b.borderWidth,
    '--chat-bubble-fill': b.fill,

    '--chat-density': String(l.density),
    '--chat-avatar-scale': String(l.avatarScale),
    '--chat-avatar-display': l.avatarDisplay,
    '--chat-header-justify': l.headerJustify,
    '--chat-header-logo': l.headerLogo,
    '--chat-bubble-width-scale': l.bubbleMaxWidth
  }
}
