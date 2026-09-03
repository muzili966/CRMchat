<template>
  <svg class="chat-icon" :width="size" :height="size" viewBox="0 0 24 24"
       fill="none" stroke="currentColor" :stroke-width="strokeWidth"
       stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <!-- 表情 -->
    <template v-if="name === 'emoji'">
      <circle cx="12" cy="12" r="9" />
      <path d="M8.5 14.5a4.5 4.5 0 0 0 7 0" />
      <path d="M9 9.5h.01M15 9.5h.01" />
    </template>
    <!-- 图片 -->
    <template v-else-if="name === 'image'">
      <rect x="3" y="4.5" width="18" height="15" rx="2.5" />
      <circle cx="8.5" cy="9.5" r="1.6" />
      <path d="M4 17l4.5-4.2a1.6 1.6 0 0 1 2.2 0L15 16.5m-1.5-1.5l2.2-2.1a1.6 1.6 0 0 1 2.2 0L20.5 15" />
    </template>
    <!-- 文件（回形针） -->
    <template v-else-if="name === 'file'">
      <path d="M20 11.5l-7.6 7.6a4.5 4.5 0 0 1-6.4-6.4l7.6-7.6a3 3 0 0 1 4.3 4.3l-7.6 7.6a1.5 1.5 0 0 1-2.2-2.1l7-7" />
    </template>
    <!-- 客服/转人工（耳麦） -->
    <template v-else-if="name === 'agent'">
      <path d="M5 12a7 7 0 0 1 14 0" />
      <rect x="3.5" y="12" width="3.5" height="6" rx="1.5" />
      <rect x="17" y="12" width="3.5" height="6" rx="1.5" />
      <path d="M19 18v.5a3 3 0 0 1-3 3h-2.5" />
    </template>
    <!-- 发送（纸飞机） -->
    <template v-else-if="name === 'send'">
      <path d="M20.5 3.5L10 14" />
      <path d="M20.5 3.5l-6.5 17-3.5-6.5L4 10.5z" />
    </template>
    <!-- 关闭 -->
    <template v-else-if="name === 'close'">
      <path d="M6 6l12 12M18 6L6 18" />
    </template>
    <!-- 用户/账号 -->
    <template v-else-if="name === 'user'">
      <circle cx="12" cy="8" r="4" />
      <path d="M4.5 20a7.5 7.5 0 0 1 15 0" />
    </template>
  </svg>
</template>

<script>
export default {
  name: 'chatIcon',
  props: {
    // emoji | image | file | agent | send | close
    name: {
      type: String,
      required: true
    },
    size: {
      type: [Number, String],
      default: 20
    },
    strokeWidth: {
      type: [Number, String],
      default: 1.7
    }
  }
}
</script>

<style scoped>
.chat-icon {
  display: block;
  vertical-align: middle;
}
</style>
