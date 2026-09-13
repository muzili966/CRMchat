<template>
  <div class="cashier">
    <div class="cashier-card">
      <div v-if="loading" class="cashier-state">加载中…</div>
      <div v-else-if="error" class="cashier-state cashier-state-error">
        <Icon type="ios-alert-outline" size="44"/>
        <p>{{ error }}</p>
      </div>
      <template v-else>
        <div class="cashier-head">
          <div class="cashier-subject">{{ info.subject }}</div>
          <div class="cashier-payer" v-if="info.payer">付款方：{{ info.payer }}</div>
          <div class="cashier-amount"><span class="cashier-currency">￥</span>{{ info.amount }}</div>
          <div class="cashier-no">单号 {{ info.pay_no }}</div>
        </div>

        <div v-if="info.status === STATUS.PAID" class="cashier-result cashier-result-ok">
          <Icon type="ios-checkmark-circle" size="52"/>
          <p class="cashier-result-title">支付成功</p>
          <p class="cashier-result-tip">{{ info.fulfilled ? '套餐已开通，回到后台刷新即可看到' : '正在开通，稍后回到后台刷新即可看到' }}</p>
        </div>

        <div v-else-if="closedText" class="cashier-result">
          <Icon type="ios-time-outline" size="52"/>
          <p class="cashier-result-title">{{ closedText }}</p>
          <p class="cashier-result-tip">请联系平台客服重新获取支付链接</p>
        </div>

        <div v-else class="cashier-main">
          <p class="cashier-deadline">请在 {{ info._expire_time }} 前完成支付</p>

          <div v-if="result" class="cashier-pay">
            <template v-if="result.type === TYPE.QRCODE">
              <div class="cashier-qr" ref="qr"></div>
              <p class="cashier-tip">请使用{{ activeChannel.name }}扫码支付，付款后本页自动更新</p>
            </template>
            <template v-else-if="result.type === TYPE.MANUAL">
              <p class="cashier-tip cashier-tip-left">{{ result.content }}</p>
              <img v-for="src in manualQrcodes" :key="src" :src="src" class="cashier-manual-qr" alt="收款码">
              <p v-if="result.data && result.data.account" class="cashier-account">{{ result.data.account }}</p>
            </template>
            <a class="cashier-switch" @click="result = null">更换支付方式</a>
          </div>

          <template v-else>
            <p v-if="!info.channels.length" class="cashier-tip">暂无可用的支付方式，请联系平台客服</p>
            <div v-for="item in info.channels" :key="item.code" class="cashier-channel"
                 :class="{ 'cashier-channel-on': channel === item.code }" @click="channel = item.code">
              <span>{{ item.name }}</span>
              <Icon :type="channel === item.code ? 'ios-radio-button-on' : 'ios-radio-button-off'" size="20"/>
            </div>
            <Button v-if="info.channels.length" type="primary" long size="large" class="cashier-submit"
                    :loading="paying" :disabled="!channel" @click="pay">立即支付</Button>
          </template>
        </div>
      </template>
    </div>
  </div>
</template>

<script>
import QRCode from 'qrcodejs2'
import { cashierInfoApi, cashierPayApi, cashierStatusApi } from '@/api/pay'

// 与后端 PaymentStatus 一致
const STATUS = { PENDING: 0, PAID: 1, CLOSED: 2, REFUNDED: 3 }
// 与后端 PaymentResult 的类型一致
const TYPE = { QRCODE: 'qrcode', REDIRECT: 'redirect', FORM: 'form', MANUAL: 'manual' }
const QR_SIZE = 200
const POLL_INTERVAL = 3000
// 轮询只为等到账结果，停留半小时多半已离开，不再空转
const POLL_MAX_TIMES = 600

export default {
  name: 'payCashier',
  data () {
    return {
      STATUS,
      TYPE,
      loading: true,
      error: '',
      info: { channels: [] },
      channel: '',
      paying: false,
      result: null,
      timer: null,
      polled: 0
    }
  },
  computed: {
    credential () {
      return { no: this.$route.query.no || '', sign: this.$route.query.sign || '' }
    },
    activeChannel () {
      return this.info.channels.find(item => item.code === this.channel) || {}
    },
    closedText () {
      if (this.info.status === STATUS.REFUNDED) return '该账单已退款'
      if (this.info.status === STATUS.CLOSED) return '该账单已关闭'
      return this.info.expired ? '该账单已过期' : ''
    },
    manualQrcodes () {
      return (this.result && this.result.data && this.result.data.qrcodes) || []
    }
  },
  created () {
    document.title = '支付账单'
    this.load()
  },
  beforeDestroy () {
    this.stopPoll()
  },
  methods: {
    load () {
      cashierInfoApi(this.credential).then(res => {
        this.info = res.data
        this.channel = (res.data.channels[0] || {}).code || ''
        this.loading = false
        // 从渠道网页收银台跳回时可能已付而回调未到，先轮询等结果
        if (this.isPending()) this.startPoll()
      }).catch(res => {
        this.loading = false
        this.error = (res && res.msg) || '支付链接无效'
      })
    },
    isPending () {
      return this.info.status === STATUS.PENDING && !this.info.expired
    },
    pay () {
      this.paying = true
      cashierPayApi({ ...this.credential, channel: this.channel }).then(res => {
        this.paying = false
        this.handleResult(res.data)
      }).catch(res => {
        this.paying = false
        this.$Message.error((res && res.msg) || '下单失败，请稍后重试')
      })
    },
    handleResult (result) {
      if (result.type === TYPE.REDIRECT) {
        window.location.href = result.content
        return
      }
      if (result.type === TYPE.FORM) {
        this.submitForm(result.content)
        return
      }
      this.result = result
      if (result.type === TYPE.QRCODE) {
        this.$nextTick(() => this.renderQr(result.content))
      }
      this.startPoll()
    },
    renderQr (text) {
      if (!this.$refs.qr) return
      this.$refs.qr.innerHTML = ''
      new QRCode(this.$refs.qr, { text, width: QR_SIZE, height: QR_SIZE, correctLevel: QRCode.CorrectLevel.M })
    },
    // 渠道给的是自动提交表单：挂到页面上提交，跳转到渠道收银台
    submitForm (html) {
      const box = document.createElement('div')
      box.style.display = 'none'
      box.innerHTML = html
      document.body.appendChild(box)
      const form = box.querySelector('form')
      if (!form) {
        this.$Message.error('支付表单无效，请更换支付方式')
        return
      }
      form.submit()
    },
    startPoll () {
      this.stopPoll()
      this.polled = 0
      this.timer = setInterval(this.poll, POLL_INTERVAL)
    },
    stopPoll () {
      if (this.timer) {
        clearInterval(this.timer)
        this.timer = null
      }
    },
    poll () {
      this.polled += 1
      if (this.polled > POLL_MAX_TIMES) {
        this.stopPoll()
        return
      }
      cashierStatusApi(this.credential).then(res => {
        this.info = { ...this.info, ...res.data }
        if (!this.isPending()) {
          this.stopPoll()
          this.result = null
        }
      }).catch(res => {
        // 偶发的网络失败不打断轮询，下一轮再查
        console.warn('[cashier] 查询支付状态失败', res)
      })
    }
  }
}
</script>

<style lang="less" scoped>
.cashier {
  min-height: 100vh;
  padding: 40px 16px;
  background: #f3f5f9;
  box-sizing: border-box;
}
.cashier-card {
  max-width: 420px;
  margin: 0 auto;
  padding: 28px 24px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 24px rgba(23, 32, 51, .06);
}
.cashier-state {
  padding: 40px 0;
  color: #7f8ba5;
  text-align: center;
  p {
    margin-top: 12px;
    font-size: 15px;
  }
}
.cashier-state-error {
  color: #ed4014;
}
.cashier-head {
  padding-bottom: 20px;
  border-bottom: 1px dashed #e5ebf5;
  text-align: center;
}
.cashier-subject {
  color: #172033;
  font-size: 15px;
}
.cashier-payer {
  margin-top: 4px;
  color: #7f8ba5;
  font-size: 13px;
}
.cashier-amount {
  margin-top: 12px;
  color: #172033;
  font-size: 34px;
  font-weight: 600;
  line-height: 1.2;
}
.cashier-currency {
  font-size: 20px;
}
.cashier-no {
  margin-top: 6px;
  color: #a3aab8;
  font-size: 12px;
  word-break: break-all;
}
.cashier-deadline {
  margin: 16px 0 12px;
  color: #7f8ba5;
  font-size: 13px;
  text-align: center;
}
.cashier-channel {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
  padding: 14px 16px;
  border: 1px solid #e5ebf5;
  border-radius: 8px;
  color: #172033;
  font-size: 15px;
  cursor: pointer;
  transition: border-color .15s;
}
.cashier-channel-on {
  border-color: #2d8cf0;
  color: #2d8cf0;
}
.cashier-submit {
  margin-top: 8px;
}
.cashier-pay {
  text-align: center;
}
.cashier-qr {
  display: inline-block;
  padding: 10px;
  border: 1px solid #e5ebf5;
  border-radius: 8px;
}
.cashier-tip {
  margin-top: 12px;
  color: #515a6e;
  font-size: 13px;
  line-height: 1.6;
  text-align: center;
}
.cashier-tip-left {
  text-align: left;
}
.cashier-manual-qr {
  display: block;
  width: 200px;
  margin: 12px auto 0;
}
.cashier-account {
  margin-top: 12px;
  padding: 10px 12px;
  background: #f7f9fc;
  border-radius: 6px;
  color: #172033;
  font-size: 13px;
  text-align: left;
  white-space: pre-line;
  word-break: break-all;
}
.cashier-switch {
  display: inline-block;
  margin-top: 16px;
  font-size: 13px;
}
.cashier-result {
  padding: 28px 0 8px;
  color: #a3aab8;
  text-align: center;
}
.cashier-result-ok {
  color: #19be6b;
}
.cashier-result-title {
  margin-top: 10px;
  color: #172033;
  font-size: 17px;
  font-weight: 600;
}
.cashier-result-tip {
  margin-top: 6px;
  color: #7f8ba5;
  font-size: 13px;
}
</style>
