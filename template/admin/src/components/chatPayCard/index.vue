<template>
  <div class="chat-pay-card" v-if="card">
    <div class="cpc-head">
      <span class="cpc-tag">续费账单</span>
      <span class="cpc-expire" :class="{ 'cpc-expire-over': expired }">{{ expireText }}</span>
    </div>
    <div class="cpc-body">
      <div class="cpc-subject">{{ card.subject }}</div>
      <div class="cpc-amount"><span class="cpc-currency">￥</span>{{ card.amount }}</div>
      <div class="cpc-no">单号 {{ card.pay_no }}</div>
    </div>
    <a v-if="safeUrl" class="cpc-action" :class="{ 'cpc-action-plain': readonly || expired }" :style="actionStyle"
       :href="safeUrl" target="_blank" rel="noopener noreferrer">{{ actionText }}</a>
  </div>
</template>

<script>
// 付款人看到的是收银台，收银台会读库给出最新状态；卡片本身只是入口，不在这里判断是否已付
const URL_PATTERN = /^https?:\/\//i

const pad = n => (n < 10 ? '0' + n : String(n))

export default {
  name: 'chatPayCard',
  props: {
    // 编码后的 msn（base64 JSON：pay_no/subject/amount/expire_at/url）
    msn: {
      type: String,
      default: ''
    },
    // 客服侧与历史回看：只查看，不引导去付款
    readonly: {
      type: Boolean,
      default: false
    },
    // 主题色，跟随客户端装修
    themeColor: {
      type: String,
      default: '#2d8cf0'
    }
  },
  computed: {
    card () {
      if (!this.msn) return null
      try {
        // 与其他卡片同一套路：base64(JSON)，escape/unescape 还原中文
        const obj = JSON.parse(decodeURIComponent(escape(atob(String(this.msn)))))
        return obj && obj.pay_no ? obj : null
      } catch (e) {
        // 正文损坏时不展示，不该让一条脏消息把整个聊天窗口打崩
        console.error('[chatPayCard] 支付卡片解析失败', e)
        return null
      }
    },
    // 卡片只由服务端插入，仍只放行 http(s)，防止被塞进 javascript: 之类的地址
    safeUrl () {
      return this.card && URL_PATTERN.test(this.card.url || '') ? this.card.url : ''
    },
    expired () {
      return !!(this.card && this.card.expire_at && this.card.expire_at * 1000 <= Date.now())
    },
    expireText () {
      if (!this.card || !this.card.expire_at) return ''
      if (this.expired) return '已过期'
      const d = new Date(this.card.expire_at * 1000)
      return `${d.getMonth() + 1}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())} 前有效`
    },
    actionText () {
      if (this.readonly || this.expired) return '查看账单'
      return '去支付'
    },
    actionStyle () {
      return this.readonly || this.expired ? {} : { background: this.themeColor, borderColor: this.themeColor }
    }
  }
}
</script>

<style lang="less" scoped>
/* 走聊天主题变量，随主题色/密度/边框一起变 */
.chat-pay-card {
  width: 240px;
  max-width: 100%;
  background: var(--chat-incoming, #fff);
  border: 1px solid var(--chat-border, #e5ebf5);
  border-radius: inherit;
  overflow: hidden;
  color: var(--chat-text, #172033);
}
.cpc-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: ~"calc(10px * var(--chat-density, 1))" var(--chat-bubble-pad-x, 14px) 0;
  font-size: 12px;
}
.cpc-tag {
  color: var(--chat-muted, #7f8ba5);
  letter-spacing: .3px;
}
.cpc-expire {
  color: var(--chat-muted, #7f8ba5);
}
.cpc-expire-over {
  color: #ed4014;
}
.cpc-body {
  padding: 6px var(--chat-bubble-pad-x, 14px) ~"calc(12px * var(--chat-density, 1))";
}
.cpc-subject {
  font-size: 13px;
  line-height: 1.5;
  word-break: break-all;
}
.cpc-amount {
  margin-top: 4px;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
}
.cpc-currency {
  font-size: 14px;
  margin-right: 1px;
}
.cpc-no {
  margin-top: 2px;
  color: var(--chat-muted, #7f8ba5);
  font-size: 12px;
  word-break: break-all;
}
.cpc-action {
  display: block;
  padding: ~"calc(9px * var(--chat-density, 1))" 0;
  border-top: 1px solid transparent;
  color: #fff;
  font-size: 14px;
  text-align: center;
  -webkit-tap-highlight-color: transparent;
}
.cpc-action:hover {
  color: #fff;
  opacity: .9;
}
.cpc-action-plain {
  border-top-color: var(--chat-border, #e5ebf5);
  color: var(--chat-primary, #2d8cf0);
}
.cpc-action-plain:hover {
  color: var(--chat-primary, #2d8cf0);
  background: var(--chat-page-bg, #f3f6fb);
}
</style>
