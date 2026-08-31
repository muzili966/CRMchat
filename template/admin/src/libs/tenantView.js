/**
 * 平台账号的租户视角
 *
 * 后端按请求上的 tenant_id 参数切换租户上下文（见 AdminAuthTokenMiddleware），
 * 此处负责在前端记住当前视角并自动附加到每个后台请求上。
 * 存 localStorage 而非仅内存：刷新页面后视角不应莫名其妙地退回平台。
 */

const STORAGE_KEY = 'crmchat_view_tenant'

let cache

/**
 * 当前视角，未切换时返回 null
 * @returns {{id: number, name: string}|null}
 */
export const getViewTenant = () => {
  if (cache !== undefined) return cache
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY)
    const data = raw ? JSON.parse(raw) : null
    cache = data && data.id ? data : null
  } catch (e) {
    cache = null
  }
  return cache
}

/**
 * @param {{id: number, name: string}|null} tenant 传 null 表示退回平台视角
 */
export const setViewTenant = tenant => {
  cache = tenant && tenant.id ? { id: Number(tenant.id), name: tenant.name || '' } : null
  try {
    cache
      ? window.localStorage.setItem(STORAGE_KEY, JSON.stringify(cache))
      : window.localStorage.removeItem(STORAGE_KEY)
  } catch (e) {
    // 隐私模式下写入会抛错，此时视角仅在本次会话内有效
  }
}

export const clearViewTenant = () => setViewTenant(null)

export default { getViewTenant, setViewTenant, clearViewTenant }
