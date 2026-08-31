<template>
    <div class="content">
        <p class="font-w">使用简介</p>
        <p class="text-i">A链接可以在网页中或内容中添加A链接使用，可以在自己站点使用，也可以发布的外站使用，或者自己生成链接二维码使用;</p>
        <p class="font-w">超链接参数说明</p>
                <p class="text-i">
                    token:与后台交互的凭证
                    <br>noCanClose:PC端是否显示广告
                <br>deviceType:强制指定终端，Mobile移动版 / pc桌面版，不传则自动识别
                <br>uid:用户ID
                <br>nickName:用户昵称
                <br>phone:用户手机号
                <br>sex:用户性别
                <br>avatar:用户头像
                <br>openid:用户openid
                <br>kefu_id:客服ID
                </p>
        <Divider />
        <p class="typetitle">接入链接</p>
        <p class="text-i">同一个地址即可，页面会按访问设备自动切换桌面版与移动版，无需区分终端。</p>

        <div class="fenlei">
            <div class="code-content-wrap">
        <!-- 站点域名+32位token拼出的链接较长，窄屏会软换行，留2行避免被截 -->
        <textarea id="NormalCodeTextarealink1" class="code" rows="2">{{siteUrl}}/chat?noCanClose=1&token={{tokeninfo.token_md5}}</textarea>
                <div class="other-wrap">
                    <a class="btn btn-blue btn-large mr10" :href="linkUrl" target="_blank">点击体验</a>
                    <a @click="getCopy('NormalCodeTextarealink1')" class="btn btn-blue btn-large" href="javascript:void(0);"><span>复制代码</span></a>
                </div>
            </div>
        </div>

        <p class="typetitle">指定终端（可选）</p>
        <p class="text-i">仅在需要强制某一版界面时使用，例如在移动端页面内嵌桌面版。追加 deviceType 参数即可，无需换地址。</p>
        <div class="fenlei">
            <div class="code-content-wrap">
        <textarea id="NormalCodeTextarealink2" class="code" rows="2">{{siteUrl}}/chat?noCanClose=1&deviceType=Mobile&token={{tokeninfo.token_md5}}</textarea>
                <div class="other-wrap">
                    <a class="btn btn-blue btn-large mr10" :href="linkUrlMobile" target="_blank">点击体验</a>
                    <a @click="getCopy('NormalCodeTextarealink2')" class="btn btn-blue btn-large" href="javascript:void(0);"><span>复制代码</span></a>
                </div>
            </div>
        </div>

        <div  class="fenlei">
            <p class="font-w">小贴士</p>
            <p class="text-i">如果点击体验，提示客服不在线，请进入客服点击进入客服登录一个账号再试。</p>
            <p class="text-i">如需更换token，请在设置中重新获取。</p>

        </div>
    </div>
</template>
<script>

export default{
    name: 'alink',

    props: {
        tokeninfo:{},
        siteUrl:'',
    },
    computed: {
        linkUrl() {
            return `${location.origin}/chat?token=${this.tokeninfo.token_md5}&noCanClose=1`;
        },
        linkUrlMobile() {
            return `${this.linkUrl}&deviceType=Mobile`;
        }
    },
    mounted() {

    },
    methods: {
        getCopy(id) {
            this.$emit('cgetCopy',id);
        },
    }
}
</script>

