<template>
  <div v-if="enabled" class="ps-root">
    <!-- 收起态：贴右缘只露一道圆弧，悬停整个圆滚出来，始终保持正圆 -->
    <div class="ps-bubble" :class="{ 'ps-bubble-hover': hover }"
         @mouseenter="hover = true" @mouseleave="hover = false" @click="open">
      <Icon type="ios-chatbubbles" size="24"/>
      <span class="ps-tip">联系平台客服</span>
    </div>

    <!-- 会话窗口：平台自营租户的接待页，标题栏用页面自带的，不再套一层 -->
    <div v-show="opened" class="ps-panel">
      <iframe v-if="url" class="ps-panel-frame" :src="url" frameborder="0" allow="microphone"></iframe>
    </div>
  </div>
</template>

<script>
  import { platformSupportApi } from '@/api/platform'

  export default {
    name: 'platformSupport',
    data () {
      return {
        enabled: false,
        url: '',
        hover: false,
        opened: false
      }
    },
    created () {
      //开页面只问「要不要显示这个入口」，地址等点开再取：接入签名有效期只有几分钟
      platformSupportApi().then(res => {
        this.enabled = !!(res.data && res.data.enabled)
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
    /* 常态大半个球藏在屏幕外，只在边缘留一道圆弧 */
    right: -30px;
    top: 45%;
    z-index: 900;
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding-right: 26px;
    background: #2d8cf0;
    color: #fff;
    border-radius: 50%;
    box-shadow: 0 2px 12px rgba(45, 140, 240, .4);
    cursor: pointer;
    transition: right .25s ease, padding-right .25s ease, box-shadow .25s ease;
  }
  .ps-bubble-hover {
    right: 18px;
    padding-right: 0;
    box-shadow: 0 4px 18px rgba(45, 140, 240, .55);
  }
  /* 文字用气泡提示浮在左侧，按钮本身始终是正圆 */
  .ps-tip {
    position: absolute;
    right: 68px;
    padding: 5px 10px;
    background: rgba(0, 0, 0, .75);
    color: #fff;
    font-size: 12px;
    line-height: 1.4;
    border-radius: 4px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity .2s ease .05s;
  }
  .ps-bubble-hover .ps-tip {
    opacity: 1;
  }
  .ps-panel {
    position: fixed;
    right: 20px;
    bottom: 20px;
    z-index: 901;
    width: 400px;
    height: 560px;
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
