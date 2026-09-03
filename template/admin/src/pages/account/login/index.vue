<template>
  <div class="login-page">
    <section class="visual-panel">
      <component :is="websiteUrl ? 'a' : 'header'" class="visual-panel__header"
                 :class="{ 'visual-panel__header--link': websiteUrl }"
                 :href="websiteUrl || null" :target="websiteUrl ? '_blank' : null" rel="noopener">
        <img :src="brandIcon" alt="QiaLink 洽联图标">
        <span><strong>QiaLink</strong><small>洽联</small></span>
      </component>
      <div class="visual-panel__art" aria-hidden="true">
        <img :src="loginIllustration" alt="">
      </div>
      <div class="visual-panel__copy">
        <h1>连接每一次沟通</h1>
        <p>让智能服务更高效，让客户关系更长久</p>
      </div>
    </section>

    <section class="form-panel">
      <div class="form-panel__top">
        <span>智能客户联络平台</span>
        <span class="top-right">
          <a v-if="websiteUrl" class="website-link" :href="websiteUrl" target="_blank" rel="noopener">
            官网首页<Icon type="ios-arrow-forward" />
          </a>
          <span class="online"><i></i> 服务在线</span>
        </span>
      </div>
      <div class="login-card">
        <img class="login-card__logo" :src="brandLogo" alt="QiaLink 洽联">
        <div class="login-card__heading">
          <h2>欢迎回来</h2>
          <p>输入您的账号和密码登录管理平台</p>
        </div>
        <Form ref="formInline" :model="formInline" :rules="ruleInline" @keyup.enter.native="handleSubmit('formInline')">
          <FormItem prop="username">
            <Input v-model="formInline.username" type="text" prefix="ios-person-outline" placeholder="请输入管理员账号" size="large" />
          </FormItem>
          <FormItem prop="password">
            <Input v-model="formInline.password" type="password" prefix="ios-lock-outline" placeholder="请输入登录密码" size="large" />
          </FormItem>
          <FormItem prop="code">
            <div class="verify-field">
              <Input v-model="formInline.code" type="text" prefix="ios-key-outline" placeholder="请输入验证码" size="large" />
              <button type="button" class="verify-image" aria-label="刷新验证码" @click="captchas">
                <img :src="imgcode" alt="验证码">
                <span v-if="isCaptchaExpired" class="verify-image__expired"><Icon type="ios-refresh" /> 已过期</span>
              </button>
            </div>
          </FormItem>
          <FormItem class="submit-item">
            <Button type="primary" long size="large" class="login-button" @click="handleSubmit('formInline')">登录</Button>
          </FormItem>
        </Form>
        <p class="login-card__hint"><Icon type="ios-lock-outline" /> 登录信息通过加密通道传输</p>
      </div>
      <footer class="form-panel__footer">
        QiaLink 洽联 · 智能客户联络平台
        <template v-if="websiteUrl"> · <a :href="websiteUrl" target="_blank" rel="noopener">了解产品</a></template>
      </footer>
    </section>

    <Modal v-model="modals" scrollable footer-hide closable title="请完成安全校验" :mask-closable="false" :z-index="2" width="342">
      <div class="captcha-box">
        <div id="captcha" ref="captcha"></div>
        <div id="msg"></div>
      </div>
    </Modal>
  </div>
</template>

<script>
import { AccountLogin, captcha_pro, loginInfoApi } from '@/api/account'
import { setCookies } from '@/libs/util'
import brandLogo from '@/assets/images/qialink-logo-horizontal.png'
import brandIcon from '@/assets/images/qialink-logo-icon.png'
import loginIllustration from '@/assets/images/qialink-login-illustration.png'
import '../../../assets/js/jigsaw.js'

const MOBILE_BREAKPOINT = 768
const SLIDER_THRESHOLD = 2
const DEFAULT_CAPTCHA_EXPIRES_IN = 1800

export default {
  data() {
    return {
      brandLogo,
      brandIcon,
      loginIllustration,
      modals: false,
      imgcode: '',
      isCaptchaExpired: false,
      captchaTimer: null,
      errorNum: 0,
      jigsaw: null,
      websiteUrl: '',
      formInline: { username: '', password: '', code: '', key: '' },
      ruleInline: {
        username: [{ required: true, message: '请输入用户名', trigger: 'blur' }],
        password: [{ required: true, message: '请输入密码', trigger: 'blur' }],
        code: [{ required: true, message: '请输入验证码', trigger: 'blur' }]
      }
    }
  },
  mounted() {
    this.loadSiteInfo()
    this.initJigsaw()
    this.captchas()
    this.updateCanvasClass()
    window.addEventListener('resize', this.updateCanvasClass)
  },
  beforeDestroy() {
    window.removeEventListener('resize', this.updateCanvasClass)
    window.clearTimeout(this.captchaTimer)
    this.removeCanvasClass()
  },
  methods: {
    initJigsaw() {
      this.$nextTick(() => {
        this.jigsaw = jigsaw.init({
          el: this.$refs.captcha,
          onSuccess: () => {
            this.modals = false
            this.login()
          },
          onFail: this.handleJigsawFail,
          onRefresh() {}
        })
      })
    },
    getCanvas() {
      return document.getElementsByTagName('canvas')[0]
    },
    updateCanvasClass() {
      const canvas = this.getCanvas()
      if(!canvas) return
      canvas.className = document.documentElement.clientWidth < MOBILE_BREAKPOINT ? '' : 'index_bg'
    },
    removeCanvasClass() {
      const canvas = this.getCanvas()
      if(canvas) canvas.removeAttribute('class')
    },
    resetJigsaw() {
      if(this.jigsaw) this.jigsaw.reset()
    },
    getExpiresTime(expiresTime) {
      const secondsPerDay = 60 * 60 * 24
      return parseInt((expiresTime - Math.round(new Date() / 1000)) / secondsPerDay)
    },
    saveLoginData(data) {
      const expires = this.getExpiresTime(data.expires_time)
      setCookies('uuid', data.user_info.id)
      setCookies('token', data.token, expires)
      setCookies('expires_time', data.expires_time, expires)
      this.$store.commit('userInfo/uniqueAuth', data.unique_auth)
      this.$store.commit('userInfo/userInfo', data.user_info)
      this.$store.commit('menus/setopenMenus', [])
      this.$store.commit('menus/getmenusNav', data.menus)
      this.$store.commit('userInfo/name', data.user_info.account)
      this.$store.commit('userInfo/avatar', data.user_info.head_pic)
      this.$store.commit('userInfo/access', data.unique_auth)
      this.$store.commit('userInfo/logo', data.logo)
      this.$store.commit('userInfo/logoSmall', data.logo_square)
      this.$store.commit('userInfo/version', data.version)
      this.$store.commit('userInfo/newOrderAudioLink', data.newOrderAudioLink)
    },
    loadSiteInfo() {
      //官网地址未配置时接口返回空串，入口就不出现，不必为此报错
      loginInfoApi().then(res => {
        this.websiteUrl = (res.data && res.data.website_url) || ''
      }).catch(() => {})
    },
    login() {
      const loading = this.$Message.loading({ content: '正在安全登录...', duration: 0 })
      AccountLogin({
        account: this.formInline.username,
        pwd: this.formInline.password,
        imgcode: this.formInline.code,
        key: this.formInline.key
      }).then(res => {
        loading()
        this.saveLoginData(res.data)
        this.resetJigsaw()
        return this.$router.replace({ path: '/admin/home/' })
      }).catch(error => {
        loading()
        this.errorNum++
        this.captchas()
        this.$Message.error((error && error.msg) || '登录失败')
        this.resetJigsaw()
      })
    },
    handleJigsawFail() {
      this.resetJigsaw()
      this.$Message.error('校验错误，请重试')
    },
    startCaptchaTimer(expiresIn) {
      window.clearTimeout(this.captchaTimer)
      const seconds = Number(expiresIn) || DEFAULT_CAPTCHA_EXPIRES_IN
      this.captchaTimer = window.setTimeout(() => {
        this.isCaptchaExpired = true
      }, seconds * 1000)
    },
    captchas() {
      this.isCaptchaExpired = false
      captcha_pro().then(res => {
        if(res.status !== 200) {
          this.isCaptchaExpired = true
          this.$Message.error(res.msg || '验证码加载失败')
          return
        }
        this.imgcode = res.data.img
        this.formInline.key = res.data.key
        this.startCaptchaTimer(res.data.expires_in)
      }).catch(error => {
        this.isCaptchaExpired = true
        this.$Message.error((error && error.msg) || '验证码加载失败')
      })
    },
    handleSubmit(name) {
      this.$refs[name].validate(valid => {
        if(!valid) return
        if(this.errorNum >= SLIDER_THRESHOLD) {
          this.modals = true
          return
        }
        this.login()
      })
    }
  }
}
</script>

<style scoped lang="stylus">
.login-page {
  min-height: 100vh;
  display: grid;
  grid-template-columns: minmax(420px, 1fr) minmax(520px, 1fr);
  overflow: hidden;
  color: #14213d;
  background: #fff;
  font-family: Inter, "PingFang SC", "Microsoft YaHei", sans-serif;
}

.login-page, .login-page * { box-sizing: border-box; }

.visual-panel {
  min-height: 100vh;
  padding: 38px 48px 54px;
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto;
  position: relative;
  color: #fff;
  background-color: #1677ff;
  background-image: linear-gradient(180deg, rgba(11, 111, 232, .02), rgba(11, 111, 232, .1)), url('../../../assets/images/qialink-login-background.png');
  background-repeat: no-repeat;
  background-position: center;
  background-size: cover;
}

.visual-panel::after {
  content: '';
  position: absolute;
  inset: 0;
  pointer-events: none;
  background: linear-gradient(180deg, rgba(22, 119, 255, .04) 35%, rgba(11, 91, 207, .34));
}

.visual-panel__header, .visual-panel__art, .visual-panel__copy { position: relative; z-index: 1; }
.visual-panel__header { display: flex; align-items: center; gap: 12px; }
.visual-panel__header--link { cursor: pointer; color: inherit; text-decoration: none; }
.visual-panel__header--link:hover, .visual-panel__header--link:focus { color: inherit; text-decoration: none; }
.visual-panel__header img { width: 44px; height: 44px; object-fit: contain; filter: brightness(0) invert(1) drop-shadow(0 0 8px rgba(255, 255, 255, .42)); }
.visual-panel__header strong, .visual-panel__header small { display: block; }
.visual-panel__header strong { font-size: 19px; line-height: 1.1; letter-spacing: .2px; }
.visual-panel__header small { margin-top: 3px; color: rgba(255, 255, 255, .76); font-size: 11px; letter-spacing: 4px; }
.visual-panel__art { min-height: 0; display: flex; align-items: center; justify-content: center; padding: 20px 0 12px; }
.visual-panel__art img { display: block; width: min(88%, 620px); max-height: 62vh; object-fit: contain; filter: drop-shadow(0 22px 32px rgba(0, 70, 177, .18)); }
.visual-panel__copy { text-align: center; text-shadow: 0 2px 12px rgba(0, 65, 165, .22); }
.visual-panel__copy h1 { margin: 0 0 10px; font-size: 28px; font-weight: 600; letter-spacing: 1px; }
.visual-panel__copy p { margin: 0; color: rgba(255, 255, 255, .78); font-size: 14px; letter-spacing: .5px; }

.form-panel {
  min-height: 100vh;
  padding: 34px 56px 28px;
  display: grid;
  grid-template-rows: auto 1fr auto;
  background: #fff;
}

.form-panel__top { display: flex; justify-content: flex-end; gap: 28px; color: #8290aa; font-size: 13px; }
.online { display: flex; align-items: center; gap: 7px; }
.online i { width: 7px; height: 7px; border-radius: 50%; background: #32c787; box-shadow: 0 0 0 4px rgba(50, 199, 135, .12); }
.top-right { display: flex; align-items: center; gap: 28px; }
.website-link { display: flex; align-items: center; gap: 2px; color: #8290aa; transition: color .2s; }
.website-link:hover { color: #335cff; }
.login-card { width: 100%; max-width: 480px; margin: auto; }
.login-card__logo { display: none; width: 176px; height: auto; margin-bottom: 40px; }
.login-card__heading h2 { margin: 0; color: #14213d; font-size: 34px; line-height: 1.3; }
.login-card__heading p { margin: 10px 0 34px; color: #a0acc0; font-size: 14px; }
.verify-field { display: grid; grid-template-columns: minmax(0, 1fr) 122px; gap: 12px; }
.verify-field >>> .ivu-input-wrapper { min-width: 0; }
.verify-image { height: 46px; padding: 0; overflow: hidden; position: relative; border: 1px solid #e0e6ef; border-radius: 6px; background: #f7f9fc; cursor: pointer; }
.verify-image img { width: 100%; height: 100%; display: block; object-fit: cover; }
.verify-image__expired { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; gap: 4px; color: #fff; background: rgba(45, 58, 82, .72); font-size: 12px; }
.submit-item { margin-top: 30px; }
.login-button { height: 48px; border: 0; border-radius: 6px; background: #5b85f7 !important; box-shadow: 0 10px 22px rgba(91, 133, 247, .2); font-weight: 500; transition: background .2s, transform .2s; }
.login-button:hover { background: #4775ef !important; transform: translateY(-1px); }
.login-card__hint { margin-top: 20px; color: #b4becf; font-size: 12px; text-align: center; }
.form-panel__footer { color: #c1cad9; font-size: 12px; text-align: center; }
.form-panel__footer a { color: #8290aa; } .form-panel__footer a:hover { color: #335cff; }
.captcha-box { width: 310px; }
#msg { width: 100%; line-height: 40px; font-size: 14px; text-align: center; }

.login-card >>> .ivu-form-item { margin-bottom: 22px; }
.login-card >>> .ivu-input-large { height: 46px; padding-left: 42px; border-color: #e0e6ef; border-radius: 6px; color: #263755; font-size: 14px; box-shadow: none; }
.login-card >>> .ivu-input-large:hover, .login-card >>> .ivu-input-large:focus { border-color: #77a1ff; box-shadow: 0 0 0 3px rgba(91, 133, 247, .09); }
.login-card >>> .ivu-input-prefix { top: 0; width: 42px; height: 46px; display: flex; align-items: center; justify-content: center; color: #a6b2c6; line-height: 1; }
.login-card >>> .ivu-input-prefix i { display: block; font-size: 17px; line-height: 1; }

@media (max-width: 960px) {
  .login-page { grid-template-columns: 42% 58%; }
  .visual-panel { padding: 30px 32px 42px; }
  .visual-panel__header img { width: 40px; height: 40px; }
  .visual-panel__art img { width: 96%; max-height: 58vh; }
  .form-panel { padding: 30px 40px 26px; }
}

@media (max-width: 720px) {
  .login-page { display: block; min-height: 100vh; padding: 22px; overflow: auto; background: linear-gradient(145deg, #eef5ff, #f8fbff); }
  .visual-panel { display: none; }
  .form-panel { min-height: calc(100vh - 44px); padding: 34px 28px 24px; border-radius: 18px; box-shadow: 0 20px 55px rgba(50, 82, 135, .1); }
  .form-panel__top { display: none; }
  .login-card { max-width: 430px; }
  .login-card__logo { display: block; }
  .login-card__heading h2 { font-size: 29px; }
  .login-card__heading p { margin-bottom: 30px; }
}

@media (max-width: 390px) {
  .login-page { padding: 0; }
  .form-panel { min-height: 100vh; padding: 30px 20px 22px; border-radius: 0; }
  .verify-field { grid-template-columns: minmax(0, 1fr) 102px; gap: 8px; }
}
</style>
