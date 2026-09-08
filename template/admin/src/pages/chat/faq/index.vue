<template>
    <div>
        <div class="i-layout-page-header">
            <div class="i-layout-page-header">
                <span class="ivu-page-header-title">{{ $route.meta.title }}</span>
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
                <span class="app-bar-tip">访客进入新会话时弹出，最多展示 {{ cardLimit }} 条</span>
                <Button type="primary" icon="md-add" class="app-bar-add" :disabled="!appid" @click="openForm(null)">
                    添加问题
                </Button>
            </div>
            <div class="app-empty" v-if="!appList.length">暂无应用，请先在「应用管理」中添加应用</div>

            <Table v-else :columns="columns" :data="list" :loading="loading" no-data-text="还没有常见问题">
                <template slot-scope="{ row }" slot="title">
                    <div class="faq-title">{{ row.title }}</div>
                    <div class="faq-keyword">关键词：{{ row.keyword || '—' }}</div>
                </template>
                <template slot-scope="{ row }" slot="content">
                    <span class="faq-content">{{ row.content }}</span>
                </template>
                <template slot-scope="{ row }" slot="is_faq">
                    <i-switch :value="!!row.is_faq" @on-change="toggleCard(row, $event)"/>
                </template>
                <template slot-scope="{ row, index }" slot="action">
                    <a @click="openForm(row)">编辑</a>
                    <Divider type="vertical"/>
                    <a :class="{ 'act-off': index === 0 }" @click="move(index, -1)">上移</a>
                    <Divider type="vertical"/>
                    <a :class="{ 'act-off': index === list.length - 1 }" @click="move(index, 1)">下移</a>
                    <Divider type="vertical"/>
                    <a class="act-del" @click="remove(row)">删除</a>
                </template>
            </Table>

            <div class="faq-page" v-if="total > where.limit">
                <Page :total="total" :current="where.page" :page-size="where.limit" show-total @on-change="onPage"/>
            </div>
        </Card>

        <Modal v-model="formShow" :title="form.id ? '编辑问题' : '添加问题'" @on-ok="submit" :loading="saving">
            <Form :label-width="80">
                <FormItem label="问题">
                    <Input v-model="form.title" :maxlength="titleMax" placeholder="卡片上展示的问句，如「如何申请退款？」"/>
                </FormItem>
                <FormItem label="关键词">
                    <Input v-model="form.keyword" placeholder="留空则用问题本身；多个关键词用逗号隔开"/>
                    <div class="form-tip">访客打字命中关键词时，会得到与点卡片相同的答案</div>
                </FormItem>
                <FormItem label="答案">
                    <Input v-model="form.content" type="textarea" :rows="5" placeholder="访客点击后收到的回复内容"/>
                </FormItem>
                <FormItem label="展示在卡片">
                    <i-switch v-model="form.is_faq"/>
                    <div class="form-tip">关闭后仍可作为关键词自动回复生效，只是不出现在卡片上</div>
                </FormItem>
            </Form>
        </Modal>
    </div>
</template>

<script>
    import { faqListApi, faqSaveApi, faqUpdateApi, faqDeleteApi, faqSortApi } from '@/api/chatFaq'
    import { appListApi } from '@/api/application'

    const APP_LIMIT = 100
    //与后端 ChatFaqServices::CARD_LIMIT / TITLE_MAX 对齐
    const CARD_LIMIT = 8
    const TITLE_MAX = 255

    export default {
        name: 'chatFaq',
        data () {
            return {
                appid: '',
                appList: [],
                loading: false,
                //常真：Modal 处于 loading 模式才不会点确定就自动关窗，
                //关窗由提交成功后显式置 formShow 控制
                saving: true,
                list: [],
                total: 0,
                where: { page: 1, limit: 20 },
                formShow: false,
                form: this.emptyForm(),
                cardLimit: CARD_LIMIT,
                titleMax: TITLE_MAX,
                columns: [
                    { title: '问题', slot: 'title', minWidth: 200 },
                    { title: '答案', slot: 'content', minWidth: 240 },
                    { title: '展示在卡片', slot: 'is_faq', width: 110 },
                    { title: '操作', slot: 'action', width: 220 }
                ]
            }
        },
        created () {
            this.getAppList()
        },
        methods: {
            emptyForm () {
                return { id: 0, title: '', keyword: '', content: '', is_faq: true }
            },
            getAppList () {
                appListApi({ page: 1, limit: APP_LIMIT }).then(res => {
                    this.appList = (res.data && res.data.list) || []
                    if (!this.appList.length) return
                    this.appid = this.appList[0].appid
                    this.getList()
                }).catch(res => this.$Message.error(res.msg))
            },
            onAppChange () {
                this.where.page = 1
                this.getList()
            },
            onPage (page) {
                this.where.page = page
                this.getList()
            },
            getList () {
                if (!this.appid) return
                this.loading = true
                faqListApi({ ...this.where, appid: this.appid }).then(res => {
                    this.list = (res.data && res.data.list) || []
                    this.total = (res.data && res.data.count) || 0
                    this.loading = false
                }).catch(res => {
                    this.loading = false
                    this.$Message.error(res.msg)
                })
            },
            openForm (row) {
                this.form = row
                    ? { id: row.id, title: row.title, keyword: row.keyword, content: row.content, is_faq: !!row.is_faq }
                    : this.emptyForm()
                this.formShow = true
            },
            submit () {
                const data = {
                    appid: this.appid,
                    title: this.form.title,
                    keyword: this.form.keyword,
                    content: this.form.content,
                    is_faq: this.form.is_faq ? 1 : 0
                }
                if (!data.title || !data.content) {
                    this.$Message.error('问题和答案都要填')
                    //Modal 的 loading 模式下必须手动收起，否则按钮一直转圈
                    this.saving = false
                    this.$nextTick(() => { this.saving = true })
                    return
                }
                const req = this.form.id ? faqUpdateApi(this.form.id, data) : faqSaveApi(data)
                req.then(res => {
                    this.$Message.success(res.msg)
                    this.formShow = false
                    this.getList()
                }).catch(res => {
                    this.$Message.error(res.msg)
                    this.saving = false
                    this.$nextTick(() => { this.saving = true })
                })
            },
            toggleCard (row, value) {
                faqUpdateApi(row.id, {
                    appid: this.appid,
                    title: row.title,
                    keyword: row.keyword,
                    content: row.content,
                    is_faq: value ? 1 : 0
                }).then(() => {
                    row.is_faq = value ? 1 : 0
                }).catch(res => {
                    this.$Message.error(res.msg)
                    this.getList()
                })
            },
            //相邻交换后整页重排，比单条改 sort 更不容易出现并列值
            move (index, step) {
                const target = index + step
                if (target < 0 || target >= this.list.length) return
                const list = [...this.list]
                const tmp = list[index]
                list[index] = list[target]
                list[target] = tmp
                this.list = list
                faqSortApi({ appid: this.appid, ids: list.map(item => item.id) })
                    .catch(res => {
                        this.$Message.error(res.msg)
                        this.getList()
                    })
            },
            remove (row) {
                this.$Modal.confirm({
                    title: '删除确认',
                    content: `确定删除「${row.title}」吗？删除后访客端卡片与关键词回复都将不再生效。`,
                    onOk: () => {
                        faqDeleteApi(row.id, this.appid).then(res => {
                            this.$Message.success(res.msg)
                            this.getList()
                        }).catch(res => this.$Message.error(res.msg))
                    }
                })
            }
        }
    }
</script>

<style scoped>
    .app-bar {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }
    .app-bar-label {
        color: #515a6e;
    }
    .app-bar-select {
        width: 280px;
    }
    .app-bar-tip {
        color: #a3aab8;
        font-size: 12px;
    }
    .app-bar-add {
        margin-left: auto;
    }
    .app-empty {
        color: #a3aab8;
        padding: 40px 0;
        text-align: center;
    }
    .faq-title {
        color: #1f2d3d;
        padding-top: 6px;
    }
    .faq-keyword {
        color: #a3aab8;
        font-size: 12px;
        padding-bottom: 6px;
    }
    .faq-content {
        color: #515a6e;
        display: inline-block;
        max-width: 420px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        vertical-align: middle;
    }
    .faq-page {
        margin-top: 16px;
        text-align: right;
    }
    .act-del {
        color: #ed4014;
    }
    .act-off {
        color: #c5c8ce;
        cursor: not-allowed;
    }
    .form-tip {
        color: #a3aab8;
        font-size: 12px;
        line-height: 1.6;
        margin-top: 4px;
    }
</style>
