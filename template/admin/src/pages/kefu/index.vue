<template>
  <div class="kefu-login">
    <section class="brand-panel">
      <header class="brand-panel__header">
        <img :src="brandLogo" alt="QiaLink 洽联">
        <span>智能客服工作台</span>
      </header>
      <div class="brand-panel__content">
        <div class="brand-panel__copy">
          <span class="eyebrow">QIALINK SERVICE DESK</span>
          <h1>让每一次沟通<br>都更有价值</h1>
          <p>统一接待、实时协同、持续连接，为团队提供清晰高效的客户服务体验。</p>
        </div>
        <div class="service-preview" aria-hidden="true">
          <div class="service-preview__top">
            <span><i></i><i></i><i></i></span><b>客户会话</b><em>在线</em>
          </div>
          <div class="service-preview__body">
            <div class="contact-list">
              <span class="contact-list__title"></span>
              <span v-for="item in 3" :key="item" class="contact-list__item"><i></i><b></b></span>
            </div>
            <div class="conversation">
              <span class="bubble bubble--left"></span>
              <span class="bubble bubble--right"></span>
              <span class="bubble bubble--left bubble--short"></span>
              <span class="conversation__input"></span>
            </div>
          </div>
          <span class="floating-card floating-card--satisfaction"><Icon type="ios-happy-outline" /> 98% 满意度</span>
          <span class="floating-card floating-card--message"><Icon type="ios-chatbubbles-outline" /> 会话实时接入</span>
        </div>
      </div>
      <div class="brand-panel__footer">
        <span><Icon type="ios-flash-outline" /> 实时响应</span>
        <span><Icon type="ios-people-outline" /> 团队协同</span>
        <span><Icon type="ios-lock-outline" /> 安全连接</span>
      </div>
    </section>

    <section class="login-panel">
      <div class="mobile-brand">
        <img :src="brandLogo" alt="QiaLink 洽联">
        <span>智能客服工作台</span>
      </div>
      <main class="login-card">
        <div v-show="!loginType" class="login-mode">
          <div class="login-card__heading">
            <span class="login-card__icon"><img :src="brandIcon" alt=""></span>
            <h2>欢迎回来</h2>
            <p>登录 QiaLink 洽联客服工作台</p>
          </div>
          <Form ref="formInline" :model="formInline" :rules="ruleInline" @keyup.enter.native="handleSubmit('formInline')">
            <FormItem prop="username">
              <Input v-model="formInline.username" type="text" prefix="ios-person-outline" placeholder="请输入客服账号" size="large" />
            </FormItem>
            <FormItem prop="password">
              <Input v-model="formInline.password" type="password" prefix="ios-lock-outline" placeholder="请输入登录密码" size="large" />
            </FormItem>
            <FormItem class="submit-item">
              <Button type="primary" long size="large" class="login-button" @click="handleSubmit('formInline')">进入工作台</Button>
            </FormItem>
          </Form>
          <button v-if="!isMobile" type="button" class="mode-switch" @click="bindScan">
            <span class="iconfont iconerweima2"></span>使用 APP 扫码登录<Icon type="ios-arrow-forward" />
          </button>
        </div>

        <div v-show="loginType" class="login-mode">
          <div class="login-card__heading login-card__heading--scan">
            <span class="login-card__icon"><Icon type="ios-qr-scanner" /></span>
            <h2>APP 扫码登录</h2>
            <p>请使用 QiaLink 洽联移动端扫描二维码</p>
          </div>
          <div class="code-box">
            <div ref="qrCodeUrl" class="qrcode"></div>
            <div v-show="rxpired" class="expired-box">
              <Icon type="ios-refresh-circle-outline" />
              <p>二维码已过期</p>
              <Button type="primary" size="small" @click="bindRefresh">重新获取</Button>
            </div>
          </div>
          <button type="button" class="mode-switch mode-switch--center" @click="loginType = 0">
            <Icon type="ios-arrow-back" /> 返回账号登录
          </button>
        </div>
      </main>
      <p class="login-panel__meta"><i></i> QiaLink 服务连接正常</p>
    </section>
  </div>
</template>
<script>
import { AccountLogin, getSanCodeKey, scanStatus, kefuConfig } from '@/api/kefu';
import mixins from '../account/mixins';
import Setting from '@/setting';
import QRCode from 'qrcodejs2'
import { setCookies } from '@/libs/util'
import brandLogo from '@/assets/images/qialink-logo-horizontal.png'
import brandIcon from '@/assets/images/qialink-logo-icon.png'
export default {
  mixins: [mixins],
  data() {
    return {
      brandLogo,
      brandIcon,
      fullWidth: document.documentElement.clientWidth,
      swiperOption: {
        pagination: '.swiper-pagination',
        autoplay: true
      },
      modals: false,
      autoLogin: true,
      imgcode: '',
      formInline: {
        username: '',
        password: '',
        code: ''
      },
      ruleInline: {
        username: [
          { required: true, message: '请输入用户名', trigger: 'blur' }
        ],
        password: [
          { required: true, message: '请输入密码', trigger: 'blur' }
        ],
        code: [
          { required: true, message: '请输入验证码', trigger: 'blur' }
        ]
      },
      errorNum: 0,
      jigsaw: null,
      login_logo: '',
      swiperList: [],
      defaultSwiperList: require('@/assets/images/sw.jpg'),
      loginType: 0, // 0 账号 1 扫码
      codeKey: '',
      scanTime: '',
      rxpired: false, // 扫码是否过期
      isMobile: false,
      version: '', //版本号
      isScan: false,
      timeNum: 0
    }
  },
  created() {
    kefuConfig().then(res => {
      this.version = res.data.version
      if(res.data.site_name) {
        document.title = res.data.site_name;
      }
    })
    this.isMobile = this.$store.state.media.isMobile
    var _this = this;
    top != window && (top.location.href = location.href);
    document.onkeydown = function(e) {
      if(_this.$route.name === 'login') {
        let key = window.event.keyCode;
        if(key === 13) {
          _this.handleSubmit('formInline');
        }
      }
    };
    window.addEventListener('resize', this.handleResize)

  },
  watch: {
    fullWidth(val) {
      // 为了避免频繁触发resize函数导致页面卡顿，使用定时器
      if(!this.timer) {
        // 一旦监听到的screenWidth值改变，就将其重新赋给data里的screenWidth
        this.screenWidth = val
        this.timer = true
        let that = this
        setTimeout(function() {
          // 打印screenWidth变化的值
          that.timer = false
        }, 400)
      }
    },
    $route(n) {
      console.log(n);
      this.captchas();
    },
  },
  mounted: function() {
    this.$nextTick(() => {
    });

    this.captchas();
  },
  methods: {
    // 切换扫码
    bindScan() {
      if(!this.isScan) {
        this.isScan = true
        this.getSanCodeKey()
      }
      this.loginType = 1
    },
    // 生成二维码
    creatQrCode() {
      let url = `${window.location.protocol}//${window.location.host}/pages/users/scan_login/index?key=${this.codeKey}`;
      var qrcode = new QRCode(this.$refs.qrCodeUrl, {
        text: url, // 需要转换为二维码的内容
        width: 160,
        height: 160,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.H
      })
    },
    // 关闭模态框
    closeModel() {
      let msg = this.$Message.loading({
        content: '登录中...',
        duration: 0
      });
      AccountLogin({
        account: this.formInline.username,
        password: this.formInline.password,
        imgcode: this.formInline.code
      }).then(res => {
        msg();
        let expires = this.getExpiresTime(res.data.exp_time);
        // 记录用户登陆信息
        setCookies('kefu_uuid', res.data.kefuInfo.uid, expires);
        setCookies('kefu_token', res.data.token, expires);
        setCookies('kefu_expires_time', res.data.exp_time, expires);
        setCookies('kefuInfo', res.data.kefuInfo, expires);

        console.log(res);

        // 记录用户信息
        this.$store.commit('kefu/setInfo', res.data.kefuInfo)


        //工作台已按视口自适应，不再按终端分流；原先手机分支指向的
        ///kefu/mobile_list 根本没有对应路由，手机登录会直接进404
        return this.$router.replace({ path: this.$route.query.redirect || '/kefu/workspace' });
      }).catch(rej => {
        msg();
        let data = rej === undefined ? {} : rej;
        this.errorNum++;
        this.captchas();
        this.$Message.error(data.msg || '登录失败');
        if(this.jigsaw) this.jigsaw.reset();
      });
    },
    getExpiresTime(expiresTime) {
      let nowTimeNum = Math.round(new Date() / 1000);
      let expiresTimeNum = expiresTime - nowTimeNum;
      return parseFloat(parseFloat(parseFloat(expiresTimeNum / 60) / 60) / 24);
    },
    closefail() {
      if(this.jigsaw) this.jigsaw.reset();
      this.$Message.error('校验错误');
    },
    handleResize(event) {
      this.fullWidth = document.documentElement.clientWidth

    },
    captchas: function() {
      this.imgcode = Setting.apiBaseURL + '/captcha_pro?' + Date.parse(new Date());
    },
    handleSubmit(name) {
      this.$refs[name].validate((valid) => {
        if(valid) {
          this.closeModel();
        }
      })
    },
    // 获取客服扫码key
    getSanCodeKey() {
      getSanCodeKey().then(res => {
        this.codeKey = res.data.key
        this.creatQrCode()
        this.scanTime = setInterval(() => {
          this.timeNum++
          if(this.timeNum >= 60) {
            this.timeNum = 0
            window.clearInterval(this.scanTime)
            this.rxpired = true
          } else {
            this.getScanStatus()
          }

        }, 1000)
      }).catch(error => {
        this.timeNum = 0
        window.clearInterval(this.scanTime)
        this.rxpired = true
        this.$Message.error(error.msg)
      })
    },
    // 扫码登录情况
    getScanStatus() {
      scanStatus(this.codeKey).then(async res => {
        // 0 = 二维码过期需要重新获取授权凭证
        if(res.data.status == 0) {
          this.timeNum = 0
          window.clearInterval(this.scanTime)
          this.rxpired = true
        }
        // 1=正在扫描
        if(res.data.status == 1) {

        }
        // 3 扫描成功正在登录
        if(res.data.status == 3) {
          window.clearInterval(this.scanTime)
          let expires = this.getExpiresTime(res.data.exp_time);
          // 记录用户登陆信息
          setCookies('kefu_uuid', res.data.kefuInfo.uid, expires);
          setCookies('kefu_token', res.data.token, expires);
          setCookies('kefu_expires_time', res.data.exp_time, expires);
          setCookies('kefuInfo', res.data.kefuInfo, expires);
          // 记录用户信息
          this.$store.commit('kefu/setInfo', res.data.kefuInfo)
          return this.$router.replace({ path: this.$route.query.redirect || '/kefu/workspace' });
        }
      }).catch(error => {
        this.$Modal.error({
          title: '提示',
          content: error.msg
        });
        this.timeNum = 0
        window.clearInterval(this.scanTime)
        this.rxpired = true
      })
    },
    // 刷新二维码
    bindRefresh() {
      this.$refs.qrCodeUrl.innerHTML = ''
      this.rxpired = false
      this.getSanCodeKey()
    }
  },
  beforeCreate() {

  },
  beforeDestroy: function() {
    this.timeNum = 0
    this.$refs.qrCodeUrl.innerHTML = ''
    window.clearInterval(this.scanTime)
    window.removeEventListener('resize', this.handleResize);
    // document.getElementsByTagName('canvas')[0].removeAttribute('class', 'index_bg');
  }
};
</script>
<style scoped lang="stylus">
.kefu-login, .kefu-login * { box-sizing: border-box; }
.kefu-login { min-height: 100vh; display: grid; grid-template-columns: minmax(520px, 1.1fr) minmax(460px, .9fr); color: #15233d; background: #f7faff; font-family: Inter, "PingFang SC", "Microsoft YaHei", sans-serif; }

.brand-panel { min-height: 100vh; padding: 38px 52px 34px; position: relative; overflow: hidden; display: grid; grid-template-rows: auto 1fr auto; color: #fff; background: linear-gradient(145deg, #3488f8 0%, #1677ee 52%, #4b8df3 100%); }
.brand-panel::before, .brand-panel::after { content: ''; position: absolute; border: 1px solid rgba(255, 255, 255, .14); border-radius: 50%; pointer-events: none; }
.brand-panel::before { width: 580px; height: 580px; right: -250px; top: -280px; box-shadow: 0 0 0 90px rgba(255,255,255,.025), 0 0 0 180px rgba(255,255,255,.02); }
.brand-panel::after { width: 380px; height: 380px; left: -250px; bottom: -230px; box-shadow: 0 0 0 70px rgba(255,255,255,.025); }
.brand-panel__header, .brand-panel__content, .brand-panel__footer { position: relative; z-index: 1; }
.brand-panel__header { display: flex; align-items: center; justify-content: space-between; }
.brand-panel__header img { width: 164px; height: auto; filter: brightness(0) invert(1) drop-shadow(0 4px 12px rgba(0, 53, 132, .18)); }
.brand-panel__header span { padding: 7px 12px; border: 1px solid rgba(255,255,255,.2); border-radius: 999px; color: rgba(255,255,255,.78); background: rgba(255,255,255,.08); font-size: 12px; }
.brand-panel__content { min-height: 0; display: grid; grid-template-columns: minmax(260px, .78fr) minmax(300px, 1.22fr); gap: 34px; align-items: center; }
.eyebrow { display: block; margin-bottom: 18px; color: #cde3ff; font-size: 11px; font-weight: 600; letter-spacing: 2.4px; }
.brand-panel__copy h1 { margin: 0; font-size: clamp(34px, 3.3vw, 54px); line-height: 1.24; letter-spacing: 1px; }
.brand-panel__copy p { max-width: 410px; margin: 22px 0 0; color: rgba(255,255,255,.76); font-size: 14px; line-height: 1.9; }
.brand-panel__footer { display: flex; align-items: center; gap: 30px; color: rgba(255,255,255,.7); font-size: 12px; }
.brand-panel__footer span { display: flex; align-items: center; gap: 7px; }
.brand-panel__footer i { font-size: 16px; }

.service-preview { width: 100%; aspect-ratio: 1.28; position: relative; border: 1px solid rgba(255,255,255,.34); border-radius: 18px; background: rgba(255,255,255,.94); box-shadow: 0 28px 65px rgba(0, 66, 155, .24); transform: perspective(1000px) rotateY(-4deg) rotateX(2deg); }
.service-preview__top { height: 16%; padding: 0 5%; display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; border-bottom: 1px solid #eaf0f8; color: #6c7d96; }
.service-preview__top > span { display: flex; gap: 4px; }
.service-preview__top > span i { width: 5px; height: 5px; border-radius: 50%; background: #cbd7e8; }
.service-preview__top b { font-size: 10px; font-weight: 500; }
.service-preview__top em { justify-self: end; color: #32b987; font-size: 9px; font-style: normal; }
.service-preview__body { height: 84%; display: grid; grid-template-columns: 34% 66%; }
.contact-list { padding: 8% 7%; display: flex; flex-direction: column; gap: 10%; border-right: 1px solid #edf2f8; background: #f8faff; }
.contact-list__title { width: 54%; height: 6px; border-radius: 4px; background: #dce6f3; }
.contact-list__item { height: 19%; padding: 7%; display: flex; align-items: center; gap: 9px; border-radius: 7px; background: #fff; }
.contact-list__item:first-of-type { background: #eaf3ff; }
.contact-list__item i { width: 22px; height: 22px; flex: none; border-radius: 50%; background: linear-gradient(145deg, #7bb5ff, #4187ee); }
.contact-list__item b { width: 45%; height: 5px; border-radius: 4px; background: #cdd9e9; }
.conversation { padding: 10% 8%; position: relative; display: flex; flex-direction: column; gap: 10%; }
.bubble { width: 62%; height: 15%; border-radius: 4px 12px 12px 12px; background: #edf2f8; }
.bubble--right { align-self: flex-end; border-radius: 12px 4px 12px 12px; background: #4f91f4; }
.bubble--short { width: 44%; }
.conversation__input { height: 14%; position: absolute; right: 8%; bottom: 9%; left: 8%; border: 1px solid #e1e9f4; border-radius: 8px; background: #fafcff; }
.floating-card { position: absolute; padding: 10px 14px; display: flex; align-items: center; gap: 7px; border-radius: 10px; color: #345276; background: rgba(255,255,255,.96); box-shadow: 0 14px 32px rgba(0,66,155,.2); font-size: 11px; font-style: normal; }
.floating-card i { color: #267ff0; font-size: 17px; }
.floating-card--satisfaction { right: -7%; top: 17%; }
.floating-card--message { left: -8%; bottom: 13%; }

.login-panel { min-height: 100vh; padding: 42px clamp(42px, 6vw, 92px) 30px; display: grid; grid-template-rows: auto 1fr auto; background: #fff; }
.mobile-brand { display: none; }
.login-card { width: 100%; max-width: 430px; margin: auto; }
.login-mode { width: 100%; }
.login-card__heading { margin-bottom: 38px; }
.login-card__heading--scan { text-align: center; }
.login-card__icon { width: 48px; height: 48px; margin-bottom: 24px; display: flex; align-items: center; justify-content: center; border: 1px solid #e6eef9; border-radius: 14px; background: #f5f9ff; box-shadow: 0 9px 24px rgba(48, 113, 202, .09); }
.login-card__icon img { width: 30px; height: 30px; object-fit: contain; }
.login-card__icon i { color: #287feb; font-size: 26px; }
.login-card__heading--scan .login-card__icon { margin-right: auto; margin-left: auto; }
.login-card__heading h2 { margin: 0; color: #13203a; font-size: 31px; line-height: 1.35; letter-spacing: .5px; }
.login-card__heading p { margin: 9px 0 0; color: #9aa8bc; font-size: 14px; }
.submit-item { margin-top: 30px; }
.login-button { height: 48px; border: 0; border-radius: 8px; background: linear-gradient(90deg, #347ff0, #5b91f7) !important; box-shadow: 0 12px 24px rgba(52,127,240,.2); font-weight: 500; letter-spacing: 1px; transition: transform .2s, box-shadow .2s; }
.login-button:hover { transform: translateY(-1px); box-shadow: 0 15px 28px rgba(52,127,240,.27); }
.mode-switch { width: 100%; padding: 8px 0; display: flex; align-items: center; justify-content: center; gap: 7px; border: 0; color: #7c8ca4; background: transparent; cursor: pointer; font-size: 13px; transition: color .2s; }
.mode-switch:hover { color: #287feb; }
.mode-switch .iconfont { color: #287feb; font-size: 19px; }
.mode-switch--center { margin-top: 24px; }
.login-panel__meta { margin: 0; color: #a7b4c7; font-size: 12px; text-align: center; }
.login-panel__meta i { width: 6px; height: 6px; margin-right: 7px; display: inline-block; border-radius: 50%; background: #32c787; box-shadow: 0 0 0 4px rgba(50,199,135,.1); vertical-align: 1px; }
.code-box { width: 220px; height: 220px; margin: 0 auto; padding: 18px; position: relative; display: flex; align-items: center; justify-content: center; border: 1px solid #e4ebf5; border-radius: 16px; background: #fff; box-shadow: 0 12px 35px rgba(33,76,135,.08); }
.qrcode { width: 180px; height: 180px; display: flex; align-items: center; justify-content: center; }
.expired-box { position: absolute; inset: 18px; display: flex; flex-direction: column; align-items: center; justify-content: center; border-radius: 8px; color: #fff; background: rgba(24,39,64,.82); backdrop-filter: blur(3px); }
.expired-box > i { margin-bottom: 5px; font-size: 24px; }
.expired-box p { margin: 0 0 12px; font-size: 13px; }
.login-card >>> .ivu-form-item { margin-bottom: 22px; }
.login-card >>> .ivu-input-large { height: 48px; padding-left: 44px; border-color: #dfe7f2; border-radius: 8px; color: #273a58; font-size: 14px; box-shadow: none; }
.login-card >>> .ivu-input-large:hover, .login-card >>> .ivu-input-large:focus { border-color: #72a4ef; box-shadow: 0 0 0 3px rgba(52,127,240,.08); }
.login-card >>> .ivu-input-prefix { top: 0; width: 44px; height: 48px; display: flex; align-items: center; justify-content: center; color: #9eacc0; line-height: 1; }
.login-card >>> .ivu-input-prefix i { display: block; font-size: 18px; line-height: 1; }

@media (max-width: 1100px) {
  .kefu-login { grid-template-columns: minmax(420px, .95fr) minmax(430px, 1.05fr); }
  .brand-panel { padding: 34px 38px 30px; }
  .brand-panel__content { grid-template-columns: 1fr; gap: 20px; }
  .brand-panel__copy { align-self: end; text-align: center; }
  .brand-panel__copy p { margin-right: auto; margin-left: auto; }
  .service-preview { width: min(80%, 420px); margin: 0 auto; }
  .brand-panel__footer { justify-content: center; }
}
@media (max-width: 820px) {
  .kefu-login { display: block; min-height: 100vh; padding: 24px; background: linear-gradient(145deg, #eef5ff, #f8fbff); }
  .brand-panel { display: none; }
  .login-panel { min-height: calc(100vh - 48px); padding: 34px 30px 26px; border-radius: 20px; box-shadow: 0 22px 60px rgba(49,88,141,.1); }
  .mobile-brand { display: flex; align-items: center; justify-content: space-between; }
  .mobile-brand img { width: 150px; height: auto; }
  .mobile-brand span { color: #9aa8bc; font-size: 12px; }
  .login-card { max-width: 440px; }
}
@media (max-width: 480px) {
  .kefu-login { padding: 0; background: #fff; }
  .login-panel { min-height: 100vh; padding: 27px 22px 22px; border-radius: 0; box-shadow: none; }
  .mobile-brand img { width: 136px; }
  .mobile-brand span { display: none; }
  .login-card__heading { margin-bottom: 30px; }
  .login-card__heading h2 { font-size: 28px; }
}
</style>
