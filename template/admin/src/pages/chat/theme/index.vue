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
                        <FormItem label="窗口标题：">
                            <Input v-model="form.title" :maxlength="titleMax" placeholder="留空则显示应用名称"/>
                            <div class="counter">{{ form.title.length }}/{{ titleMax }}</div>
                        </FormItem>
                        <FormItem label="窗口LOGO：">
                            <div class="pic-box" @click="openPic('logo')">
                                <img v-if="form.logo" :src="form.logo">
                                <div class="pic-empty" v-else><Icon type="ios-camera-outline" size="26"/></div>
                            </div>
                            <a class="pic-clear" v-if="form.logo" @click="clearPic('logo')">清除</a>
                            <p class="field-tip">建议正方形图片，展示在聊天窗口标题栏</p>
                        </FormItem>
                        <FormItem label="主题色：">
                            <ColorPicker v-model="form.theme_color" recommend :colors="recommendColors"/>
                            <span class="color-value">{{ form.theme_color }}</span>
                            <a class="pic-clear" @click="resetColor">恢复默认</a>
                        </FormItem>
                        <FormItem label="PC悬浮图标：">
                            <div class="pic-box" @click="openPic('pc_icon')">
                                <img v-if="form.pc_icon" :src="form.pc_icon">
                                <div class="pic-empty" v-else><Icon type="ios-camera-outline" size="26"/></div>
                            </div>
                            <a class="pic-clear" v-if="form.pc_icon" @click="clearPic('pc_icon')">清除</a>
                            <p class="field-tip">留空使用默认图标</p>
                        </FormItem>
                        <FormItem label="移动端图标：">
                            <div class="pic-box" @click="openPic('mobile_icon')">
                                <img v-if="form.mobile_icon" :src="form.mobile_icon">
                                <div class="pic-empty" v-else><Icon type="ios-camera-outline" size="26"/></div>
                            </div>
                            <a class="pic-clear" v-if="form.mobile_icon" @click="clearPic('mobile_icon')">清除</a>
                            <p class="field-tip">留空使用默认图标</p>
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
                                    :disabled="bannerFull" @click="addBanner">
                                {{ bannerFull ? `最多添加 ${bannerMax} 张` : '添加一张' }}
                            </Button>
                        </FormItem>
                        <FormItem label="平台标识：">
                            <i-switch v-model="form.show_platform_brand" :true-value="brandShow" :false-value="brandHide"
                                      :disabled="!whiteLabel" size="large">
                                <span slot="open">显示</span>
                                <span slot="close">隐藏</span>
                            </i-switch>
                            <Tag color="gold" class="brand-tag" v-if="!whiteLabel">旗舰版功能</Tag>
                            <p class="field-tip" v-if="!whiteLabel">升级套餐后可隐藏平台标识</p>
                            <p class="field-tip" v-else>关闭后访客窗口底部不再显示「{{ platformBrand }}」</p>
                        </FormItem>
                        <div class="save-bar">
                            <Button type="primary" :loading="saving" @click="save">保存</Button>
                        </div>
                    </Form>
                </Col>
                <Col :xl="10" :lg="10" :md="24" :sm="24" :xs="24">
                    <div class="preview-tip">以下为访客看到的效果（示意）</div>
                    <div class="preview-wrap">
                        <div class="chat-window">
                            <div class="chat-header" :style="{ background: previewColor }">
                                <img class="chat-logo" v-if="form.logo" :src="form.logo">
                                <span class="chat-title">{{ previewTitle }}</span>
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
                                    <div class="chat-bubble" :style="item.self ? { background: previewColor } : null">
                                        {{ item.text }}
                                    </div>
                                </div>
                            </div>
                            <div class="chat-footer">
                                <div class="chat-input">请输入您的问题…</div>
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
    import { mySubscriptionApi } from '@/api/tenant'
    import uploadPictures from '@/components/uploadPictures'

    const THEME_COLOR_DEFAULT = '#2d8cf0'
    const PLATFORM_BRAND = '技术支持 by QiaLink 洽联'
    const BRAND_SHOW = 1
    const BRAND_HIDE = 0
    const BANNER_MAX = 5
    const TITLE_MAX = 50
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
        pc_icon: '',
        mobile_icon: '',
        banners: [],
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
                saving: false,
                appid: '',
                appList: [],
                whiteLabel: false,
                form: createForm(),
                modalPic: false,
                isChoice: '单选',
                picTarget: { field: '', index: NO_BANNER },
                gridBtn: { xl: 4, lg: 8, md: 8, sm: 8, xs: 8 },
                gridPic: { xl: 6, lg: 8, md: 12, sm: 12, xs: 12 },
                recommendColors: RECOMMEND_COLORS,
                previewMessages: PREVIEW_MESSAGES,
                platformBrand: PLATFORM_BRAND,
                titleMax: TITLE_MAX,
                bannerMax: BANNER_MAX,
                brandShow: BRAND_SHOW,
                brandHide: BRAND_HIDE
            }
        },
        computed: {
            currentApp () {
                return this.appList.find(item => item.appid === this.appid) || {}
            },
            previewTitle () {
                return this.form.title || this.currentApp.name || '在线客服'
            },
            previewColor () {
                return HEX_COLOR_REG.test(this.form.theme_color) ? this.form.theme_color : THEME_COLOR_DEFAULT
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
        },
        methods: {
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
                    banners: normalizeBanners(theme.banners),
                    // 开关与预览按数值严格比较，后端字符串形态需先归一
                    show_platform_brand: Number(theme.show_platform_brand) === BRAND_HIDE ? BRAND_HIDE : BRAND_SHOW
                })
                if (data.white_label !== undefined) this.whiteLabel = !!data.white_label
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
                if (index === NO_BANNER) this.form[field] = pc.att_dir
                else this.form.banners[index].image = pc.att_dir
                this.modalPic = false
            },
            clearPic (field) {
                this.form[field] = ''
            },
            resetColor () {
                this.form.theme_color = THEME_COLOR_DEFAULT
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
    .preview-tip {
        color: #808695;
        font-size: 12px;
        text-align: center;
        margin-bottom: 10px;
    }
    .preview-wrap {
        display: flex;
        justify-content: center;
        background: #f8f8f9;
        border-radius: 6px;
        padding: 20px 0;
    }
    .chat-window {
        width: 360px;
        max-width: 100%;
        height: 560px;
        display: flex;
        flex-direction: column;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 6px 24px rgba(0, 0, 0, 0.14);
        overflow: hidden;
    }
    .chat-header {
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
        padding: 14px;
        background: #f5f7fa;
        overflow: hidden;
    }
    .chat-msg {
        display: flex;
        margin-bottom: 12px;
    }
    .chat-msg-self {
        justify-content: flex-end;
    }
    .chat-bubble {
        max-width: 76%;
        padding: 8px 12px;
        border-radius: 8px;
        background: #fff;
        color: #515a6e;
        font-size: 13px;
        line-height: 1.6;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
    }
    .chat-msg-self .chat-bubble {
        color: #fff;
    }
    .chat-footer {
        display: flex;
        align-items: center;
        padding: 10px 12px;
        border-top: 1px solid #e8eaec;
    }
    .chat-input {
        flex: 1;
        height: 34px;
        line-height: 34px;
        padding: 0 10px;
        border: 1px solid #dcdee2;
        border-radius: 4px;
        color: #c5c8ce;
        font-size: 13px;
    }
    .chat-send {
        margin-left: 10px;
        padding: 0 16px;
        height: 34px;
        line-height: 34px;
        border-radius: 4px;
        color: #fff;
        font-size: 13px;
    }
    .chat-brand {
        text-align: center;
        color: #c5c8ce;
        font-size: 11px;
        padding-bottom: 8px;
    }
</style>
