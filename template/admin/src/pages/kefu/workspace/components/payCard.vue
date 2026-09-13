<template>
  <Modal :value="value" title="发送续费卡片" width="440" :mask-closable="false" @on-visible-change="onVisibleChange">
    <div class="pay-card">
      <div class="pay-card-row"><span class="pay-card-label">租户</span><span>{{ target.tenant_name }}</span></div>
      <div class="pay-card-row"><span class="pay-card-label">当前到期</span><span>{{ target._expire_time }}</span></div>
      <Form :label-width="70" class="pay-card-form" @submit.native.prevent>
        <FormItem label="套餐">
          <Select v-model="form.plan_id" placeholder="请选择套餐">
            <Option v-for="item in plans" :value="item.id" :key="item.id">{{ item.name }}（￥{{ item.price }}/月）</Option>
          </Select>
        </FormItem>
        <FormItem label="月数">
          <InputNumber v-model="form.months" :min="MONTHS_MIN" :max="MONTHS_MAX" :precision="0"/>
        </FormItem>
        <FormItem label="应付">
          <span class="pay-card-amount">￥{{ amount }}</span>
        </FormItem>
        <FormItem label="备注">
          <Input v-model="form.remark" :maxlength="REMARK_MAX" placeholder="选填，仅平台可见"/>
        </FormItem>
      </Form>
      <p class="pay-card-tip">对方点开卡片自选支付方式，到账后自动开通，无需再截图核对</p>
    </div>
    <div slot="footer">
      <Button @click="close">取消</Button>
      <Button type="primary" :loading="sending" :disabled="!form.plan_id" @click="send">发送</Button>
    </div>
  </Modal>
</template>

<script>
import { sendPayCardApi } from '@/api/kefu'

// 与后端 TenantPlanPayable 的月数范围一致
const MONTHS_MIN = 1
const MONTHS_MAX = 36
const REMARK_MAX = 255

export default {
  name: 'payCard',
  props: {
    value: {
      type: Boolean,
      default: false
    },
    // 访客对应的租户与可选套餐，由工作台切换会话时拉取
    target: {
      type: Object,
      default: () => ({ tenant_id: 0, plans: [] })
    },
    userId: {
      type: [Number, String],
      default: 0
    }
  },
  data () {
    return {
      MONTHS_MIN,
      MONTHS_MAX,
      REMARK_MAX,
      sending: false,
      form: { plan_id: '', months: MONTHS_MIN, remark: '' }
    }
  },
  computed: {
    plans () {
      return this.target.plans || []
    },
    amount () {
      const plan = this.plans.find(item => item.id === this.form.plan_id)
      return plan ? (Number(plan.price) * (this.form.months || 0)).toFixed(2) : '0.00'
    }
  },
  methods: {
    onVisibleChange (visible) {
      if (visible) {
        this.resetForm()
        return
      }
      this.$emit('input', false)
    },
    // 默认选租户当前套餐：绝大多数是原样续费
    resetForm () {
      const current = this.plans.find(item => item.id === this.target.plan_id)
      const first = this.plans[0]
      this.form = { plan_id: (current || first || {}).id || '', months: MONTHS_MIN, remark: '' }
    },
    close () {
      this.$emit('input', false)
    },
    send () {
      this.sending = true
      sendPayCardApi({ user_id: this.userId, ...this.form }).then(res => {
        this.sending = false
        this.$emit('sent', res.data)
        this.close()
      }).catch(res => {
        this.sending = false
        this.$Message.error(res.msg)
      })
    }
  }
}
</script>

<style lang="less" scoped>
.pay-card-row {
  display: flex;
  margin-bottom: 8px;
  color: #172033;
  font-size: 13px;
}
.pay-card-label {
  width: 70px;
  padding-right: 12px;
  color: #7f8ba5;
  text-align: right;
}
.pay-card-form {
  margin-top: 12px;
}
.pay-card-amount {
  color: #172033;
  font-size: 18px;
  font-weight: 600;
}
.pay-card-tip {
  color: #a3aab8;
  font-size: 12px;
}
</style>
