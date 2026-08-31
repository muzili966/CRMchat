<template>
  <div>
    <div v-if="loading" class="plan-gate-loading">
      <Spin size="large" fix></Spin>
    </div>
    <slot v-else-if="allowed"></slot>
    <div v-else class="plan-gate">
      <div class="plan-gate-icon">
        <Icon type="md-lock" size="34"/>
      </div>
      <p class="plan-gate-title">{{ featureText.name }}需要{{ requiredPlan }}及以上</p>
      <p class="plan-gate-desc">{{ featureText.desc }}</p>
      <p class="plan-gate-current" v-if="planName">当前订阅：{{ planName }}</p>
      <Button type="primary" @click="goSubscription">查看套餐</Button>
    </div>
  </div>
</template>

<script>
  import { planFeatureApi } from '@/api/tenant'
  import { getPlanFeatureText } from '@/config/planFeatures'

  // 同一次会话内多个页面共用一次请求，避免每进一个页面都打一次接口
  let gateCache = null

  export default {
    name: 'planGate',
    props: {
      // eb_tenant_plan 上的能力字段名，如 brand_custom
      feature: { type: String, required: true }
    },
    data () {
      return {
        loading: true,
        gate: { features: {}, upgrade: {}, plan_name: '', unlimited: false }
      }
    },
    computed: {
      featureText () {
        return getPlanFeatureText(this.feature)
      },
      allowed () {
        // 平台视角不受套餐约束；取不到能力表时放行，与后端 hasFeature 的宽松口径一致，
        // 不能因为一次接口失败就把已付费的功能挡在外面
        if (this.gate.unlimited) return true
        const features = this.gate.features || {}
        return features[this.feature] === undefined ? true : !!features[this.feature]
      },
      requiredPlan () {
        return (this.gate.upgrade || {})[this.feature] || '更高版本'
      },
      planName () {
        return this.gate.plan_name || ''
      }
    },
    created () {
      this.loadGate()
    },
    methods: {
      loadGate () {
        if (!gateCache) {
          gateCache = planFeatureApi().then(res => res.data).catch(() => ({}))
        }
        gateCache.then(data => {
          this.gate = data || {}
          this.loading = false
        })
      },
      goSubscription () {
        this.$router.push({ path: '/admin/tenant/subscription' })
      }
    }
  }
</script>

<style scoped>
  .plan-gate-loading {
    position: relative;
    min-height: 220px;
  }

  .plan-gate {
    padding: 64px 24px;
    text-align: center;
    background: #fff;
    border-radius: 4px;
  }

  .plan-gate-icon {
    width: 68px;
    height: 68px;
    margin: 0 auto 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #f2f5fa;
    color: #9aa6bd;
  }

  .plan-gate-title {
    font-size: 17px;
    font-weight: 600;
    color: #17233d;
  }

  .plan-gate-desc {
    max-width: 460px;
    margin: 10px auto 0;
    font-size: 13px;
    line-height: 1.7;
    color: #808695;
  }

  .plan-gate-current {
    margin: 14px 0 20px;
    font-size: 13px;
    color: #808695;
  }
</style>
