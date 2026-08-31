import soundFile from '@/assets/video/notify.wav'

/**
 * 通知提示音
 *
 * Chrome 要求页面发生过用户交互才允许播放声音，未交互时 play() 返回的 Promise 会被拒绝；
 * Firefox 默认宽松，所以同样的代码在两个浏览器里表现不同（火狐有声、Chrome 没声）。
 * 这里在首次交互时静音播放一次完成"解锁"，之后收到消息即可正常发声。
 */

const audio = new Audio(soundFile)
audio.preload = 'auto'

let unlocked = false
let bound = false

const UNLOCK_EVENTS = ['click', 'keydown', 'touchstart']

/**
 * 借一次用户交互解锁播放权限：静音播放后立即复位，用户听不到
 */
const unlock = () => {
  if (unlocked) return
  unlocked = true
  const wasMuted = audio.muted
  audio.muted = true
  const done = () => {
    audio.pause()
    audio.currentTime = 0
    audio.muted = wasMuted
  }
  const played = audio.play()
  if (played && typeof played.then === 'function') {
    played.then(done).catch(() => {
      // 解锁失败不影响后续，播放时还会再试一次
      unlocked = false
      audio.muted = wasMuted
    })
  } else {
    done()
  }
  UNLOCK_EVENTS.forEach(evt => document.removeEventListener(evt, unlock, true))
  bound = false
}

/**
 * 监听首次用户交互，幂等
 */
export const initNotifySound = () => {
  if (bound || unlocked || typeof document === 'undefined') return
  bound = true
  UNLOCK_EVENTS.forEach(evt => document.addEventListener(evt, unlock, true))
}

/**
 * 播放提示音；被浏览器拦截时静默跳过，不抛未捕获的 Promise 异常
 */
export const playNotifySound = () => {
  try {
    audio.currentTime = 0
    const played = audio.play()
    if (played && typeof played.then === 'function') {
      played.catch(() => {
        // 尚未发生用户交互，等下一次交互解锁后自然会有声音
        initNotifySound()
      })
    }
  } catch (e) {
    initNotifySound()
  }
}

export default { initNotifySound, playNotifySound }
