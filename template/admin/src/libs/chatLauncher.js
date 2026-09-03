// 悬浮入口图标。
// 一份配置（图标 + 形状 + 文案）生成两张内联 SVG：PC 与移动端。
// 嵌入脚本本就以 <img src> 渲染，无需改动。配置以 data-* 标记嵌进 PC 的 SVG，
// 便于回显选中态、改主题色时重生成。
//
// PC 默认按钮本就是「图标+文字」的宽按钮，移动端是固定 52 的小圆，所以：
//   PC   —— 按形状渲染，胶囊形可带文案；
//   移动 —— 一律主题色方块+图标（容器 border-radius:50% 会裁成圆），不带文案。

// 图标路径统一 24 视窗，白色描边，与工具栏那套 chatIcon 同源。
const ICONS = {
  chat: '<path d="M4 6.5h16a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-8l-5 4v-4H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2z"/>',
  message: '<path d="M4 6.5h16a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-8l-5 4v-4H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2z"/><path d="M8 12h.01M12 12h.01M16 12h.01"/>',
  headset: '<path d="M5 12a7 7 0 0 1 14 0"/><rect x="3.5" y="12" width="3.5" height="6" rx="1.5"/><rect x="17" y="12" width="3.5" height="6" rx="1.5"/><path d="M19 18v.5a3 3 0 0 1-3 3h-2.5"/>',
  smile: '<circle cx="12" cy="12" r="9"/><path d="M8.5 14.5a4.5 4.5 0 0 0 7 0"/><path d="M9 9.5h.01M15 9.5h.01"/>',
  help: '<circle cx="12" cy="12" r="9"/><path d="M9.2 9.5a2.8 2.8 0 0 1 5.4 1c0 1.8-2.6 2.2-2.6 3.8"/><path d="M12 17.5h.01"/>'
}

const STROKE = 'fill="none" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"'

/**
 * 图标预设
 */
export const LAUNCHER_PRESETS = [
  { key: 'chat', label: '对话气泡' },
  { key: 'message', label: '消息' },
  { key: 'headset', label: '客服' },
  { key: 'smile', label: '笑脸' },
  { key: 'help', label: '帮助' }
]

/**
 * 形状。pill 支持文案，其余为图标气泡
 */
export const LAUNCHER_SHAPES = [
  { key: 'circle', label: '圆形', withText: false },
  { key: 'ellipse', label: '椭圆', withText: false },
  { key: 'square', label: '方形', withText: false },
  { key: 'rounded', label: '方形圆角', withText: false },
  { key: 'pill', label: '胶囊·带文字', withText: true }
]

/**
 * 文案最大字数（控制生成的 SVG 长度，落库列上限 1000）
 */
export const LAUNCHER_TEXT_MAX = 6

function icon(key) {
  return ICONS[key] || ICONS.chat
}

function esc(text) {
  return String(text).replace(/[<>&"]/g, function (c) {
    return { '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;' }[c]
  })
}

// 估算文案宽度：中文按整宽、其余按 0.6，供胶囊定宽
function textWidth(text, fontSize) {
  let w = 0
  for (const ch of String(text)) {
    w += /[一-龥＀-￯]/.test(ch) ? fontSize : fontSize * 0.6
  }
  return Math.ceil(w)
}

function svg(attrs, body, w, h) {
  return '<svg xmlns="http://www.w3.org/2000/svg" ' + attrs +
    ' width="' + w + '" height="' + h + '" viewBox="0 0 ' + w + ' ' + h + '">' + body + '</svg>'
}

// 图标气泡：主题色底 + 居中图标。椭圆为横向，其余 56 见方
// 返回 { body, w, h }
function shapeSvg(cfg, color) {
  const ic = icon(cfg.icon)
  if (cfg.shape === 'ellipse') {
    // 72×52 横椭圆，图标 24 居中：translate((72-24)/2, (52-24)/2)
    return {
      body: '<ellipse cx="36" cy="26" rx="36" ry="26" fill="' + color + '"/>' +
        '<g transform="translate(24 14)" ' + STROKE + '>' + ic + '</g>',
      w: 72, h: 52
    }
  }
  let bg
  if (cfg.shape === 'square') {
    bg = '<rect x="0" y="0" width="56" height="56" fill="' + color + '"/>'
  } else if (cfg.shape === 'rounded') {
    bg = '<rect x="0" y="0" width="56" height="56" rx="16" fill="' + color + '"/>'
  } else {
    bg = '<circle cx="28" cy="28" r="28" fill="' + color + '"/>'
  }
  return {
    body: bg + '<g transform="translate(16 16)" ' + STROKE + '>' + ic + '</g>',
    w: 56, h: 56
  }
}

// 胶囊：主题色底 + 左图标 + 右文案（文案空则退化为圆形）
function pillSvg(cfg, color) {
  const text = (cfg.text || '').slice(0, LAUNCHER_TEXT_MAX)
  if (!text) {
    return shapeSvg({ icon: cfg.icon, shape: 'circle' }, color)
  }
  const fs = 17
  const w = 20 + 24 + 8 + textWidth(text, fs) + 18
  // font-family 用 generic sans-serif：编码后更短，且系统默认 sans 本就含中文字形
  const body = '<rect x="0" y="0" width="' + w + '" height="48" rx="24" fill="' + color + '"/>' +
    '<g transform="translate(16 12)" ' + STROKE + '>' + icon(cfg.icon) + '</g>' +
    '<text x="' + (20 + 24 + 8) + '" y="24" fill="#ffffff" font-size="' + fs + '"' +
    ' font-family="sans-serif" dominant-baseline="central">' + esc(text) + '</text>'
  return { body: body, w: w, h: 48 }
}

function toUri(str) {
  return 'data:image/svg+xml,' + encodeURIComponent(str)
}

/**
 * PC 悬浮图标 data-URI（按形状，胶囊可带文案）
 * @param {{icon:string,shape:string,text:string}} cfg
 * @param {string} color
 * @returns {string}
 */
export function buildLauncherPc(cfg, color) {
  const marker = 'data-p="' + cfg.icon + '" data-shape="' + cfg.shape + '"'
  const g = cfg.shape === 'pill' ? pillSvg(cfg, color) : shapeSvg(cfg, color)
  return toUri(svg(marker, g.body, g.w, g.h))
}

/**
 * 移动端悬浮图标 data-URI（一律方块+图标，容器裁成圆，不带文案）
 * @param {{icon:string}} cfg
 * @param {string} color
 * @returns {string}
 */
export function buildLauncherMobile(cfg, color) {
  const body = '<rect x="0" y="0" width="56" height="56" fill="' + color + '"/>' +
    '<g transform="translate(16 16)" ' + STROKE + '>' + icon(cfg.icon) + '</g>'
  return toUri(svg('data-p="' + cfg.icon + '"', body, 56, 56))
}

/**
 * 图库缩略图用：把图标以指定形状渲染成小图
 * @param {string} iconKey
 * @param {string} shape
 * @param {string} color
 * @returns {string}
 */
export function buildLauncherThumb(iconKey, shape, color) {
  return buildLauncherPc({ icon: iconKey, shape: shape === 'pill' ? 'circle' : shape, text: '' }, color)
}

/**
 * 从 PC 存储值反解配置；非预设（自定义/默认/空）返回 null
 * @param {string} value
 * @returns {{icon:string,shape:string,text:string}|null}
 */
export function readLauncherConfig(value) {
  if (typeof value !== 'string' || value.indexOf('data:image/svg+xml') !== 0) {
    return null
  }
  const svgStr = decodeURIComponent(value)
  const p = svgStr.match(/data-p="([a-z]+)"/)
  if (!p) {
    return null
  }
  const shape = (svgStr.match(/data-shape="([a-z]+)"/) || [])[1] || 'circle'
  const t = svgStr.match(/<text[^>]*>([^<]*)<\/text>/)
  const text = t ? t[1].replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&').replace(/&quot;/g, '"') : ''
  return { icon: p[1], shape: shape, text: text }
}

/**
 * value 是否为预设（改主题色时判断要不要重生成）
 * @param {string} value
 * @returns {boolean}
 */
export function isLauncherPreset(value) {
  return readLauncherConfig(value) !== null
}
