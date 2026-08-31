/**
 * 访客端装修主题：数据挂在会话接口返回体的 theme 字段上，由 socketServer 混入的 chatServerData 承载，
 * 因此本混入必须与 socketServer 一起使用；后端未下发 theme 时全部退化为组件内置的默认外观。
 */
const DEFAULT_THEME_COLOR = '#3875ea';
const PLATFORM_BRAND_TEXT = '技术支持 by QiaLink 洽联';
const BRAND_HIDDEN = 0; // show_platform_brand = 0 表示白标
const BUBBLE_TEXT_COLOR = '#fff';
const BANNER_AUTOPLAY_SPEED = 4000;

// banners 是 json 字段，网关或旧版本后端可能原样透传字符串
function parseBanners(raw) {
  if(Array.isArray(raw)) return raw;
  if(typeof raw !== 'string' || !raw) return [];
  try {
    const list = JSON.parse(raw);
    return Array.isArray(list) ? list : [];
  } catch (e) {
    console.error('[appTheme] 轮播图配置解析失败', e);
    return [];
  }
}

export default {
  data() {
    return {
      platformBrandText: PLATFORM_BRAND_TEXT,
      bannerAutoplaySpeed: BANNER_AUTOPLAY_SPEED,
      bannerIndex: 0
    }
  },
  computed: {
    appTheme() {
      return this.chatServerData.theme || {};
    },
    hasThemeColor() {
      return !!this.appTheme.theme_color;
    },
    themeColor() {
      return this.appTheme.theme_color || DEFAULT_THEME_COLOR;
    },
    // 返回空对象是为了让样式表里的默认渐变/底色继续生效，而不是被覆盖成默认色
    themeBgStyle() {
      return this.hasThemeColor ? { background: this.themeColor } : {};
    },
    // 主题色饱和度不可控，同步改文字色以保证气泡可读
    themeBubbleStyle() {
      if(!this.hasThemeColor) return {};
      return { background: this.themeColor, color: BUBBLE_TEXT_COLOR };
    },
    headerTitle() {
      return this.appTheme.title || this.chatServerData.to_user_nickname;
    },
    headerLogo() {
      return this.appTheme.logo || this.chatServerData.to_user_avatar;
    },
    themeBanners() {
      return parseBanners(this.appTheme.banners)
        .filter(item => item && item.image)
        .sort((a, b) => (a.sort || 0) - (b.sort || 0));
    },
    // 自定义广告HTML，后端已做XSS清洗；未配置轮播图时才展示
    themeCustomHtml() {
      return this.appTheme.custom_html || '';
    },
    // 旧后端不下发 theme，默认展示平台标识，仅显式置 0 时才隐藏
    isShowPlatformBrand() {
      const flag = this.appTheme.show_platform_brand;
      if(flag === undefined || flag === null || flag === '') return true;
      return Number(flag) !== BRAND_HIDDEN;
    }
  },
  methods: {
    // 仅访客自己发出的气泡跟随主题色，坐席侧保持默认灰底
    messageBubbleStyle(item) {
      return item.user_id == this.chatServerData.user_id ? this.themeBubbleStyle : {};
    },
    openBanner(banner) {
      if(!banner.link) return;
      window.open(banner.link);
    }
  }
}
