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

export const getChatLayout = style => (
  CHAT_LAYOUT_PRESETS.find(item => item.value === style) || CHAT_LAYOUT_PRESETS[0]
)

export const getBubbleStyle = style => (
  CHAT_BUBBLE_PRESETS.find(item => item.value === style) || CHAT_BUBBLE_PRESETS[0]
)

export const getChatThemeVariables = primary => ({
  '--chat-primary': primary || '#2d8cf0',
  '--chat-page-bg': '#f3f6fb',
  '--chat-surface': '#ffffff',
  '--chat-incoming': '#ffffff',
  '--chat-text': '#172033',
  '--chat-muted': '#7f8ba5',
  '--chat-border': '#e5ebf5',
  '--chat-shadow': '0 12px 36px rgba(31, 45, 61, 0.14)'
})
