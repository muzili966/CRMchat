<template>
    <div>
        <div class="i-layout-page-header">
            <div class="i-layout-page-header">
                <span class="ivu-page-header-title">{{$route.meta.title}}</span>
            </div>
        </div>
        <Card :bordered="false" dis-hover class="ivu-mt">
            <div class="app-bar">
                <span class="app-bar-label">选择应用：</span>
                <Select v-model="appid" class="app-bar-select" placeholder="请选择应用" @on-change="onAppChange">
                    <Option v-for="item in appList" :value="item.appid" :key="item.appid">
                        {{ item.name }}（{{ item.appid }}）
                    </Option>
                </Select>
                <span class="app-bar-tip">装修配置按应用独立保存</span>
            </div>
            <div class="app-empty" v-if="!appList.length">暂无应用，请先在「应用管理」中添加应用</div>
            <Row :gutter="24" v-else>
                <Col :xl="14" :lg="14" :md="24" :sm="24" :xs="24">
                    <Form :model="form" :label-width="110" @submit.native.prevent>
                        <Divider orientation="left" size="small" class="deco-divider">聊天窗口</Divider>
                        <FormItem label="窗口标题：">
                            <Input v-model="form.title" :maxlength="titleMax" placeholder="留空则显示应用名称"/>
                            <div class="counter">{{ form.title.length }}/{{ titleMax }}</div>
                        </FormItem>
                        <FormItem label="窗口LOGO：">
                            <div class="pic-box" :class="{ 'pic-box-locked': !brandCustom }"
                                 @click="brandCustom && openPic('logo')">
                                <img v-if="form.logo" :src="form.logo">
                                <div class="pic-empty" v-else><Icon type="ios-camera-outline" size="26"/></div>
                            </div>
                            <a class="pic-clear" v-if="form.logo && brandCustom" @click="clearPic('logo')">清除</a>
                            <template v-if="brandCustom">
                                <p class="field-tip">建议正方形图片，展示在聊天窗口标题栏</p>
                            </template>
                            <template v-else>
                                <Tag color="gold" class="brand-tag">{{ requiredPlan('brand_custom') }}</Tag>
                                <p class="field-tip">升级后可换成贵司自己的LOGO</p>
                            </template>
                        </FormItem>
                        <FormItem label="界面布局：">
                            <div class="theme-preset-grid">
                                <button v-for="item in layoutPresets" :key="item.value" type="button"
                                        class="theme-preset" :class="{ 'theme-preset-active': form.theme_style === item.value }"
                                        :disabled="!brandCustom" @click="selectLayout(item)">
                                    <span class="layout-preset-preview" :class="'layout-preset-' + item.size">
                                        <i class="layout-mini-header"></i>
                                        <span class="layout-mini-body"><i></i><i></i><i></i></span>
                                    </span>
                                    <span class="theme-preset-copy">
                                        <strong>{{ item.name }}</strong>
                                        <small>{{ item.description }}</small>
                                    </span>
                                    <Icon v-if="form.theme_style === item.value" type="md-checkmark-circle" size="19"/>
                                </button>
                            </div>
                            <p class="field-tip" v-if="brandCustom">仅调整标题栏、头像、间距和消息区密度，不会改变主题色</p>
                            <template v-else>
                                <Tag color="gold" class="brand-tag">{{ requiredPlan('brand_custom') }}</Tag>
                                <p class="field-tip">升级后可切换布局密度与气泡样式</p>
                            </template>
                        </FormItem>
                        <FormItem label="主题色：">
                            <ColorPicker v-model="form.theme_color" recommend :colors="recommendColors"/>
                            <span class="color-value">{{ form.theme_color }}</span>
                            <a class="pic-clear" @click="resetColor">恢复默认</a>
                        </FormItem>
                        <FormItem label="气泡样式：">
                            <div class="bubble-preset-grid">
                                <button v-for="item in bubblePresets" :key="item.value" type="button"
                                        class="bubble-preset" :class="{ 'bubble-preset-active': form.bubble_style === item.value }"
                                        :disabled="!brandCustom" @click="form.bubble_style = item.value">
                                    <span class="bubble-preset-preview" :class="'bubble-preview-' + item.value">
                                        <i></i><i :style="{ background: previewColor, borderColor: previewColor }"></i>
                                    </span>
                                    <span><strong>{{ item.name }}</strong><small>{{ item.description }}</small></span>
                                </button>
                            </div>
                        </FormItem>
                        <FormItem label="轮播广告：">
                            <div class="banner-empty" v-if="!form.banners.length">
                                暂未配置轮播广告，访客窗口不展示广告位
                            </div>
                            <div class="banner-item" v-for="(item, index) in form.banners" :key="index">
                                <div class="pic-box pic-box-banner" @click="openBannerPic(index)">
                                    <img v-if="item.image" :src="item.image">
                                    <div class="pic-empty" v-else><Icon type="ios-camera-outline" size="26"/></div>
                                </div>
                                <Input v-model="item.link" class="banner-link" placeholder="跳转链接，选填，需以 http:// 或 https:// 开头"/>
                                <a class="banner-del" @click="removeBanner(index)">删除</a>
                            </div>
                            <Button type="dashed" long icon="md-add" class="banner-add"
                                    :disabled="bannerFull || !customAd" @click="addBanner">
                                {{ bannerFull ? `最多添加 ${bannerMax} 张` : '添加一张' }}
                            </Button>
                            <template v-if="!customAd">
                                <Tag color="gold" class="brand-tag">{{ requiredPlan('custom_ad') }}</Tag>
                                <p class="field-tip">当前套餐的客服窗口展示平台统一广告，升级后可投放自有广告</p>
                            </template>
                        </FormItem>
                        <FormItem label="自定义广告：">
                            <Collapse v-model="advPanel" simple>
                                <Panel :name="advPanelName">
                                    自定义广告内容（HTML）· 高级用法
                                    <div slot="content">
                                        <Input v-model="form.custom_html" type="textarea" :rows="customHtmlRows"
                                               :maxlength="customHtmlMax" :disabled="!customAd"
                                               placeholder="支持HTML，留空则只展示上方轮播图"/>
                                        <div class="counter">{{ form.custom_html.length }}/{{ customHtmlMax }}</div>
                                        <p class="field-tip">
                                            仅在未配置轮播广告时展示；保存时会自动移除 script、iframe 等不安全内容
                                        </p>
                                    </div>
                                </Panel>
                            </Collapse>
                        </FormItem>
                        <FormItem label="平台标识：">
                            <i-switch v-model="form.show_platform_brand" :true-value="brandShow" :false-value="brandHide"
                                      :disabled="!whiteLabel" size="large">
                                <span slot="open">显示</span>
                                <span slot="close">隐藏</span>
                            </i-switch>
                            <Tag color="gold" class="brand-tag" v-if="!whiteLabel">{{ requiredPlan('white_label') }}</Tag>
                            <p class="field-tip" v-if="!whiteLabel">升级套餐后可隐藏平台标识</p>
                            <p class="field-tip" v-else>关闭后访客窗口底部不再显示「{{ platformBrand }}」</p>
                        </FormItem>
                        <Divider orientation="left" size="small" class="deco-divider">悬浮入口</Divider>
                        <FormItem label="悬浮图标：">
                            <div class="launcher-gallery">
                                <div class="launcher-tile" :class="{ 'launcher-tile-on': !form.pc_icon }"
                                     @click="brandCustom && pickLauncherDefault()" title="平台默认">
                                    <span class="launcher-default-txt">默认</span>
                                </div>
                                <div v-for="p in launcherPresets" :key="p.key" class="launcher-tile"
                                     :class="{ 'launcher-tile-on': launcher.icon === p.key }"
                                     :title="p.label" @click="brandCustom && pickLauncherPreset(p.key)">
                                    <img :src="launcherThumb(p.key)">
                                </div>
                                <div class="launcher-tile launcher-upload" :class="{ 'launcher-tile-on': isCustomLauncher }"
                                     @click="brandCustom && openPic('launcher')" title="上传自定义">
                                    <img v-if="isCustomLauncher" :src="form.pc_icon">
                                    <Icon v-else type="ios-cloud-upload-outline" size="22"/>
                                </div>
                            </div>
                            <p class="field-tip" v-if="brandCustom">预设跟随主题色；也可上传自定义图标，留空即用平台默认。</p>
                            <template v-else><Tag color="gold" class="brand-tag">{{ requiredPlan('brand_custom') }}</Tag></template>
                        </FormItem>
                        <FormItem label="图标形状：" v-if="brandCustom && hasLauncherPreset">
                            <RadioGroup :value="launcher.shape" type="button" @on-change="setLauncherShape">
                                <Radio v-for="sp in launcherShapes" :key="sp.key" :label="sp.key">{{ sp.label }}</Radio>
                            </RadioGroup>
                            <p class="field-tip">胶囊形可在图标后跟一段文案，与默认按钮一致</p>
                        </FormItem>
                        <FormItem label="按钮文案：" v-if="brandCustom && hasLauncherPreset && launcherShapeWithText">
                            <Input v-model="launcher.text" :maxlength="launcherTextMax" style="width:220px"
                                   placeholder="如：在线咨询" @on-change="onLauncherText"/>
                            <p class="field-tip">最多 {{ launcherTextMax }} 字，仅 PC 端展示；移动端保持小圆图标</p>
                        </FormItem>
                        <FormItem label="入口预览：" v-if="form.pc_icon">
                            <div class="launcher-preview"><img :src="form.pc_icon"></div>
                        </FormItem>
                        <FormItem label="悬浮按钮：">
                            <i-switch v-model="form.show_tip" :true-value="1" :false-value="0" size="large">
                                <span slot="open">显示</span>
                                <span slot="close">隐藏</span>
                            </i-switch>
                            <p class="field-tip">
                                关闭后接入方页面上不再出现客服入口按钮，需自行在页面里放置触发入口
                            </p>
                        </FormItem>
                        <FormItem label="窗口形态：">
                            <RadioGroup v-model="form.window_style" type="button">
                                <Radio label="float">悬浮对话框</Radio>
                                <Radio label="center">居中弹窗</Radio>
                            </RadioGroup>
                            <p class="field-tip">
                                悬浮对话框贴在页面右下角；居中弹窗带遮罩、面积更大，可展示广告位
                            </p>
                        </FormItem>
                        <div class="save-bar">
                            <Button type="primary" :loading="saving" @click="save">保存</Button>
                        </div>
                    </Form>
                </Col>
                <Col :xl="10" :lg="10" :md="24" :sm="24" :xs="24">
                    <div class="preview-toolbar">
                        <span>访客端实时预览</span>
                        <ButtonGroup size="small">
                            <Button :type="previewDevice === 'desktop' ? 'primary' : 'default'" @click="previewDevice = 'desktop'">桌面</Button>
                            <Button :type="previewDevice === 'mobile' ? 'primary' : 'default'" @click="previewDevice = 'mobile'">手机</Button>
                        </ButtonGroup>
                    </div>
                    <div class="preview-wrap" :class="'preview-wrap-' + previewDevice">
                        <div class="chat-window" :class="previewClasses" :style="previewVariables">
                            <div class="chat-header" :style="{ background: previewColor }">
                                <div class="chat-identity">
                                    <img class="chat-logo" :src="previewAgentLogo" @error="handlePreviewLogoError">
                                    <div class="chat-identity-copy">
                                        <span class="chat-title">{{ previewTitle }}</span>
                                        <small><i></i>客服在线 · 为您服务</small>
                                    </div>
                                </div>
                                <Icon class="chat-close" type="ios-close" size="22"/>
                            </div>
                            <div class="chat-banner" v-if="firstBanner">
                                <img :src="firstBanner.image">
                                <span class="chat-banner-badge">广告</span>
                                <div class="chat-banner-dots" v-if="form.banners.length > 1">
                                    <i v-for="(item, index) in form.banners" :key="index"
                                       :class="{ 'dot-on': index === 0 }"></i>
                                </div>
                            </div>
                            <div class="chat-body">
                                <div class="chat-msg" v-for="(item, index) in previewMessages" :key="index"
                                     :class="item.self ? 'chat-msg-self' : ''">
                                    <span class="chat-avatar" :class="{ 'chat-avatar-self': item.self }">
                                        <Icon v-if="item.self" type="ios-person-outline"/>
                                        <img v-else :src="previewAgentLogo" @error="handlePreviewLogoError">
                                    </span>
                                    <div class="chat-bubble" :style="previewBubbleStyle(item.self)">
                                        {{ item.text }}
                                    </div>
                                </div>
                            </div>
                            <div class="chat-footer chat-footer-desktop" v-if="previewDevice === 'desktop'">
                                <div class="chat-footer-tools">
                                    <Icon class="chat-tool" type="ios-happy-outline" size="21"/>
                                    <Icon class="chat-tool" type="ios-image-outline" size="21"/>
                                    <Icon class="chat-tool" type="ios-person-outline" size="20"/>
                                </div>
                                <div class="chat-textarea">请输入您的问题…</div>
                                <div class="chat-footer-action">
                                    <div class="chat-send" :style="{ background: previewColor }">发送</div>
                                </div>
                            </div>
                            <div class="chat-footer chat-footer-mobile" v-else>
                                <Icon class="chat-tool" type="ios-image-outline" size="22"/>
                                <div class="chat-input">请输入您的问题…</div>
                                <Icon class="chat-tool" type="ios-happy-outline" size="22"/>
                                <div class="chat-send" :style="{ background: previewColor }">发送</div>
                            </div>
                            <div class="chat-brand" v-if="form.show_platform_brand === brandShow">{{ platformBrand }}</div>
                        </div>
                    </div>
                </Col>
            </Row>
        </Card>

        <Modal v-model="modalPic" width="950px" scrollable footer-hide closable title="选择图片" :mask-closable="false" :z-index="888">
            <uploadPictures :isChoice="isChoice" @getPic="getPic" :gridBtn="gridBtn" :gridPic="gridPic" v-if="modalPic"></uploadPictures>
        </Modal>
    </div>
</template>

<script>
    import { appThemeApi, appThemeSaveApi } from '@/api/theme'
    import { appListApi } from '@/api/application'
    import { mySubscriptionApi, planFeatureApi } from '@/api/tenant'
    import uploadPictures from '@/components/uploadPictures'
    import defaultBrandIcon from '@/assets/images/qialink-logo-icon.png'
    import { LAUNCHER_PRESETS, LAUNCHER_SHAPES, LAUNCHER_TEXT_MAX, buildLauncherPc, buildLauncherMobile, buildLauncherThumb, readLauncherConfig, isLauncherPreset } from '@/libs/chatLauncher'
    import {
        CHAT_BUBBLE_PRESETS,
        CHAT_LAYOUT_PRESETS,
        DEFAULT_BUBBLE_STYLE,
        DEFAULT_CHAT_LAYOUT,
        getBubbleStyle,
        getChatLayout,
        getChatThemeVariables
    } from '@/config/chatThemes'

    const THEME_COLOR_DEFAULT = '#2d8cf0'
    const PLATFORM_BRAND = '技术支持 by QiaLink 洽联'
    const BRAND_SHOW = 1
    const BRAND_HIDE = 0
    const BANNER_MAX = 5
    const TITLE_MAX = 50
    // 与后端 ApplicationTheme::MAX_CUSTOM_HTML 保持一致
    const CUSTOM_HTML_MAX = 10000
    const CUSTOM_HTML_ROWS = 6
    const ADV_PANEL_NAME = 'custom-html'
    const APP_LIMIT = 100
    const NO_BANNER = -1
    const HEX_COLOR_REG = /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/
    const LINK_REG = /^https?:\/\//

    const RECOMMEND_COLORS = ['#2d8cf0', '#19be6b', '#ff9900', '#ed4014', '#9b59b6', '#2f3542']

    const PREVIEW_MESSAGES = [
        { self: false, text: '您好，请问有什么可以帮您？' },
        { self: true, text: '我想咨询一下产品价格' },
        { self: false, text: '我们有多个套餐可选，稍后为您发送详细报价。' }
    ]

    const createForm = () => ({
        appid: '',
        title: '',
        logo: '',
        theme_color: THEME_COLOR_DEFAULT,
        theme_style: DEFAULT_CHAT_LAYOUT,
        bubble_style: DEFAULT_BUBBLE_STYLE,
        pc_icon: '',
        show_tip: 1,
        window_style: 'float',
        mobile_icon: '',
        banners: [],
        custom_html: '',
        show_platform_brand: BRAND_SHOW
    })

    // 后端 json 字段可能以字符串或数组两种形态返回，统一成 {image, link, sort} 数组
    const normalizeBanners = banners => {
        const list = typeof banners === 'string' ? safeParse(banners) : banners
        if (!Array.isArray(list)) return []
        return list.map((item, index) => ({
            image: item.image || '',
            link: item.link || '',
            sort: item.sort === undefined ? index : item.sort
        }))
    }

    const safeParse = text => {
        if (!text) return []
        try {
            return JSON.parse(text)
        } catch (e) {
            console.error('轮播广告解析失败', e)
            return []
        }
    }

    export default {
        name: 'chat_theme',
        components: { uploadPictures },
        data () {
            return {
                // 悬浮图标编辑态（icon 空=默认/自定义）
                launcher: { icon: '', shape: 'circle', text: '' },

                saving: false,
                appid: '',
                appList: [],
                whiteLabel: false,
                customAd: false,
                brandCustom: false,
                //各能力的最低所需套餐名，由订阅接口下发，不在前端写死
                upgradePlans: {},
                form: createForm(),
                modalPic: false,
                isChoice: '单选',
                picTarget: { field: '', index: NO_BANNER },
                gridBtn: { xl: 4, lg: 8, md: 8, sm: 8, xs: 8 },
                gridPic: { xl: 6, lg: 8, md: 12, sm: 12, xs: 12 },
                recommendColors: RECOMMEND_COLORS,
                layoutPresets: CHAT_LAYOUT_PRESETS,
                bubblePresets: CHAT_BUBBLE_PRESETS,
                defaultBrandIcon,
                previewDevice: 'mobile',
                previewMessages: PREVIEW_MESSAGES,
                platformBrand: PLATFORM_BRAND,
                titleMax: TITLE_MAX,
                bannerMax: BANNER_MAX,
                customHtmlMax: CUSTOM_HTML_MAX,
                customHtmlRows: CUSTOM_HTML_ROWS,
                advPanel: [],
                advPanelName: ADV_PANEL_NAME,
                brandShow: BRAND_SHOW,
                brandHide: BRAND_HIDE
            }
        },
        computed: {
            launcherPresets () { return LAUNCHER_PRESETS },
            launcherShapes () { return LAUNCHER_SHAPES },
            launcherTextMax () { return LAUNCHER_TEXT_MAX },
            // 是否选了预设图标（非默认/自定义）
            hasLauncherPreset () { return !!this.launcher.icon },
            // 当前形状是否支持文案
            launcherShapeWithText () { return this.launcher.shape === 'pill' },
            // 非预设、非空 = 自定义上传
            isCustomLauncher () { return !!this.form.pc_icon && !isLauncherPreset(this.form.pc_icon) },
            currentApp () {
                return this.appList.find(item => item.appid === this.appid) || {}
            },
            previewTitle () {
                return this.form.title || this.currentApp.name || '在线客服'
            },
            previewColor () {
                return HEX_COLOR_REG.test(this.form.theme_color) ? this.form.theme_color : THEME_COLOR_DEFAULT
            },
            previewAgentLogo () {
                return this.form.logo || this.defaultBrandIcon
            },
            previewVariables () {
                //与访客端调用同一个函数，预设改动无需在预览里重复一遍
                return getChatThemeVariables(
                    this.previewColor,
                    getChatLayout(this.form.theme_style).value,
                    getBubbleStyle(this.form.bubble_style).value
                )
            },
            previewClasses () {
                return [`chat-window-${this.previewDevice}`]
            },
            firstBanner () {
                return this.form.banners.find(item => item.image) || null
            },
            bannerFull () {
                return this.form.banners.length >= BANNER_MAX
            }
        },
        created () {
            this.getAppList()
            this.getWhiteLabel()
            this.loadUpgradePlans()
        },
        watch: {
            // 改主题色时，预设气泡跟随重生成；自定义/默认不动
            'form.theme_color' () {
                if (this.launcher.icon) { this.regenLauncher() }
            }
        },
        methods: {
            // 图库缩略图（跟随当前形状与主题色）
            launcherThumb (key) { return buildLauncherThumb(key, this.launcher.shape, this.previewColor) },
            // 依据当前编辑态重生成 PC 与移动端两张图
            regenLauncher () {
                if (!this.launcher.icon) { this.form.pc_icon = ''; this.form.mobile_icon = ''; return }
                this.form.pc_icon = buildLauncherPc(this.launcher, this.previewColor)
                this.form.mobile_icon = buildLauncherMobile(this.launcher, this.previewColor)
            },
            pickLauncherPreset (key) { this.launcher.icon = key; this.regenLauncher() },
            setLauncherShape (shape) { this.launcher.shape = shape; this.regenLauncher() },
            onLauncherText () {
                this.launcher.text = (this.launcher.text || '').slice(0, LAUNCHER_TEXT_MAX)
                this.regenLauncher()
            },
            pickLauncherDefault () {
                this.launcher = { icon: '', shape: 'circle', text: '' }
                this.form.pc_icon = ''
                this.form.mobile_icon = ''
            },
            handlePreviewLogoError (event) {
                const image = event && event.target
                if (!image || image.dataset.fallbackApplied) return
                image.dataset.fallbackApplied = 'true'
                image.src = this.defaultBrandIcon
            },
            getAppList () {
                appListApi({ page: 1, limit: APP_LIMIT }).then(res => {
                    this.appList = res.data.list || []
                    if (!this.appList.length) return
                    this.appid = this.appList[0].appid
                    this.getTheme()
                }).catch(res => {
                    this.$Message.error(res.msg)
                })
            },
            // 白标开关受套餐门控，缺省按不可用处理，避免误导租户
            getWhiteLabel () {
                mySubscriptionApi().then(res => {
                    const plan = (res.data || {}).plan
                    this.whiteLabel = !!(plan && plan.white_label)
                    this.brandCustom = !!(plan && plan.brand_custom)
                }).catch(res => {
                    console.error('套餐权益读取失败', res)
                })
            },
            getTheme () {
                appThemeApi({ appid: this.appid }).then(res => {
                    this.fillForm(res.data || {})
                }).catch(res => {
                    this.$Message.error(res.msg)
                })
            },
            fillForm (data) {
                const theme = data.theme || data
                this.form = Object.assign(createForm(), theme, {
                    appid: this.appid,
                    title: theme.title || '',
                    theme_color: theme.theme_color || THEME_COLOR_DEFAULT,
                    theme_style: getChatLayout(theme.theme_style).value,
                    bubble_style: getBubbleStyle(theme.bubble_style).value,
                    banners: normalizeBanners(theme.banners),
                    custom_html: theme.custom_html || '',
                    // 开关与预览按数值严格比较，后端字符串形态需先归一
                    show_platform_brand: Number(theme.show_platform_brand) === BRAND_HIDE ? BRAND_HIDE : BRAND_SHOW
                })
                // 已有自定义内容时默认展开，避免折叠区里的配置被忽略
                this.advPanel = this.form.custom_html ? [ADV_PANEL_NAME] : []
                if (data.white_label !== undefined) this.whiteLabel = !!data.white_label
                //装修详情接口已带套餐能力时以它为准，省一次订阅接口请求
                const plan = data.plan || {}
                if (plan.white_label !== undefined) this.whiteLabel = !!plan.white_label
                if (plan.custom_ad !== undefined) this.customAd = !!plan.custom_ad
                if (plan.brand_custom !== undefined) this.brandCustom = !!plan.brand_custom
                // 从已存的 pc_icon 反解编辑态；非预设则回到默认态
                this.launcher = readLauncherConfig(this.form.pc_icon) || { icon: '', shape: 'circle', text: '' }
            },
            //门禁提示里的套餐名取自真实套餐数据，运营调整权益后自动跟随
            loadUpgradePlans () {
                planFeatureApi().then(res => {
                    const data = res.data || {}
                    this.upgradePlans = data.upgrade || {}
                    if (data.unlimited) {
                        this.brandCustom = this.customAd = this.whiteLabel = true
                    }
                }).catch(() => {})
            },
            requiredPlan (feature) {
                const name = this.upgradePlans[feature]
                return name ? `${name}及以上` : '升级可用'
            },
            onAppChange () {
                if (!this.appid) return
                this.getTheme()
            },
            openPic (field) {
                this.picTarget = { field, index: NO_BANNER }
                this.modalPic = true
            },
            openBannerPic (index) {
                this.picTarget = { field: 'banners', index }
                this.modalPic = true
            },
            getPic (pc) {
                const { field, index } = this.picTarget
                if (field === 'launcher') { this.form.pc_icon = pc.att_dir; this.form.mobile_icon = pc.att_dir; this.launcher = { icon: '', shape: 'circle', text: '' } }
                else if (index === NO_BANNER) this.form[field] = pc.att_dir
                else this.form.banners[index].image = pc.att_dir
                this.modalPic = false
            },
            clearPic (field) {
                this.form[field] = ''
            },
            resetColor () {
                this.form.theme_color = THEME_COLOR_DEFAULT
            },
            selectLayout (layout) {
                this.form.theme_style = layout.value
            },
            previewBubbleStyle (self) {
                if (!self) return null
                if (this.form.bubble_style === 'outline') {
                    return { background: 'transparent', color: this.previewColor, borderColor: this.previewColor }
                }
                return { background: this.previewColor }
            },
            addBanner () {
                if (this.bannerFull) return this.$Message.warning(`最多添加 ${BANNER_MAX} 张轮播广告`)
                this.form.banners.push({ image: '', link: '', sort: this.form.banners.length })
            },
            removeBanner (index) {
                this.form.banners.splice(index, 1)
            },
            // 返回空串表示校验通过
            checkForm () {
                if (!this.form.appid) return '请先选择应用'
                if (!HEX_COLOR_REG.test(this.form.theme_color)) return '主题色需为合法的十六进制颜色，如 #2d8cf0'
                if (this.form.banners.some(item => !item.image)) return '轮播广告的图片不能为空'
                if (this.form.banners.some(item => item.link && !LINK_REG.test(item.link))) {
                    return '轮播跳转链接需以 http:// 或 https:// 开头'
                }
                return ''
            },
            buildPayload () {
                const banners = this.form.banners.map((item, index) => ({
                    image: item.image,
                    link: item.link,
                    sort: index
                }))
                return Object.assign({}, this.form, { banners })
            },
            save () {
                const error = this.checkForm()
                if (error) return this.$Message.error(error)
                this.saving = true
                appThemeSaveApi(this.buildPayload()).then(res => {
                    this.saving = false
                    this.$Message.success(res.msg)
                }).catch(res => {
                    this.saving = false
                    this.$Message.error(res.msg)
                })
            }
        }
    }
</script>

<style scoped>
    .app-bar {
        margin-bottom: 20px;
    }
    .app-bar-label {
        color: #515a6e;
        font-size: 14px;
    }
    .app-bar-select {
        width: 280px;
    }
    .app-bar-tip {
        color: #808695;
        font-size: 12px;
        margin-left: 12px;
    }
    .app-empty {
        color: #808695;
        text-align: center;
        padding: 40px 0;
    }
    .field-tip {
        color: #808695;
        font-size: 12px;
        line-height: 1.6;
    }
    .theme-preset-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }
    .theme-preset {
        min-height: 76px;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        color: #515a6e;
        text-align: left;
        background: #fff;
        border: 1px solid #dcdee2;
        border-radius: 8px;
        cursor: pointer;
        transition: border-color .2s, box-shadow .2s, transform .2s;
    }
    .theme-preset:hover {
        border-color: #8bbcf1;
        transform: translateY(-1px);
    }
    .theme-preset-active {
        color: #2d8cf0;
        border-color: #2d8cf0;
        box-shadow: 0 0 0 2px rgba(45, 140, 240, .1);
    }
    .layout-preset-preview {
        width: 48px;
        height: 48px;
        flex: none;
        display: flex;
        flex-direction: column;
        padding: 5px;
        background: #f3f6fb;
        border: 1px solid #e5ebf5;
        border-radius: 8px;
        box-sizing: border-box;
    }
    .layout-mini-header {
        display: block;
        height: 7px;
        background: #2d8cf0;
        border-radius: 3px 3px 1px 1px;
    }
    .layout-mini-body {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 3px;
    }
    .layout-mini-body i {
        display: block;
        width: 68%;
        height: 5px;
        background: #fff;
        border-radius: 4px;
        box-shadow: 0 1px 2px rgba(31, 45, 61, .1);
    }
    .layout-mini-body i:nth-child(2) {
        width: 58%;
        align-self: flex-end;
        background: #8bbcf1;
    }
    .layout-preset-compact .layout-mini-body {
        gap: 1px;
    }
    .layout-preset-compact .layout-mini-body i {
        height: 4px;
    }
    .layout-preset-spacious .layout-mini-body {
        gap: 5px;
    }
    .layout-preset-spacious .layout-mini-body i:nth-child(3) {
        display: none;
    }
    .layout-preset-focus .layout-mini-body i {
        width: 82%;
    }
    .layout-preset-focus .layout-mini-body i:nth-child(2) {
        width: 76%;
    }
    .bubble-preset-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
    }
    .bubble-preset {
        min-height: 92px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        gap: 7px;
        padding: 8px;
        color: #515a6e;
        text-align: center;
        background: #fff;
        border: 1px solid #dcdee2;
        border-radius: 8px;
        cursor: pointer;
    }
    .bubble-preset:hover,
    .bubble-preset-active {
        color: #2d8cf0;
        border-color: #2d8cf0;
    }
    .bubble-preset-active {
        box-shadow: 0 0 0 2px rgba(45, 140, 240, .1);
    }
    .bubble-preset > span:last-child {
        display: flex;
        flex-direction: column;
        line-height: 1.4;
    }
    .bubble-preset small {
        color: #808695;
        font-size: 10px;
    }
    .bubble-preset-preview {
        width: 62px;
        height: 28px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .bubble-preset-preview i {
        width: 38px;
        height: 11px;
        background: #eef2f7;
        border: 1px solid transparent;
        border-radius: 8px 8px 8px 2px;
    }
    .bubble-preset-preview i:last-child {
        width: 32px;
        align-self: flex-end;
        border-radius: 8px 8px 2px 8px;
    }
    .bubble-preview-clean i {
        border-radius: 3px;
    }
    .bubble-preview-pill i {
        border-radius: 12px;
    }
    .bubble-preview-outline i,
    .bubble-preview-outline i:last-child {
        background: transparent !important;
        border-color: #aab6c8;
    }
    .bubble-preview-card i {
        border-radius: 5px;
        box-shadow: 0 3px 7px rgba(31, 45, 61, .2);
    }
    .theme-preset-copy {
        min-width: 0;
        flex: 1;
        display: flex;
        flex-direction: column;
        line-height: 1.45;
    }
    .theme-preset-copy strong {
        color: inherit;
        font-size: 14px;
    }
    .theme-preset-copy small {
        color: #808695;
        font-size: 11px;
    }
    .counter {
        text-align: right;
        color: #808695;
        font-size: 12px;
        line-height: 1.6;
    }
    .pic-box {
        display: inline-block;
        vertical-align: middle;
        width: 58px;
        height: 58px;
        border: 1px dashed #dcdee2;
        border-radius: 4px;
        cursor: pointer;
        overflow: hidden;
    }
    .pic-box img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .pic-empty {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #c5c8ce;
    }
    /* 未开通时不可点击，但保留占位让租户看见这里能配什么 */
    .pic-box-locked {
        cursor: not-allowed;
        opacity: .55;
    }

    .pic-clear {
        margin-left: 12px;
        font-size: 12px;
    }
    .color-value {
        color: #515a6e;
        font-size: 13px;
        margin-left: 10px;
        vertical-align: middle;
    }
    .banner-empty {
        color: #808695;
        font-size: 12px;
        margin-bottom: 10px;
    }
    .banner-item {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }
    .pic-box-banner {
        width: 88px;
        height: 50px;
        flex: none;
    }
    .banner-link {
        margin: 0 12px;
    }
    .banner-del {
        flex: none;
    }
    .banner-add {
        margin-top: 4px;
    }
    .brand-tag {
        margin-left: 10px;
    }
    .save-bar {
        border-top: 1px solid #e8eaec;
        padding-top: 16px;
        margin-top: 8px;
    }
    .preview-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
        color: #515a6e;
        font-size: 13px;
    }
    .preview-wrap {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 620px;
        padding: 24px 12px;
        background: #f2f4f7;
        border-radius: 12px;
        transition: background .2s;
    }
    .chat-window {
        width: 360px;
        max-width: 100%;
        height: 560px;
        display: flex;
        flex-direction: column;
        color: var(--chat-text);
        background: var(--chat-surface);
        border: 1px solid var(--chat-border);
        border-radius: 14px;
        box-shadow: var(--chat-shadow);
        overflow: hidden;
        transition: width .25s, height .25s, border-radius .25s;
    }
    .chat-window-mobile {
        width: 320px;
        height: 580px;
        border-radius: 24px;
    }
    .chat-window-desktop {
        width: 390px;
        height: 540px;
    }
    .chat-header {
        position: relative;
        display: flex;
        align-items: center;
        height: 52px;
        padding: 0 14px;
        color: #fff;
    }
    .chat-logo {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 10px;
        background: #fff;
    }
    .chat-identity {
        min-width: 0;
        display: flex;
        align-items: center;
    }
    .chat-identity-copy {
        min-width: 0;
        display: flex;
        flex-direction: column;
    }
    .chat-identity-copy small {
        margin-top: 2px;
        display: flex;
        align-items: center;
        gap: 5px;
        color: rgba(255, 255, 255, .76);
        font-size: 9px;
        line-height: 1;
    }
    .chat-identity-copy small i {
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: #8ff0c2;
        box-shadow: 0 0 0 2px rgba(143, 240, 194, .18);
    }
    .chat-close {
        position: absolute;
        right: 14px;
        color: rgba(255, 255, 255, .8);
    }
    .chat-title {
        font-size: 15px;
        font-weight: 600;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .chat-banner {
        position: relative;
        height: 92px;
        background: #f0f2f5;
    }
    .chat-banner img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .chat-banner-badge {
        position: absolute;
        left: 8px;
        top: 8px;
        background: rgba(0, 0, 0, 0.45);
        color: #fff;
        font-size: 11px;
        border-radius: 2px;
        padding: 1px 6px;
    }
    .chat-banner-dots {
        position: absolute;
        right: 10px;
        bottom: 8px;
    }
    .chat-banner-dots i {
        display: inline-block;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.6);
        margin-left: 4px;
    }
    .chat-banner-dots .dot-on {
        background: #fff;
    }
    .chat-body {
        flex: 1;
        padding: 18px 14px;
        background: var(--chat-page-bg);
        overflow: hidden;
    }
    .chat-msg {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        margin-bottom: 12px;
    }
    .chat-msg-self {
        flex-direction: row-reverse;
        justify-content: flex-start;
    }
    .chat-avatar {
        width: calc(30px * var(--chat-avatar-scale, 1));
        height: calc(30px * var(--chat-avatar-scale, 1));
        display: var(--chat-avatar-display, flex);
        align-items: center;
        justify-content: center;
        flex: none;
        overflow: hidden;
        border: 2px solid #fff;
        border-radius: 50%;
        color: #8290a7;
        background: #e8eef7;
        box-shadow: 0 3px 10px rgba(31, 45, 61, .08);
    }
    .chat-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .chat-avatar i {
        font-size: 17px;
    }
    .chat-bubble {
        max-width: calc(76% * var(--chat-bubble-width-scale, 1));
        padding: 9px var(--chat-bubble-pad-x, 12px);
        border-radius: var(--chat-bubble-radius-in, 14px 14px 14px 4px);
        border: var(--chat-bubble-border-width, 0px) solid var(--chat-border);
        background: var(--chat-bubble-fill, var(--chat-incoming));
        color: var(--chat-text);
        font-size: 13px;
        line-height: 1.6;
        box-shadow: var(--chat-bubble-shadow, none);
    }
    .chat-msg-self .chat-bubble {
        color: #fff;
        border-radius: var(--chat-bubble-radius-out, 14px 14px 4px 14px);
    }
    /* 预设数值与访客端共用 @/config/chatThemes.js，预览只按同样的倍率换算自己的基准 */
    .chat-header {
        justify-content: var(--chat-header-justify, flex-start);
    }
    .chat-logo {
        display: var(--chat-header-logo, block);
    }
    /* 预览按真实 PC / 手机端的基准尺寸换算，只缩小外层窗口，不另造一套气泡比例 */
    .chat-window-desktop {
        .chat-body {
            padding: 0 0 20px;
        }
        .chat-logo {
            width: calc(30px * var(--chat-avatar-scale, 1));
            height: calc(30px * var(--chat-avatar-scale, 1));
        }
        .chat-msg {
            gap: 10px;
            margin-bottom: 0;
            padding: calc(8px * var(--chat-density, 1)) 8px;
        }
        .chat-avatar {
            width: calc(33px * var(--chat-avatar-scale, 1));
            height: calc(33px * var(--chat-avatar-scale, 1));
        }
        .chat-bubble {
            max-width: calc(60% * var(--chat-bubble-width-scale, 1));
            padding-top: 7px;
            padding-bottom: 7px;
        }
    }
    .chat-window-mobile {
        .chat-body {
            padding: 12px 8px 20px;
        }
        .chat-logo {
            width: calc(34px * var(--chat-avatar-scale, 1));
            height: calc(34px * var(--chat-avatar-scale, 1));
        }
        .chat-msg {
            gap: 9px;
            margin-bottom: 0;
            padding: calc(7px * var(--chat-density, 1)) 4px;
        }
        .chat-avatar {
            width: calc(36px * var(--chat-avatar-scale, 1));
            height: calc(36px * var(--chat-avatar-scale, 1));
        }
        .chat-bubble {
            max-width: calc((78% - 46px) * var(--chat-bubble-width-scale, 1));
            padding-top: 10px;
            padding-bottom: 10px;
        }
    }

    .chat-footer {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 12px;
        background: var(--chat-surface);
        border-top: 1px solid var(--chat-border);
    }
    .chat-footer-desktop {
        min-height: 132px;
        padding: 10px 14px 12px;
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 6px;
        box-shadow: 0 -8px 24px rgba(31, 45, 61, .035);
    }
    .chat-footer-tools {
        height: 24px;
        display: flex;
        align-items: center;
        gap: 13px;
    }
    .chat-textarea {
        flex: 1;
        color: var(--chat-muted);
        font-size: 12px;
    }
    .chat-footer-action {
        display: flex;
        justify-content: flex-end;
    }
    .chat-footer-desktop .chat-send {
        height: 30px;
        padding: 0 18px;
        line-height: 30px;
        border-radius: 8px;
    }
    .chat-footer-mobile {
        display: flex;
    }
    .chat-input {
        flex: 1;
        min-width: 0;
        height: 38px;
        line-height: 38px;
        padding: 0 10px;
        background: var(--chat-page-bg);
        border: 1px solid var(--chat-border);
        border-radius: 12px;
        color: var(--chat-muted);
        font-size: 13px;
    }
    .chat-tool {
        flex: none;
        color: var(--chat-muted);
    }
    .chat-send {
        flex: none;
        padding: 0 14px;
        height: 38px;
        line-height: 38px;
        border-radius: 11px;
        color: #fff;
        font-size: 13px;
    }
    .chat-brand {
        text-align: center;
        color: var(--chat-muted);
        background: var(--chat-surface);
        font-size: 11px;
        padding-bottom: 8px;
    }
    @media (max-width: 768px) {
        .theme-preset-grid,
        .bubble-preset-grid {
            grid-template-columns: 1fr;
        }
        .preview-wrap {
            min-height: 0;
        }
    }

    .deco-divider {
        margin: 6px 0 18px;
        font-weight: 600;
        color: #1f2d3d;
    }
    .launcher-gallery {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .launcher-tile {
        width: 52px;
        height: 52px;
        border: 1px solid #e4e8f0;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        overflow: hidden;
        transition: border-color .2s, box-shadow .2s;
        background: #fff;
    }
    .launcher-tile:hover { border-color: #b9c6e6; }
    .launcher-tile-on {
        border-color: #335cff;
        box-shadow: 0 0 0 2px rgba(51,92,255,.16);
    }
    .launcher-tile img { width: 38px; height: 38px; display: block; }
    .launcher-default-txt { font-size: 13px; color: #8a95a6; }
    .launcher-upload { border-style: dashed; color: #97a1b2; }

    .launcher-preview {
        display: inline-flex; align-items: center;
        min-height: 52px; padding: 6px 10px;
        background: #f6f8fc; border: 1px solid #eef1f6; border-radius: 12px;
    }
    .launcher-preview img { height: 44px; width: auto; display: block; }
</style>
