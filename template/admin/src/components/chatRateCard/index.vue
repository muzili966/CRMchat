<template>
  <div class="chat-rate-card">
    <div class="crc-title">{{ done ? '感谢您的评价' : '本次服务您还满意吗？' }}</div>

    <div class="crc-stars">
      <span v-for="n in 5" :key="n" class="crc-star" :class="{ 'crc-star-on': n <= current, 'crc-star-lock': done }"
            @click="pick(n)" @mouseenter="hover(n)" @mouseleave="hover(0)">★</span>
    </div>
    <div class="crc-label">{{ label }}</div>

    <template v-if="!done">
      <textarea v-if="score" v-model="remark" class="crc-remark" rows="2" maxlength="200"
                placeholder="说说哪里做得好或需要改进（选填）"></textarea>
      <button class="crc-submit" :disabled="!score || submitting" :style="submitStyle" @click="submit">
        {{ submitting ? '提交中…' : '提交评价' }}
      </button>
    </template>
    <div v-else-if="doneRemark" class="crc-done-remark">“{{ doneRemark }}”</div>
  </div>
</template>

<script>
const LABELS = ['', '很不满意', '不满意', '一般', '满意', '非常满意']

export default {
  name: 'chatRateCard',
  props: {
    // 编码后的 msn（base64 JSON，含 session_id）
    msn: {
      type: String,
      default: ''
    },
    // 已提交的分数，由外部按会话状态回传；非0即锁定
    rated: {
      type: Number,
      default: 0
    },
    ratedRemark: {
      type: String,
      default: ''
    },
    // 主题色，跟随客户端装修
    themeColor: {
      type: String,
      default: '#2d8cf0'
    }
  },
  data () {
    return {
      score: 0,
      hovering: 0,
      remark: '',
      submitting: false,
      submitted: 0,
      submittedRemark: ''
    }
  },
  computed: {
    sessionId () {
      try {
        const json = decodeURIComponent(escape(window.atob(this.msn || '')))
        const data = JSON.parse(json)
        return Number(data.session_id) || 0
      } catch (e) {
        // 正文解不开时卡片仍要能显示，只是提交不了
        return 0
      }
    },
    done () {
      return !!(this.submitted || this.rated)
    },
    doneRemark () {
      return this.submittedRemark || this.ratedRemark
    },
    // 已评价时展示最终分数，未评价时跟随鼠标预览
    current () {
      if (this.done) return this.submitted || this.rated
      return this.hovering || this.score
    },
    label () {
      return LABELS[this.current] || '点击星星评分'
    },
    submitStyle () {
      return this.score ? { background: this.themeColor } : {}
    }
  },
  methods: {
    pick (n) {
      if (this.done) return
      this.score = n
    },
    hover (n) {
      if (this.done) return
      this.hovering = n
    },
    submit () {
      if (!this.score || this.submitting) return
      if (!this.sessionId) {
        this.$emit('error', '评价信息已失效，请刷新后重试')
        return
      }
      this.submitting = true
      this.$emit('submit', {
        session_id: this.sessionId,
        rate: this.score,
        remark: this.remark,
        // 提交结果由父组件回调，卡片自身不接触通信层
        done: ok => {
          this.submitting = false
          if (ok) {
            this.submitted = this.score
            this.submittedRemark = this.remark
          }
        }
      })
    }
  }
}
</script>

<style lang="less" scoped>
.chat-rate-card {
  width: 260px;
  max-width: 100%;
  padding: 14px 16px;
  border: 1px solid #eaedf3;
  border-radius: 10px;
  background: #fff;
  box-sizing: border-box;
  text-align: center;
}
.crc-title {
  font-size: 14px;
  color: #1f2d3d;
  margin-bottom: 10px;
}
.crc-stars {
  display: flex;
  justify-content: center;
  gap: 6px;
}
.crc-star {
  font-size: 26px;
  line-height: 1;
  color: #dcdee2;
  cursor: pointer;
  transition: color .15s, transform .15s;
}
.crc-star:hover {
  transform: scale(1.12);
}
.crc-star-on {
  color: #ff9900;
}
.crc-star-lock {
  cursor: default;
}
.crc-star-lock:hover {
  transform: none;
}
.crc-label {
  font-size: 12px;
  color: #a3aab8;
  margin: 8px 0;
  min-height: 17px;
}
.crc-remark {
  width: 100%;
  border: 1px solid #eaedf3;
  border-radius: 6px;
  padding: 6px 8px;
  font-size: 13px;
  color: #1f2d3d;
  resize: none;
  outline: none;
  box-sizing: border-box;
  font-family: inherit;
}
.crc-remark:focus {
  border-color: #c5c8ce;
}
.crc-submit {
  width: 100%;
  margin-top: 10px;
  padding: 7px 0;
  border: none;
  border-radius: 6px;
  background: #c5c8ce;
  color: #fff;
  font-size: 13px;
  cursor: pointer;
}
.crc-submit:disabled {
  cursor: not-allowed;
  opacity: .7;
}
.crc-done-remark {
  font-size: 13px;
  color: #808695;
  margin-top: 4px;
  word-break: break-all;
}
</style>
