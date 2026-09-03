// 头像加载失败时的统一兜底。
// 用内联 SVG data URI 而不是再指向某个默认图片地址：头像本就因网络或
// 地址问题没加载出来，兜底若又依赖网络，很可能一起失败。data URI 不发请求，稳。

export const DEFAULT_AVATAR =
  'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4MCIgaGVpZ2h0PSI4MCIgdmlld0JveD0iMCAwIDgwIDgwIj48cmVjdCB3aWR0aD0iODAiIGhlaWdodD0iODAiIGZpbGw9IiNlOGVjZjMiLz48Y2lyY2xlIGN4PSI0MCIgY3k9IjMxIiByPSIxNSIgZmlsbD0iI2I5YzJkMCIvPjxwYXRoIGQ9Ik0xMiA3MmMwLTE1LjUgMTIuNS0yNiAyOC0yNnMyOCAxMC41IDI4IDI2eiIgZmlsbD0iI2I5YzJkMCIvPjwvc3ZnPg=='

/**
 * <img @error> 处理器：把加载失败的头像替换成默认头像。
 * 用 dataset 打标，避免默认图万一也触发 error 时陷入死循环。
 * @param {Event} event
 */
export function onAvatarError(event) {
  const image = event && event.target
  if (!image || image.dataset.avatarFallback) {
    return
  }
  image.dataset.avatarFallback = 'true'
  image.src = DEFAULT_AVATAR
}
