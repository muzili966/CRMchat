<template>
    <div>
        <div class="i-layout-page-header">
            <div class="i-layout-page-header">
                <span class="ivu-page-header-title">{{ $route.meta.title }}</span>
            </div>
        </div>

        <Card :bordered="false" dis-hover class="ivu-mt">
            <!-- 两种聚合：按会话看一次接待，按访客看一个客户的全部往来 -->
            <div class="hist-bar">
                <RadioGroup v-model="mode" type="button" @on-change="onModeChange">
                    <Radio label="session">按会话</Radio>
                    <Radio label="visitor">按访客</Radio>
                </RadioGroup>
                <div class="hist-filters">
                    <DatePicker v-model="dateRange" type="daterange" placeholder="按最后消息时间筛选"
                                style="width: 220px" @on-change="onDateChange"/>
                    <Select v-if="mode === 'session'" v-model="where.kefu_user_id" clearable
                            placeholder="全部客服" style="width: 150px" @on-change="reload">
                        <Option v-for="k in agents" :key="k.user_id" :value="k.user_id">{{ k.nickname }}</Option>
                    </Select>
                    <Input v-model="where.keyword" search enter-button="搜索" placeholder="访客昵称"
                           style="width: 200px" @on-search="reload"/>
                </div>
            </div>

            <!-- 会话视角 -->
            <Table v-if="mode === 'session'" :columns="sessionColumns" :data="list" :loading="loading"
                   no-data-text="暂无历史会话" @on-row-click="openSession">
                <template slot-scope="{ row }" slot="visitor">
                    <div class="cell-user">
                        <img class="cell-avatar" :src="row.avatar" @error="handleAvatarError">
                        <div>
                            <div class="cell-name">{{ row.visitor_name || '未命名访客' }}</div>
                            <Tag v-if="row.is_tourist" size="small">游客</Tag>
                        </div>
                    </div>
                </template>
                <template slot-scope="{ row }" slot="agent">
                    <span>{{ row.agent_name }}</span>
                    <Tag v-if="row.agent_is_ai" color="blue" size="small">AI</Tag>
                </template>
                <template slot-scope="{ row }" slot="last">
                    <span class="cell-msg">{{ row.message || '—' }}</span>
                </template>
                <template slot-scope="{ row }" slot="action">
                    <a @click.stop="openSession(row)">查看对话</a>
                </template>
            </Table>

            <!-- 访客视角 -->
            <Table v-else :columns="visitorColumns" :data="list" :loading="loading"
                   no-data-text="暂无访客" @on-row-click="openVisitor">
                <template slot-scope="{ row }" slot="visitor">
                    <div class="cell-user">
                        <img class="cell-avatar" :src="row.avatar" @error="handleAvatarError">
                        <div>
                            <div class="cell-name">{{ row.visitor_name || '未命名访客' }}</div>
                            <Tag v-if="row.is_tourist" size="small">游客</Tag>
                        </div>
                    </div>
                </template>
                <template slot-scope="{ row }" slot="action">
                    <a @click.stop="openVisitor(row)">查看往来</a>
                </template>
            </Table>

            <div class="hist-page">
                <Page :total="total" :current="where.page" :page-size="where.limit" show-total
                      @on-change="onPage"/>
            </div>
        </Card>

        <!-- 对话抽屉 -->
        <Drawer v-model="drawer" width="560" :title="drawerTitle">
            <!-- 访客视角先列出其全部会话，选一条再看内容 -->
            <div v-if="visitorSessions.length" class="sess-picker">
                <div v-for="s in visitorSessions" :key="s.id" class="sess-item"
                     :class="{ 'sess-item-on': current && current.agent_user_id === s.agent_user_id }"
                     @click="loadTranscript(s)">
                    <div class="sess-item-main">
                        <span>{{ s.agent_name }}</span>
                        <Tag v-if="s.agent_is_ai" color="blue" size="small">AI</Tag>
                    </div>
                    <span class="sess-item-time">{{ s._update_time }}</span>
                </div>
            </div>

            <div v-if="recordsLoading" class="chat-empty">加载中…</div>
            <div v-else-if="!records.length" class="chat-empty">暂无对话内容</div>
            <div v-else class="chat-box">
                <div v-for="m in records" :key="m.id" class="chat-row" :class="{ 'chat-row-agent': m.is_agent }">
                    <div class="chat-meta">{{ m.nickname }} · {{ m._add_time }}</div>
                    <div class="chat-bubble">
                        <template v-if="m.msn_type === 3">
                            <img class="chat-img" :src="m.msn">
                        </template>
                        <template v-else-if="m.msn_type === 7">
                            <chatFileCard :msn="m.msn"/>
                        </template>
                        <template v-else>
                            <span v-html="m.msn"></span>
                        </template>
                    </div>
                </div>
                <div v-if="records.length < recordTotal" class="chat-more">
                    <Button size="small" :loading="recordsLoading" @click="loadMore">加载更多消息</Button>
                </div>
            </div>
        </Drawer>
    </div>
</template>

<script>
    import {
        historySessionsApi, historyVisitorsApi, historyVisitorSessionsApi, historyRecordsApi
    } from '@/api/chatHistory'
    import { kefuListApi } from '@/api/setting'
    import { onAvatarError } from '@/libs/avatar'
    import chatFileCard from '@/components/chatFileCard'

    const RECORD_LIMIT = 30

    export default {
        name: 'chatHistory',
        components: { chatFileCard },
        data () {
            return {
                mode: 'session',
                loading: false,
                list: [],
                total: 0,
                agents: [],
                dateRange: [],
                where: { keyword: '', kefu_user_id: '', appid: '', start: '', end: '', page: 1, limit: 20 },
                drawer: false,
                drawerTitle: '对话内容',
                current: null,
                visitorSessions: [],
                records: [],
                recordTotal: 0,
                recordPage: 1,
                recordsLoading: false,
                sessionColumns: [
                    { title: '访客', slot: 'visitor', minWidth: 160 },
                    { title: '接待客服', slot: 'agent', width: 140 },
                    { title: '消息数', key: 'mssage_num', width: 90 },
                    { title: '最后一条', slot: 'last', minWidth: 180 },
                    { title: '最后时间', key: '_update_time', width: 150 },
                    { title: '操作', slot: 'action', width: 100 }
                ],
                visitorColumns: [
                    { title: '访客', slot: 'visitor', minWidth: 180 },
                    { title: '手机号', key: 'phone', width: 130 },
                    { title: '接待客服数', key: 'agent_num', width: 110 },
                    { title: '消息总数', key: 'msg_num', width: 100 },
                    { title: '最后活跃', key: '_last_time', width: 150 },
                    { title: '操作', slot: 'action', width: 100 }
                ]
            }
        },
        created () {
            this.getAgents()
            this.getList()
        },
        methods: {
            handleAvatarError (e) { onAvatarError(e) },
            onModeChange () {
                this.where.kefu_user_id = ''
                this.where.page = 1
                this.getList()
            },
            onDateChange (val) {
                this.where.start = val && val[0] ? val[0] : ''
                this.where.end = val && val[1] ? val[1] : ''
                this.reload()
            },
            reload () {
                this.where.page = 1
                this.getList()
            },
            onPage (page) {
                this.where.page = page
                this.getList()
            },
            getList () {
                this.loading = true
                const api = this.mode === 'session' ? historySessionsApi : historyVisitorsApi
                api(this.where).then(res => {
                    this.list = (res.data && res.data.list) || []
                    this.total = (res.data && res.data.count) || 0
                    this.loading = false
                }).catch(res => {
                    this.loading = false
                    this.$Message.error(res.msg)
                })
            },
            // 客服下拉：复用客服列表接口，只为筛选
            getAgents () {
                kefuListApi({ page: 1, limit: 100 }).then(res => {
                    this.agents = ((res.data && res.data.list) || []).filter(item => item.user_id)
                }).catch(() => {})
            },
            openSession (row) {
                this.visitorSessions = []
                this.drawerTitle = `${row.visitor_name || '访客'} × ${row.agent_name}`
                this.drawer = true
                this.loadTranscript(row)
            },
            // 访客视角：先拉他的全部会话，默认展开最近一条
            openVisitor (row) {
                this.drawerTitle = `${row.visitor_name || '访客'} 的全部往来`
                this.drawer = true
                this.records = []
                this.current = null
                historyVisitorSessionsApi(row.visitor_id).then(res => {
                    this.visitorSessions = res.data || []
                    if (this.visitorSessions.length) {
                        this.loadTranscript(this.visitorSessions[0])
                    }
                }).catch(res => this.$Message.error(res.msg))
            },
            loadTranscript (row) {
                this.current = row
                this.recordPage = 1
                this.records = []
                this.fetchRecords()
            },
            loadMore () {
                this.recordPage += 1
                this.fetchRecords(true)
            },
            fetchRecords (append = false) {
                if (!this.current) return
                this.recordsLoading = true
                historyRecordsApi({
                    agent_user_id: this.current.agent_user_id,
                    visitor_user_id: this.current.visitor_id,
                    page: this.recordPage,
                    limit: RECORD_LIMIT
                }).then(res => {
                    const list = (res.data && res.data.list) || []
                    //接口按时间正序返回，翻页取到的是更晚的消息，故追加在后
                    this.records = append ? [...this.records, ...list] : list
                    this.recordTotal = (res.data && res.data.count) || 0
                    this.recordsLoading = false
                }).catch(res => {
                    this.recordsLoading = false
                    this.$Message.error(res.msg)
                })
            }
        }
    }
</script>

<style scoped>
    .hist-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
    }
    .hist-filters {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .hist-page {
        margin-top: 16px;
        text-align: right;
    }
    .cell-user {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 6px 0;
    }
    .cell-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        flex: none;
    }
    .cell-name {
        color: #1f2d3d;
    }
    .cell-msg {
        color: #808695;
        display: inline-block;
        max-width: 260px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        vertical-align: middle;
    }
    .sess-picker {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding-bottom: 14px;
        margin-bottom: 14px;
        border-bottom: 1px solid #eef1f6;
    }
    .sess-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 12px;
        border: 1px solid #eef1f6;
        border-radius: 8px;
        cursor: pointer;
    }
    .sess-item-on {
        border-color: #2d8cf0;
        background: #f2f7ff;
    }
    .sess-item-main {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .sess-item-time {
        color: #a3aab8;
        font-size: 12px;
    }
    .chat-empty {
        color: #a3aab8;
        text-align: center;
        padding: 40px 0;
    }
    .chat-box {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    .chat-row {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }
    .chat-row-agent {
        align-items: flex-end;
    }
    .chat-meta {
        font-size: 12px;
        color: #a3aab8;
        margin-bottom: 4px;
    }
    .chat-bubble {
        max-width: 78%;
        padding: 9px 12px;
        border-radius: 10px;
        background: #f4f6fa;
        color: #1f2d3d;
        word-break: break-all;
    }
    .chat-row-agent .chat-bubble {
        background: #e8f1ff;
    }
    .chat-img {
        max-width: 180px;
        border-radius: 6px;
        display: block;
    }
    .chat-more {
        text-align: center;
        padding: 6px 0;
    }
</style>
