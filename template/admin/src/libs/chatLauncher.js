// 悬浮入口图标预设。
// 每个预设是一个「主题色圆形气泡 + 白色线性图标」，跟随客户设置的主题色。
// 生成为内联 SVG data-URI 存进 pc_icon/mobile_icon —— 嵌入脚本本就以 <img src> 渲染，
// 无需改动。预设 key 以 data-p 标记嵌进 SVG，便于回显选中态与改色时重生成。

// 图标路径统一用 24 视窗，白色描边，居中放进 56 的气泡里。
// 造型与工具栏那套 chatIcon 同源，保证整个产品一套图标语言。
const ICONS = {
  chat: '<path d="M4 6.5h16a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-8l-5 4v-4H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2z"/>',
  message: '<path d="M4 6.5h16a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-8l-5 4v-4H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2z"/><path d="M8 12h.01M12 12h.01M16 12h.01"/>',
  headset: '<path d="M5 12a7 7 0 0 1 14 0"/><rect x="3.5" y="12" width="3.5" height="6" rx="1.5"/><rect x="17" y="12" width="3.5" height="6" rx="1.5"/><path d="M19 18v.5a3 3 0 0 1-3 3h-2.5"/>',
  smile: '<circle cx="12" cy="12" r="9"/><path d="M8.5 14.5a4.5 4.5 0 0 0 7 0"/><path d="M9 9.5h.01M15 9.5h.01"/>',
  help: '<circle cx="12" cy="12" r="9"/><path d="M9.2 9.5a2.8 2.8 0 0 1 5.4 1c0 1.8-2.6 2.2-2.6 3.8"/><path d="M12 17.5h.01"/>'
}

/**
 * 预设清单，供图库渲染
 */
export const LAUNCHER_PRESETS = [
  { key: 'chat', label: '对话气泡' },
  { key: 'message', label: '消息' },
  { key: 'headset', label: '客服' },
  { key: 'smile', label: '笑脸' },
  { key: 'help', label: '帮助' }
]

/**
 * 生成预设 SVG 字符串（气泡填主题色，图标白色描边）
 * @param {string} key
 * @param {string} color
 * @returns {string}
 */
function buildSvg(key, color) {
  const icon = ICONS[key] || ICONS.chat
  return '<svg xmlns="http://www.w3.org/2000/svg" data-p="' + key + '" width="56" height="56" viewBox="0 0 56 56">' +
    '<circle cx="28" cy="28" r="28" fill="' + color + '"/>' +
    '<g transform="translate(16 16)" fill="none" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' +
    icon + '</g></svg>'
}

/**
 * 预设 → data-URI，用作 <img src> 与 pc_icon 存储值
 * @param {string} key
 * @param {string} color
 * @returns {string}
 */
export function buildLauncherDataUri(key, color) {
  return 'data:image/svg+xml,' + encodeURIComponent(buildSvg(key, color))
}

/**
 * 从存储值反解预设 key；不是预设（自定义上传/默认/空）则返回空串
 * @param {string} value
 * @returns {string}
 */
export function readLauncherPreset(value) {
  if (typeof value !== 'string' || value.indexOf('data:image/svg+xml') !== 0) {
    return ''
  }
  const m = decodeURIComponent(value).match(/data-p="([a-z]+)"/)
  return m ? m[1] : ''
}

/**
 * value 是否为预设（用于改主题色时判断要不要重生成）
 * @param {string} value
 * @returns {boolean}
 */
export function isLauncherPreset(value) {
  return readLauncherPreset(value) !== ''
}
