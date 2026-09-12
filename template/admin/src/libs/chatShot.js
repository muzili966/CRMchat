import html2canvas from 'html2canvas'

/**
 * 对话截图
 *
 * 用于纠纷举证、内部质检：CSV 导出带元数据但读起来累，截图所见即所得，
 * 发给对方一眼能看懂。注意这只是举证材料，不等于司法证据——一张图谁都
 * 说得清可以改，真要上法庭还得配存证与时间戳。
 */

/** 画布高度上限：各浏览器不一，取保守值，超了整张图会变全白 */
const MAX_CANVAS_HEIGHT = 12000

/**
 * 单次截图的消息条数上限
 *
 * 举证要的是完整记录，所以截图前会把整段对话拉全。但几千条消息同时渲染，
 * html2canvas 会耗尽内存，浏览器直接卡死，到那一步不如让人走 Excel 导出。
 */
export const MAX_SHOT_RECORDS = 1000

/** 截图倍率：2 倍在文字清晰与文件体积之间比较平衡 */
const SCALE = 2

/**
 * 同域判断
 *
 * 微信等第三方头像域名不带跨域响应头，html2canvas 取不到会留一片空白，
 * 还会拖慢整个截图过程，不如在克隆体里换成占位。
 * @param {String} src
 * @returns {Boolean}
 */
function sameOrigin (src) {
  if (!src || src.startsWith('data:')) return true
  try {
    return new URL(src, location.href).origin === location.origin
  } catch (e) {
    return false
  }
}

/** 小于这个尺寸的按头像处理，画个灰块就够，不必写字 */
const AVATAR_MAX = 80

/**
 * 取不到的图片换成占位
 *
 * 留一片空白会让人以为这里本来就没东西，对举证是有害的歧义；浏览器默认的
 * 裂图又依赖系统图标、样式不可控。统一画成带说明的灰框，一眼看得出
 * 「此处有图但没能渲染」。
 * @param {Document} doc
 * @param {HTMLImageElement} img
 */
function replaceWithPlaceholder (doc, img) {
  //克隆体不在渲染树里，量不到尺寸，只能用截图前在原图上标好的值
  const w = Number(img.dataset.shotW) || 120
  const h = Number(img.dataset.shotH) || 90
  const box = doc.createElement('div')

  if (w <= AVATAR_MAX && h <= AVATAR_MAX) {
    box.style.cssText = `width:${w}px;height:${h}px;border-radius:${img.style.borderRadius || '50%'};background:#dcdee2;display:inline-block`
  } else {
    box.style.cssText = `width:${w}px;height:${Math.max(h, 60)}px;border:1px dashed #c5c8ce;border-radius:4px;` +
      'background:#f8f8f9;color:#808695;font-size:12px;display:flex;align-items:center;justify-content:center;text-align:center'
    box.textContent = '图片未能加载'
  }
  if (img.parentNode) {
    img.parentNode.replaceChild(box, img)
  }
}

/**
 * 页眉：标明这张图出自哪里、是谁和谁的对话、什么时候导的
 *
 * 没有这行，一张聊天截图看不出来源，拿去举证等于白截。
 * @param {Object} meta title/subtitle
 * @returns {HTMLElement}
 */
function buildHeader (meta) {
  const box = document.createElement('div')
  box.style.cssText = 'padding:14px 18px;border-bottom:1px solid #e8eaec;background:#fafbfc;font-size:13px;color:#515a6e;line-height:1.7'
  const title = document.createElement('div')
  title.style.cssText = 'font-size:15px;font-weight:600;color:#17233d;margin-bottom:2px'
  title.textContent = meta.title || '对话记录'
  box.appendChild(title)
  if (meta.subtitle) {
    const sub = document.createElement('div')
    sub.textContent = meta.subtitle
    box.appendChild(sub)
  }
  const stamp = document.createElement('div')
  stamp.textContent = '导出时间 ' + formatNow()
  box.appendChild(stamp)
  return box
}

/**
 * @returns {String}
 */
function formatNow () {
  const d = new Date()
  const p = n => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`
}

/**
 * 触发浏览器下载
 * @param {HTMLCanvasElement} canvas
 * @param {String} filename
 */
function download (canvas, filename) {
  const link = document.createElement('a')
  link.download = filename
  link.href = canvas.toDataURL('image/png')
  link.click()
}

/**
 * 把整段对话截成 PNG
 *
 * 超过画布上限时纵向切成多张，文件名带序号，避免长会话截出一张全白图。
 * @param {HTMLElement} el 对话容器
 * @param {Object} meta title/subtitle/filename
 * @returns {Promise<Number>} 生成的图片张数
 */
export async function captureChat (el, meta = {}) {
  if (!el) throw new Error('找不到对话内容')

  //先在原图上量好尺寸、标好能不能渲染：克隆体里这两样都读不到
  const marked = Array.from(el.querySelectorAll('img'))
  marked.forEach(img => {
    const rect = img.getBoundingClientRect()
    img.dataset.shotW = String(Math.round(rect.width))
    img.dataset.shotH = String(Math.round(rect.height))
    img.dataset.shotOk = (sameOrigin(img.src) && img.complete && img.naturalWidth > 0) ? '1' : '0'
  })

  try {
    return await render(el, meta)
  } finally {
    marked.forEach(img => {
      delete img.dataset.shotW
      delete img.dataset.shotH
      delete img.dataset.shotOk
    })
  }
}

/**
 * 实际渲染并落盘
 * @param {HTMLElement} el
 * @param {Object} meta
 * @returns {Promise<Number>}
 */
async function render (el, meta) {
  const canvas = await html2canvas(el, {
    scale: SCALE,
    useCORS: true,
    backgroundColor: '#ffffff',
    //滚动容器只截可视区是没意义的，这里强制按内容实际高度截
    windowHeight: el.scrollHeight,
    height: el.scrollHeight,
    scrollY: 0,
    onclone (doc, cloned) {
      //克隆体上动手，不影响页面本身
      cloned.style.overflow = 'visible'
      cloned.style.height = 'auto'
      cloned.querySelectorAll('img').forEach(img => {
        if (img.dataset.shotOk === '1') return
        replaceWithPlaceholder(doc, img)
      })
      cloned.insertBefore(buildHeader(meta), cloned.firstChild)
    }
  })

  const base = meta.filename || 'chat'
  if (canvas.height <= MAX_CANVAS_HEIGHT) {
    download(canvas, `${base}.png`)
    return 1
  }

  //超高就分段：一次性画太长的图，浏览器会直接给一张空白
  const pages = Math.ceil(canvas.height / MAX_CANVAS_HEIGHT)
  for (let i = 0; i < pages; i++) {
    const part = document.createElement('canvas')
    const h = Math.min(MAX_CANVAS_HEIGHT, canvas.height - i * MAX_CANVAS_HEIGHT)
    part.width = canvas.width
    part.height = h
    part.getContext('2d').drawImage(canvas, 0, i * MAX_CANVAS_HEIGHT, canvas.width, h, 0, 0, canvas.width, h)
    download(part, `${base}_${i + 1}.png`)
  }
  return pages
}
