<template>
  <div v-if="enabled" class="ps-root">
    <!-- 右下角固定圆形浮标，与主流客服挂件同一位置，打开会话后让位给面板 -->
    <div v-show="!opened" class="ps-bubble" :style="bubbleStyle"
         @mouseenter="hover = true" @mouseleave="hover = false" @click="open">
      <img v-if="icon" class="ps-bubble-icon" :src="icon" alt="平台客服" @error="icon = ''">
      <Icon v-else type="ios-chatbubbles" size="26"/>
      <span v-if="showTip" class="ps-tip" :class="{ 'ps-tip-on': hover }">联系平台客服</span>
    </div>

    <!-- 会话窗口：平台自营租户的接待页，标题栏用页面自带的，不再套一层 -->
    <div v-show="opened" class="ps-panel">
      <iframe v-if="url" class="ps-panel-frame" :src="url" frameborder="0" allow="microphone"></iframe>
    </div>
  </div>
</template>

<script>
  import { platformSupportApi } from '@/api/platform'

  const DEFAULT_COLOR = '#2d8cf0'

  export default {
    name: 'platformSupport',
    data () {
      return {
        enabled: false,
        url: '',
        icon: '',
        themeColor: DEFAULT_COLOR,
        showTip: true,
        hover: false,
        opened: false
      }
    },
    computed: {
      bubbleStyle () {
        return { background: this.themeColor || DEFAULT_COLOR }
      }
    },
    created () {
      //开页面只取外观与「要不要显示」，会话地址等点开再要：接入签名有效期只有几分钟
      platformSupportApi().then(res => {
        const d = res.data || {}
        if (!d.enabled) return
        this.enabled = true
        this.icon = d.icon || ''
        this.themeColor = d.theme_color || DEFAULT_COLOR
        this.showTip = d.show_tip !== 0
      }).catch(() => {})
      //会话页的关闭按钮在 iframe 里，只能靠它 postMessage 通知父窗口
      window.addEventListener('message', this.onFrameMessage)
    },
    beforeDestroy () {
      window.removeEventListener('message', this.onFrameMessage)
    },
    methods: {
      onFrameMessage (e) {
        if (e.data && e.data.type === 'closeWindow') {
          this.opened = false
        }
      },
      open () {
        this.hover = false
        //已经连上的会话别重连，重连会丢掉窗口里的上下文
        if (this.url) {
          this.opened = true
          return
        }
        platformSupportApi().then(res => {
          if (res.data && res.data.enabled) {
            this.url = res.data.url
            this.opened = true
          } else {
            this.enabled = false
          }
        }).catch(res => this.$Message.error(res.msg || '客服入口暂时不可用'))
      }
    }
  }
</script>

<style scoped>
  .ps-bubble {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 900;
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    border-radius: 50%;
    box-shadow: 0 4px 16px rgba(0, 0, 0, .2);
    cursor: pointer;
    /* 悬停只放大与加深阴影，按钮形状不跳变 */
    transition: transform .2s ease, box-shadow .2s ease;
  }
  .ps-bubble:hover {
    transform: scale(1.06);
    box-shadow: 0 6px 22px rgba(0, 0, 0, .28);
  }
  .ps-bubble-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    object-fit: cover;
  }
  /* 文字浮在按钮左侧，不改变按钮本身 */
  .ps-tip {
    position: absolute;
    right: 68px;
    padding: 6px 10px;
    background: rgba(0, 0, 0, .75);
    color: #fff;
    font-size: 12px;
    line-height: 1.4;
    border-radius: 4px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity .2s ease;
  }
  .ps-tip-on {
    opacity: 1;
  }
  .ps-panel {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 901;
    width: 400px;
    height: 580px;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 6px 32px rgba(0, 0, 0, .22);
  }
  .ps-panel-frame {
    display: block;
    width: 100%;
    height: 100%;
    border: 0;
  }
</style>
