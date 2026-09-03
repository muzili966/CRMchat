<template>
  <div v-if="value.visible" class="visitor-account-mask" @click.self="close">
    <div class="visitor-account">
      <div class="va-header">
        <span>{{ isBind ? '绑定手机号' : '继续上次会话' }}</span>
        <i class="va-close" @click="close">&#215;</i>
      </div>
      <p class="va-tip">{{ isBind ? '绑定后换设备也能接续这次会话' : '输入绑定的手机号，继续之前的对话' }}</p>

      <div class="va-field">
        <input v-model.trim="phone" type="tel" maxlength="11" placeholder="请输入手机号" />
      </div>

      <!-- 登录时可选密码或验证码；绑定时先验手机号，密码选填 -->
      <div v-if="!isBind" class="va-tabs">
        <span :class="{ on: loginBy === 'password' }" @click="loginBy = 'password'">密码登录</span>
        <span :class="{ on: loginBy === 'code' }" @click="loginBy = 'code'">验证码登录</span>
      </div>

      <div v-if="isBind || loginBy === 'code'" class="va-field va-field--code">
        <input v-model.trim="code" type="text" maxlength="6" placeholder="短信验证码" />
        <button type="button" class="va-code-btn" :disabled="counting > 0" @click="sendCode">
          {{ counting > 0 ? counting + 's' : '获取验证码' }}
        </button>
      </div>

      <div v-if="isBind || loginBy === 'password'" class="va-field">
        <input v-model="password" type="password" maxlength="32"
               :placeholder="isBind ? '设置密码（选填，6-32位）' : '请输入密码'" />
      </div>

      <p v-if="error" class="va-error">{{ error }}</p>

      <button type="button" class="va-submit" :disabled="loading" @click="submit">
        {{ loading ? '处理中…' : (isBind ? '确定绑定' : '登录') }}
      </button>

      <div class="va-switch">
        <template v-if="isBind">已有账号？<a @click="switchMode('login')">直接登录</a></template>
        <template v-else>还没绑定？<a @click="switchMode('bind')">绑定手机号</a></template>
      </div>
    </div>
  </div>
</template>

<script>
import { visitorSendCode, visitorBind, visitorLogin } from '@/api/kefu'
import { getLoc } from '@/libs/util'

const COUNTDOWN = 60

export default {
  name: 'visitorAccount',
  props: {
    // { visible, mode: 'bind' | 'login' }
    value: {
      type: Object,
      default: () => ({ visible: false, mode: 'login' })
    }
  },
  data() {
    return {
      phone: '',
      code: '',
      password: '',
      loginBy: 'password',
      counting: 0,
      timer: null,
      loading: false,
      error: ''
    }
  },
  computed: {
    isBind() {
      return this.value.mode === 'bind'
    }
  },
  watch: {
    'value.visible'(v) {
      if (v) { this.reset() }
    }
  },
  beforeDestroy() {
    this.clearTimer()
  },
  methods: {
    reset() {
      this.code = ''
      this.password = ''
      this.error = ''
      this.loginBy = 'password'
    },
    close() {
      this.$emit('input', { ...this.value, visible: false })
    },
    switchMode(mode) {
      this.$emit('input', { visible: true, mode })
    },
    validPhone() {
      return /^1[3-9]\d{9}$/.test(this.phone)
    },
    sendCode() {
      this.error = ''
      if (!this.validPhone()) { this.error = '请输入正确的手机号'; return }
      visitorSendCode({ phone: this.phone }).then(res => {
        this.startCountdown()
        //开发桩会把验证码回给前端，仅本地联调可见
        if (res.data && res.data.debug_code) { this.code = res.data.debug_code }
      }).catch(err => { this.error = err.msg || '发送失败' })
    },
    startCountdown() {
      this.counting = COUNTDOWN
      this.clearTimer()
      this.timer = setInterval(() => {
        this.counting -= 1
        if (this.counting <= 0) { this.clearTimer() }
      }, 1000)
    },
    clearTimer() {
      if (this.timer) { clearInterval(this.timer); this.timer = null }
    },
    submit() {
      this.error = ''
      if (!this.validPhone()) { this.error = '请输入正确的手机号'; return }
      this.isBind ? this.doBind() : this.doLogin()
    },
    doBind() {
      if (!this.code) { this.error = '请输入验证码'; return }
      this.request(visitorBind({
        phone: this.phone,
        code: this.code,
        uid: getLoc('uid') || 0,
        password: this.password
      }))
    },
    doLogin() {
      if (this.loginBy === 'code') {
        if (!this.code) { this.error = '请输入验证码'; return }
        this.request(visitorBind({ phone: this.phone, code: this.code, uid: getLoc('uid') || 0 }))
        return
      }
      if (!this.password) { this.error = '请输入密码'; return }
      this.request(visitorLogin({ phone: this.phone, password: this.password }))
    },
    request(promise) {
      this.loading = true
      promise.then(res => {
        this.loading = false
        this.$emit('success', res.data || {})
      }).catch(err => {
        this.loading = false
        this.error = err.msg || '操作失败'
      })
    }
  }
}
</script>

<style lang="less" scoped>
.visitor-account-mask {
  //访客窗口本身就是一张 iframe，fixed 即铺满整窗，不依赖祖先定位
  position: fixed;
  inset: 0;
  z-index: 20;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(23, 32, 51, .42);
}
.visitor-account {
  width: 300px;
  max-width: 86%;
  padding: 22px 22px 18px;
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 18px 48px rgba(23, 32, 51, .22);
}
.va-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 16px;
  font-weight: 600;
  color: #1f2d3d;
}
.va-close {
  cursor: pointer;
  font-size: 22px;
  line-height: 1;
  color: #b3bccb;
  font-style: normal;
}
.va-tip {
  margin: 8px 0 16px;
  font-size: 12px;
  color: #8a95a6;
}
.va-field {
  margin-bottom: 12px;
}
.va-field input {
  width: 100%;
  height: 40px;
  padding: 0 12px;
  border: 1px solid #e4e8f0;
  border-radius: 9px;
  font-size: 14px;
  outline: none;
  transition: border-color .2s;
}
.va-field input:focus {
  border-color: #335cff;
}
.va-field--code {
  display: flex;
  gap: 8px;
}
.va-field--code input {
  flex: 1;
}
.va-code-btn {
  flex: none;
  width: 104px;
  border: 1px solid #335cff;
  border-radius: 9px;
  background: #fff;
  color: #335cff;
  font-size: 13px;
  cursor: pointer;
}
.va-code-btn:disabled {
  border-color: #d6dbe6;
  color: #aab3c2;
  cursor: default;
}
.va-tabs {
  display: flex;
  gap: 18px;
  margin-bottom: 12px;
  font-size: 13px;
}
.va-tabs span {
  cursor: pointer;
  color: #8a95a6;
  padding-bottom: 3px;
  border-bottom: 2px solid transparent;
}
.va-tabs span.on {
  color: #335cff;
  border-color: #335cff;
}
.va-error {
  margin: -4px 0 10px;
  font-size: 12px;
  color: #e5484d;
}
.va-submit {
  width: 100%;
  height: 42px;
  border: none;
  border-radius: 9px;
  background: #335cff;
  color: #fff;
  font-size: 15px;
  cursor: pointer;
}
.va-submit:disabled {
  background: #a9bbff;
  cursor: default;
}
.va-switch {
  margin-top: 12px;
  text-align: center;
  font-size: 12px;
  color: #8a95a6;
}
.va-switch a {
  color: #335cff;
  cursor: pointer;
}
</style>
