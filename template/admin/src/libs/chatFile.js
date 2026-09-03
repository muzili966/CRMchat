// 文件消息（msn_type=7）的编解码与展示辅助。
// 服务端会对 msn 做 strip_tags/htmlspecialchars，所以文件元数据不直接放明文，
// 而是 base64(JSON) 存进 msn——base64 字母表不含 < > &，能安然穿过清洗。

/**
 * 把文件元数据编码进 msn
 * @param {{url:string,name:string,size:number,ext:string}} meta
 * @returns {string}
 */
export function encodeFileMsg(meta) {
  const json = JSON.stringify({
    url: meta.url || '',
    name: meta.name || '文件',
    size: Number(meta.size) || 0,
    ext: (meta.ext || '').toLowerCase()
  })
  // encodeURIComponent 兜住中文文件名，再 btoa 成纯 base64
  return btoa(unescape(encodeURIComponent(json)))
}

/**
 * 从 msn 解回文件元数据，解析失败返回 null（当作普通消息处理）
 * @param {string} msn
 * @returns {{url:string,name:string,size:number,ext:string}|null}
 */
export function decodeFileMsg(msn) {
  try {
    const obj = JSON.parse(decodeURIComponent(escape(atob(String(msn)))))
    if (!obj || !obj.url) {
      return null
    }
    return obj
  } catch (e) {
    return null
  }
}

/**
 * 字节数转易读大小
 * @param {number} bytes
 * @returns {string}
 */
export function humanSize(bytes) {
  const n = Number(bytes) || 0
  if (n < 1024) {
    return n + ' B'
  }
  if (n < 1024 * 1024) {
    return (n / 1024).toFixed(1) + ' KB'
  }
  return (n / 1024 / 1024).toFixed(1) + ' MB'
}

const EXT_GROUP = {
  pdf: 'pdf',
  doc: 'word', docx: 'word',
  xls: 'excel', xlsx: 'excel',
  ppt: 'ppt', pptx: 'ppt',
  zip: 'zip', rar: 'zip', '7z': 'zip',
  jpg: 'image', jpeg: 'image', png: 'image', gif: 'image', bmp: 'image', webp: 'image'
}

// 各类型的角标底色，避免一堆同色卡片糊在一起
const GROUP_COLOR = {
  pdf: '#e5484d',
  word: '#2b6cff',
  excel: '#1a9e57',
  ppt: '#e8710a',
  zip: '#8a6d3b',
  image: '#6b7280',
  file: '#8a95a6'
}

/**
 * 扩展名归组，用于选图标与配色
 * @param {string} ext
 * @returns {string}
 */
export function fileGroup(ext) {
  return EXT_GROUP[(ext || '').toLowerCase()] || 'file'
}

/**
 * @param {string} ext
 * @returns {string}
 */
export function fileColor(ext) {
  return GROUP_COLOR[fileGroup(ext)]
}

/**
 * 接入方 accept 属性用的扩展名清单（与后端白名单一致）
 */
export const FILE_ACCEPT =
  '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.7z,.jpg,.jpeg,.png,.gif,.bmp,.webp'
