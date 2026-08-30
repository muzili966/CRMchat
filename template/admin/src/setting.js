// 请求接口地址 如果没有配置自动获取当前网址路径

const VUE_APP_API_URL = process.env.VUE_APP_API_URL || `${location.origin}/api/admin`
// 用host而非hostname：非标准端口部署（如内网 ip:20118）时必须带上端口，否则会连到80导致握手被拒
const VUE_APP_WS_ADMIN_URL = process.env.VUE_APP_WS_ADMIN_URL ||
    `${location.protocol === 'https:' ? 'wss:' : 'ws:'}//${location.host}`


const Setting = {
    // 接口请求地址
    apiBaseURL: VUE_APP_API_URL,
    //socket连接
    wsSocketUrl: VUE_APP_WS_ADMIN_URL,
    // 路由模式，可选值为 history 或 hash
    routerMode: 'history',
    // 页面切换时，是否显示模拟的进度条
    showProgressBar: true
}


export default Setting
