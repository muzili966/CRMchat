<template>
  <component :is="surface"/>
</template>
<script>
import { mapState } from 'vuex';
import { setLoc } from '@/libs/util'

// deviceType 用于强制指定终端，大小写不敏感；不传则按浏览器环境自动判断
const FORCE_PC = ['pc', 'desktop']
const FORCE_MOBILE = ['mobile', 'h5']

export default {
  components: {
    // 保持按需加载，避免访客端一次性拉取两套界面
    pcCustomerServer: () => import('./pcCustomerServer'),
    mobileCustomerServer: () => import('./mobileCustomerServer')
  },
  computed: {
    ...mapState('media', ['isMobile']),
    // 同一地址内切换界面而非跳转：接入方只需投放一个链接，
    // 地址栏也不会暴露 /chat/pc 这类终端专属路径
    surface() {
      const forced = String(this.$route.query.deviceType || '').toLowerCase();
      if(FORCE_PC.includes(forced)) return 'pcCustomerServer';
      if(FORCE_MOBILE.includes(forced)) return 'mobileCustomerServer';
      return this.isMobile ? 'mobileCustomerServer' : 'pcCustomerServer';
    }
  },
  created() {
    const tokenName = this.$route.query.tokenName || 'token';
    setLoc('mobile_token', this.$route.query[tokenName]);
  }
}
</script>
