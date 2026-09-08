<template>
  <div class="chat-faq-card" v-if="list.length">
    <div class="cfc-head">你可能想问</div>
    <div class="cfc-item" :class="{ 'cfc-item-readonly': readonly }"
         v-for="item in list" :key="item.id" @click="pick(item)">
      <span class="cfc-item-text">{{ item.title }}</span>
      <svg class="cfc-item-arrow" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true">
        <path d="M9 6l6 6-6 6" fill="none" stroke="currentColor"
              stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
  </div>
</template>

<script>
export default {
  name: 'chatFaqCard',
  props: {
    // 编码后的 msn（base64 JSON，含 list）
    msn: {
      type: String,
      default: ''
    },
    // 客服侧与历史回看只需看见卡片内容，点了不该发消息
    readonly: {
      type: Boolean,
      default: false
    }
  },
  computed: {
    list () {
      if (!this.msn) return []
      try {
        // 与 chatFile 同一套路：base64(JSON)，escape/unescape 还原中文
        const obj = JSON.parse(decodeURIComponent(escape(atob(String(this.msn)))))
        return Array.isArray(obj.list) ? obj.list : []
      } catch (e) {
        // 正文损坏时静默降级为不展示，不该让一条脏消息把整个聊天窗口打崩
        console.error('[chatFaqCard] 常见问题卡片解析失败', e)
        return []
      }
    }
  },
  methods: {
    pick (item) {
      if (this.readonly) return
      this.$emit('pick', item)
    }
  }
}
</script>

<style lang="less" scoped>
/* 走聊天主题变量，随主题色/密度/边框一起变，不硬编码颜色 */
.chat-faq-card {
  min-width: 200px;
  background: var(--chat-incoming, #fff);
  border: 1px solid var(--chat-border, #e5ebf5);
  border-radius: inherit;
  overflow: hidden;
}
.cfc-head {
  padding: ~"calc(10px * var(--chat-density, 1))" var(--chat-bubble-pad-x, 14px);
  color: var(--chat-muted, #7f8ba5);
  font-size: 12px;
  letter-spacing: .3px;
}
.cfc-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: ~"calc(10px * var(--chat-density, 1))" var(--chat-bubble-pad-x, 14px);
  /* 分隔线而非独立色块：一排描边胶囊会比真实对话还抢眼 */
  border-top: 1px solid var(--chat-border, #e5ebf5);
  color: var(--chat-text, #172033);
  font-size: 13px;
  line-height: 1.5;
  cursor: pointer;
  transition: background .15s, color .15s;
  -webkit-tap-highlight-color: transparent;
}
.cfc-item-readonly {
  cursor: default;
}
.cfc-item:not(.cfc-item-readonly):hover,
.cfc-item:not(.cfc-item-readonly):active {
  background: var(--chat-page-bg, #f3f6fb);
  color: var(--chat-primary, #2d8cf0);
}
.cfc-item-text {
  flex: 1;
}
.cfc-item-arrow {
  flex: none;
  color: var(--chat-muted, #7f8ba5);
  opacity: .5;
  transition: transform .15s, opacity .15s;
}
.cfc-item:not(.cfc-item-readonly):hover .cfc-item-arrow {
  color: var(--chat-primary, #2d8cf0);
  opacity: 1;
  transform: translateX(2px);
}
</style>
