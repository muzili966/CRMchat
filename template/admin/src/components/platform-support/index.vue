<template>
  <div v-if="entry.enabled" class="ps-root">
    <!-- 收起态：贴着右边缘的半圆，悬停才整个滚出来并展开文字，不挡内容 -->
    <div class="ps-bubble" :class="{ 'ps-bubble-hover': hover }"
         @mouseenter="hover = true" @mouseleave="hover = false" @click="open">
      <Icon type="ios-chatbubbles" size="22"/>
      <span class="ps-bubble-text">平台客服</span>
    </div>

    <!-- 会话窗口：平台自营租户的接待页，标题栏用页面自带的，不再套一层 -->
    <!-- 只在首次打开时挂载 iframe，之后靠 v-show 保留会话，避免每次重连丢上下文 -->
    <div v-show="opened" class="ps-panel">
      <iframe v-if="loaded" class="ps-panel-frame" :src="entry.url" frameborder="0" allow="microphone"></iframe>
    </div>
  </div>
</template>

<script>
  import { platformSupportApi } from '@/api/platform'

  export default {
    name: 'platformSupport',
    data () {
      return {
        entry: { enabled: false, url: '' },
        hover: false,
        opened: false,
        loaded: false
      }
    },
    created () {
      this.getEntry()
      //会话页的关闭按钮在 iframe 里，只能靠它 postMessage 通知父窗口
      window.addEventListener('message', this.onFrameMessage)
    },
    beforeDestroy () {
      window.removeEventListener('message', this.onFrameMessage)
    },
    methods: {
      //平台自营租户拿到 enabled=false，整个入口不渲染
      getEntry () {
        platformSupportApi().then(res => {
          this.entry = (res.data && res.data.enabled) ? res.data : { enabled: false, url: '' }
        }).catch(() => {})
      },
      onFrameMessage (e) {
        if (e.data && e.data.type === 'closeWindow') {
          this.opened = false
        }
      },
      open () {
        this.loaded = true
        this.opened = true
      }
    }
  }
</script>

<style scoped>
  .ps-bubble {
    position: fixed;
    right: -14px;
    top: 45%;
    z-index: 900;
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    padding-left: 12px;
    gap: 8px;
    background: #2d8cf0;
    color: #fff;
    border-radius: 28px;
    box-shadow: 0 2px 12px rgba(45, 140, 240, .4);
    cursor: pointer;
    overflow: hidden;
    white-space: nowrap;
    /* 常态半个球缩在边缘外，悬停整体滚出来并让出文字的宽度 */
    transition: width .25s ease, right .25s ease, background-color .25s ease;
  }
  .ps-bubble-hover {
    right: 16px;
    width: 132px;
    background: #1c7ae0;
  }
  .ps-bubble-text {
    font-size: 13px;
    opacity: 0;
    transition: opacity .2s ease .05s;
  }
  .ps-bubble-hover .ps-bubble-text {
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
