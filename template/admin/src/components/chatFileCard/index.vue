<template>
  <a v-if="file" class="chat-file-card" :href="file.url" target="_blank" rel="noopener" :download="file.name">
    <span class="cfc-icon" :style="{ background: color }">{{ badge }}</span>
    <span class="cfc-body">
      <span class="cfc-name" :title="file.name">{{ file.name }}</span>
      <span class="cfc-meta">{{ sizeText }}<template v-if="isImage"> · 图片</template></span>
    </span>
    <span class="cfc-download">下载</span>
  </a>
</template>

<script>
import { decodeFileMsg, humanSize, fileColor, fileGroup } from '@/libs/chatFile'

const BADGE = {
  pdf: 'PDF', word: 'DOC', excel: 'XLS', ppt: 'PPT', zip: 'ZIP', image: 'IMG', file: 'FILE'
}

export default {
  name: 'chatFileCard',
  props: {
    // 编码后的 msn（base64 JSON）
    msn: {
      type: String,
      default: ''
    }
  },
  computed: {
    file() {
      return decodeFileMsg(this.msn)
    },
    color() {
      return this.file ? fileColor(this.file.ext) : '#8a95a6'
    },
    badge() {
      return this.file ? (BADGE[fileGroup(this.file.ext)] || 'FILE') : 'FILE'
    },
    sizeText() {
      return this.file ? humanSize(this.file.size) : ''
    },
    isImage() {
      return this.file && fileGroup(this.file.ext) === 'image'
    }
  }
}
</script>

<style lang="less" scoped>
.chat-file-card {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  width: 248px;
  max-width: 100%;
  padding: 10px 12px;
  border: 1px solid #eaedf3;
  border-radius: 10px;
  background: #fff;
  color: #1f2d3d;
  box-sizing: border-box;
}
.chat-file-card:hover {
  border-color: #c9d3e5;
}
.cfc-icon {
  flex: none;
  width: 38px;
  height: 38px;
  border-radius: 8px;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .3px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.cfc-body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.cfc-name {
  font-size: 13px;
  line-height: 1.35;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.cfc-meta {
  font-size: 11px;
  color: #97a1b2;
}
.cfc-download {
  flex: none;
  font-size: 12px;
  color: #335cff;
}
</style>
