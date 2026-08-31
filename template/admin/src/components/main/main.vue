<template>
  <Layout style="height: 100%" class="main">
    <Sider hide-trigger collapsible :width="200" :collapsed-width="isMobile?0:80" v-model="collapsed" class="left-sider" :style="{overflow: 'hidden'}">
      <side-menu accordion ref="sideMenu" :active-name="$route.path" :collapsed="collapsed" @on-select="turnToPage" :menu-list="menuList">
        <!-- 需要放在菜单上面的内容，如Logo，写在side-menu标签内部，如下 -->
        <div class="logo-con">
          <img v-show="!collapsed" :src="maxLogo" class="logo-con__image logo-con__image--wide" key="max-logo" alt="QiaLink 洽联" @error="handleLogoError('max')" />
          <img v-show="collapsed" :src="minLogo" class="logo-con__image logo-con__image--compact" key="min-logo" alt="QiaLink 洽联图标" @error="handleLogoError('min')" />
        </div>
      </side-menu>
    </Sider>
    <Layout>
      <Header class="header-con">
        <header-bar :collapsed="collapsed" @on-coll-change="handleCollapsedChange" @on-reload="handleReload">
          <user :message-unread-count="unreadCount" :user-avatar="userAvatar" />
          <language v-if="$config.useI18n" @on-lang-change="setLocal" style="margin-right: 10px;" :lang="local" />
          <header-notice></header-notice>
          <fullscreen v-model="isFullscreen" style="margin-right: 10px;" />
          <!-- <error-store v-if="$config.plugin['error-store'] && $config.plugin['error-store'].showInHeader" :has-read="hasReadErrorPage" :count="errorCount"></error-store> -->
          <header-search></header-search>
        </header-bar>
      </Header>
      <!-- 租户视角横幅：平台账号很容易忘了自己正代入某个租户，改错数据代价大 -->
      <div class="view-tenant-bar" v-if="viewTenant">
        <Icon type="md-eye" size="16"/>
        <span>正在以「<b>{{ viewTenant.name }}</b>」的身份查看，所有操作都作用于该租户</span>
        <a @click="exitTenantView">退出租户视角</a>
      </div>
      <Content class="main-content-con">
        <Layout class="main-layout-con">
          <div class="tag-nav-wrapper">
            <tags-nav :value="$route" @input="handleClick" :list="tagNavList" @on-close="handleCloseTag" />
          </div>
          <Content class="content-wrapper">
            <!--            <keep-alive :include="cacheList">-->
            <!--              <router-view v-if="reload"/>-->
            <!--            </keep-alive>-->
            <router-view v-if="reload" style="min-height: 600px;" />
            <!--<ABackTop :height="100" :bottom="80" :right="50" container=".content-wrapper"></ABackTop>-->
          </Content>
        </Layout>
      </Content>
    </Layout>
    <!--    <div class="open-image" @click="clear" v-if="openImage"><img src="@/assets/images/wechat_demo.png" alt=""></div>-->
  </Layout>
</template>
<script>
import SideMenu from './components/side-menu'
import { getViewTenant, clearViewTenant } from '@/libs/tenantView'
import { viewAuthApi } from '@/api/setting'
import HeaderBar from './components/header-bar'
import TagsNav from './components/tags-nav'
import User from './components/user'
import ABackTop from './components/a-back-top'
import Fullscreen from './components/fullscreen'
import Language from './components/language'
// import ErrorStore from './components/error-store'
import HeaderSearch from './components/header-search'
import HeaderNotice from './components/header-notice'

import Setting from '@/setting'
import iView from 'iview'
import { mapMutations, mapActions, mapGetters, mapState } from 'vuex'
import { getNewTagList, routeEqual, getMenuopen, getCookies, setCookies } from '@/libs/util'
import { getLogo } from '@/api/common';
import routers from '@/router/routers'
import defaultMinLogo from '@/assets/images/qialink-logo-icon.png'
import defaultMaxLogo from '@/assets/images/qialink-logo-horizontal.png'
import './main.less'

const isValidLogo = logo => typeof logo === 'string' && logo.trim().length > 0
export default {
  name: 'Main',
  components: {
    SideMenu,
    HeaderBar,
    Language,
    TagsNav,
    Fullscreen,
    //ErrorStore,
    User,
    ABackTop,
    HeaderSearch,
    HeaderNotice
  },
  data() {
    return {
      viewTenant: getViewTenant(),
      collapsed: JSON.parse(getCookies('collapsed') || 'false'),
      minLogo: defaultMinLogo,
      maxLogo: defaultMaxLogo,
      isFullscreen: false,
      reload: true,
      screenWidth: '',
      openImage: true
    }
  },
  computed: {
    ...mapGetters([
      'errorCount'
    ]),
    ...mapState('media', [
      'isMobile'
    ]),
    tagNavList() {
      return this.$store.state.app.tagNavList
    },
    tagRouter() {
      return this.$store.state.app.tagRouter
    },
    userAvatar() {
      return this.$store.state.user.avatarImgPath
    },
    cacheList() {
      const list = ['ParentView', ...this.tagNavList.length ? this.tagNavList.filter(item => !(item.meta && item.meta.notCache)).map(item => item.name) : []]

      return list
    },
    menuList() {



      let menus = this.$store.state.menus.menusName

      let newArray = []
      menus.forEach((now, index) => {
        newArray[index] = now
        if(newArray[index].children && now.children) {
          newArray[index].children = now.children.filter((item) => {
            return !item.auth
          })
        }
      })
      return newArray
      // return this.$store.state.menus.menusName
    },
    local() {
      return this.$store.state.app.local
    },
    hasReadErrorPage() {
      return this.$store.state.app.hasReadErrorPage
    },
    unreadCount() {
      return this.$store.state.user.unreadCount
    }
  },
  methods: {
    //登录时下发的权限是登录那一刻的视角；切换视角后必须重新拉，
    //否则会出现菜单看得到但路由被拦、或反过来的错位
    refreshViewAuth () {
      viewAuthApi().then(res => {
        const data = res.data || {}
        if (!data.unique_auth) return
        this.$store.commit('userInfo/uniqueAuth', data.unique_auth)
        this.$store.commit('userInfo/access', data.unique_auth)
        this.$store.commit('menus/getmenusNav', data.menus)
      }).catch(() => {})
    },
    exitTenantView () {
      clearViewTenant()
      //权限随视角变化，整页重载确保菜单与路由权限一并刷新
      window.location.href = '/admin'
    },
    ...mapMutations([
      'setBreadCrumb',
      'setTagNavList',
      'addTag',
      'setLocal',
      'setHomeRoute',
      'closeTag'
    ]),
    ...mapActions([
      'handleLogin',
      'getUnreadMessageCount'
    ]),
    turnToPage(route, all) {
      let { path, name, params, query } = {}
      if(typeof route === 'string' && !all) path = route
      else if(typeof route === 'string' && all) name = route
      else {
        path = route.path
        name = route.name
        params = route.params
        query = route.query
      }
      this.$router.push({
        path,
        name,
        params,
        query
      })
    },
    handleCollapsedChange(state) {
      this.collapsed = state;
      setCookies('collapsed', state);
    },
    handleCloseTag(res, type, route) {
      if(type !== 'others') {
        if(type === 'all') {
          this.turnToPage(this.$config.homeName, 'all')
        } else {
          if(routeEqual(this.$route, route)) {
            this.closeTag(route)
          }
        }
      }
      if(res.length === 1 && res[0].name === this.$config.homeName) {
        this.$router.push({ name: this.$config.homeName })
      }
      this.setTagNavList(res)
    },
    handleClick(item) {
      this.turnToPage(item)
    },
    getLogo() {
      this.applyLogo({
        logo: this.$store.state.userInfo.logo,
        logoSmall: this.$store.state.userInfo.logoSmall
      })
      getLogo().then(res => {
        if(!res || !res.data) return
        this.applyLogo({ logo: res.data.logo, logoSmall: res.data.logo_square })
      }).catch(error => {
        console.warn('[QiaLink] 获取导航栏 Logo 失败，继续使用品牌默认资源', error)
      })
    },
    applyLogo({ logo, logoSmall }) {
      if(isValidLogo(logo)) this.maxLogo = logo.trim()
      if(isValidLogo(logoSmall)) this.minLogo = logoSmall.trim()
    },
    handleLogoError(type) {
      if(type === 'max') {
        this.maxLogo = defaultMaxLogo
        return
      }
      this.minLogo = defaultMinLogo
    },
    handleReload() {
      this.reload = false
      // if (Setting.showProgressBar) iView.LoadingBar.start()
      this.$nextTick(() => {
        this.reload = true
        // if (Setting.showProgressBar) iView.LoadingBar.finish()
      })
    },
    clear() {
      this.openImage = false;
    },
  },
  watch: {
    '$route'(newRoute) {
      let openNames = getMenuopen(newRoute, this.menuList)
      this.$store.commit('menus/setopenMenus', openNames)
      const { name, query, params, meta } = newRoute
      this.addTag({
        route: { name, query, params, meta },
        type: 'push'
      })
      this.setBreadCrumb(newRoute)
      this.setTagNavList(getNewTagList(this.tagNavList, newRoute))
      this.$refs.sideMenu.updateOpenName(newRoute.path)
    }
  },
  mounted() {
    this.refreshViewAuth()
    this.screenWidth = document.body.clientWidth
    window.onresize = () => {
      return (() => {
        this.screenWidth = document.body.clientWidth
        if(this.screenWidth <= 1060) {
          this.collapsed = true
          setCookies('collapsed', true);
        } else {
          this.collapsed = false
          setCookies('collapsed', false);
        }
      })()
    }

    /**
     * @description 初始化设置面包屑导航和标签导航
     */
    this.setTagNavList()
    this.setHomeRoute(routers)
    const { name, params, query, meta } = this.$route
    this.addTag({
      route: { name, params, query, meta }
    })
    this.setBreadCrumb(this.$route)
    // 设置初始语言
    this.setLocal(this.$i18n.locale)
    // 如果当前打开页面不在标签栏中，跳到homeName页
    if(!this.tagNavList.find(item => item.name === this.$route.name)) {
      this.$router.push({
        name: this.$config.homeName
      })
    }
    // 获取未读消息条数
    this.getUnreadMessageCount()
    this.getLogo()
  }
}
</script>
<style lang="less">
.main .header-con {
  padding: 0 20px 0 0px;
}
.main .logo-con img {
  width: auto;
  max-width: 100%;
  max-height: 42px;
  object-fit: contain;
}
.main .tag-nav-wrapper {
  background: unset;
}
.open-image {
  display: flex;
  align-items: center;
  justify-content: center;
  position: fixed;
  background-color: rgba(0, 0, 0, 0.6);
  height: 100%;
  width: 100%;
  top: 0;
  left: 0;
  z-index: 1000;
}

.view-tenant-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: #FFF7E6;
  border-bottom: 1px solid #FFE0A3;
  color: #8C5A00;
  font-size: 13px;
}

.view-tenant-bar a {
  margin-left: auto;
  color: #E38B00;
  text-decoration: underline;
}
</style>
