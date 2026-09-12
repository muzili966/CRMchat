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
                    <!-- 导出的是当前筛选条件下的全部对话，与翻到第几页无关；量大故走下载中心 -->
                    <Tooltip content="生成后到「设置管理 - 下载中心」下载" placement="top">
                        <Dropdown @on-click="exportAll">
                            <Button icon="ios-download-outline" :loading="!!exportingAll">
                                全局导出<Icon type="ios-arrow-down"/>
                            </Button>
                            <DropdownMenu slot="list">
                                <!-- 单表适合筛选透视，分包适合归档与单独交付 -->
                                <DropdownItem name="xlsx">单表Excel（便于分析）</DropdownItem>
                                <DropdownItem name="csv">单表CSV（便于导入）</DropdownItem>
                                <DropdownItem name="zip" divided>按访客打包ZIP（便于归档）</DropdownItem>
                            </DropdownMenu>
                        </Dropdown>
                    </Tooltip>
                </div>
            </div>

            <!-- 会话视角 -->
            <Table v-if="mode === 'session'" :columns="sessionColumns" :data="list" :loading="loading"
                   no-data-text="暂无历史对话" @on-row-click="openSession">
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
            <div v-if="current && current.merged" class="sess-hint">
                已合并该访客与全部客服（含AI）的往来，按时间先后排列
            </div>
            <!-- 访客视角先列出其全部会话，选一条再看内容；置顶一条合并全部客服的时间线 -->
            <div v-if="visitorSessions.length" class="sess-picker">
                <div class="sess-item sess-item-all" :class="{ 'sess-item-on': current && current.merged }"
                     @click="loadMerged()">
                    <div class="sess-item-main">
                        <Icon type="ios-git-merge"/>
                        <span>全部合并</span>
                    </div>
                    <span class="sess-item-time">{{ visitorSessions.length }}个客服</span>
                </div>
                <div v-for="s in visitorSessions" :key="s.id" class="sess-item"
                     :class="{ 'sess-item-on': current && !current.merged && current.agent_user_id === s.agent_user_id }"
                     @click="loadTranscript(s)">
                    <div class="sess-item-main">
                        <span>{{ s.agent_name }}</span>
                        <Tag v-if="s.agent_is_ai" color="blue" size="small">AI</Tag>
                    </div>
                    <span class="sess-item-time">{{ s._update_time }}</span>
                </div>
            </div>

            <div v-if="current" class="sess-export">
                <Button size="small" icon="ios-download-outline" :loading="exporting === 'xlsx'"
                        :disabled="!records.length" @click="exportChat('xlsx')">导出Excel</Button>
                <Button size="small" icon="ios-download-outline" :loading="exporting === 'csv'"
                        :disabled="!records.length" @click="exportChat('csv')">导出CSV</Button>
                <Tooltip content="所见即所得，便于举证时直接发给对方" placement="top">
                    <Button size="small" icon="ios-image-outline" :loading="shooting"
                            :disabled="!records.length" @click="exportShot">导出截图</Button>
                </Tooltip>
            </div>

            <div v-if="recordsLoading" class="chat-empty">加载中…</div>
            <div v-else-if="!records.length" class="chat-empty">暂无对话内容</div>
            <div v-else ref="chatBox" class="chat-box">
                <template v-for="(m, i) in records">
                <!-- 合并视图里接待方会中途变化（AI转人工、换客服），插条分隔线才看得出接力 -->
                <div v-if="isHandover(i)" :key="'hand-' + m.id" class="chat-handover">
                    <span>{{ m.agent_name }}</span>
                    <Tag v-if="m.is_ai" color="blue" size="small">AI</Tag>
                    <span class="chat-handover-tip">接待</span>
                </div>
                <div :key="m.id" class="chat-row" :class="{ 'chat-row-agent': m.is_agent }">
                    <div class="chat-meta">{{ m.nickname }} · {{ m._add_time }}</div>
                    <div class="chat-bubble">
                        <template v-if="m.msn_type === 3">
                            <img class="chat-img" :src="m.msn">
                        </template>
                        <template v-else-if="m.msn_type === 7">
                            <chatFileCard :msn="m.msn"/>
                        </template>
                        <template v-else-if="m.msn_type === 9">
                            <chatFaqCard :msn="m.msn" readonly/>
                        </template>
                        <template v-else>
                            <span v-html="m.msn"></span>
                        </template>
                    </div>
                </div>
                </template>
                <div v-if="records.length < recordTotal" class="chat-more">
                    <Button size="small" :loading="recordsLoading" @click="loadMore">加载更多消息</Button>
                </div>
            </div>
        </Drawer>
    </div>
</template>

<script>
    import {
        historySessionsApi, historyVisitorsApi, historyVisitorSessionsApi, historyRecordsApi,
        historyExportApi, historyExportAllApi, historyVisitorRecordsApi, historyVisitorExportApi
    } from '@/api/chatHistory'
    import { kefuListApi } from '@/api/setting'
    import { onAvatarError } from '@/libs/avatar'
    import chatFileCard from '@/components/chatFileCard'
    import chatFaqCard from '@/components/chatFaqCard'
  import { captureChat, MAX_SHOT_RECORDS } from '@/libs/chatShot'

    const RECORD_LIMIT = 30

    export default {
        name: 'chatHistory',
        components: { chatFileCard, chatFaqCard },
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
                visitorId: 0,
                visitorSessions: [],
                records: [],
                recordTotal: 0,
                recordPage: 1,
                recordsLoading: false,
                exporting: '',
        shooting: false,
                exportingAll: '',
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
                this.visitorId = 0
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
                this.visitorId = row.visitor_id
                historyVisitorSessionsApi(row.visitor_id).then(res => {
                    this.visitorSessions = res.data || []
                    if (this.visitorSessions.length) {
                        //默认给合并视图：访客视角关心的是整件事怎么走的，而不是某个客服单独说了什么
                        this.loadMerged()
                    }
                }).catch(res => this.$Message.error(res.msg))
            },
            // 合并全部客服的时间线；用一条伪会话占住 current，复用抽屉的加载与导出
            loadMerged () {
                this.loadTranscript({ merged: true, visitor_id: this.visitorId })
            },
            loadTranscript (row) {
                this.current = row
                this.recordPage = 1
                this.records = []
                this.exporting = ''
                this.fetchRecords()
            },
            //单会话与全局导出共用的落地处理：提示 + 打开下载
            handleExport (request, flag, format) {
                this[flag] = format
                request.then(res => {
                    this[flag] = ''
                    this.$Message.success(res.msg)
                    if (res.data && res.data.url) {
                        window.open(location.origin + res.data.url)
                    }
                }).catch(res => {
                    this[flag] = ''
                    this.$Message.error(res.msg)
                })
            },
            //导出的是整段会话，与当前翻到第几页无关
            exportChat (format) {
                if (!this.current) return
                const request = this.current.merged
                    ? historyVisitorExportApi({ visitor_user_id: this.current.visitor_id, format })
                    : historyExportApi({
                        agent_user_id: this.current.agent_user_id,
                        visitor_user_id: this.current.visitor_id,
                        format
                    })
                this.handleExport(request, 'exporting', format)
            },
            //举证要的是完整记录，所以先把整段对话拉全再截，不能只截翻到的那部分
            async exportShot () {
                if (!this.current || this.shooting) return
                if (this.recordTotal > MAX_SHOT_RECORDS) {
                    this.$Message.warning(`本段共 ${this.recordTotal} 条，超过 ${MAX_SHOT_RECORDS} 条截图会卡死浏览器，请改用 Excel 导出`)
                    return
                }
                this.shooting = true
                try {
                    await this.loadAllRecords()
                    //DOM 要等这批消息真正渲染完，否则截到的还是旧内容
                    await this.$nextTick()
                    const pages = await captureChat(this.$refs.chatBox, {
                        title: this.drawerTitle,
                        subtitle: `共 ${this.records.length} 条，完整对话`,
                        filename: 'chat_' + (this.current.visitor_id || '') + '_' + Date.now()
                    })
                    this.$Message.success(pages > 1 ? `内容较长，已分为 ${pages} 张图片` : '截图已保存')
                } catch (e) {
                    this.$Message.error((e && e.message) || '截图失败')
                }
                this.shooting = false
            },
            //从头整段取回：已加载的那部分是按 30 条一页翻的，接着追加会错页
            async loadAllRecords () {
                const limit = 100
                let all = []
                for (let page = 1; page <= 100; page++) {
                    const { list, count } = await this.requestRecords(page, limit)
                    all = all.concat(list)
                    this.recordTotal = count
                    if (list.length < limit || all.length >= count) break
                }
                this.records = all
                this.recordPage = Math.ceil(all.length / RECORD_LIMIT) || 1
            },
            // 上一条由不同客服应答即为一次接力；首条也要标出接待方
            isHandover (index) {
                if (!this.current || !this.current.merged) return false
                if (index === 0) return true
                return this.records[index].agent_user_id !== this.records[index - 1].agent_user_id
            },
            //导出当前筛选条件下的全部会话明细，走下载中心异步产出
            exportAll (format) {
                this.exportingAll = format
                historyExportAllApi({ ...this.where, format }).then(res => {
                    this.exportingAll = ''
                    this.$Message.success(res.msg)
                }).catch(res => {
                    this.exportingAll = ''
                    this.$Message.error(res.msg)
                })
            },
            loadMore () {
                this.recordPage += 1
                this.fetchRecords(true)
            },
            //取一页对话，翻页与全量截图共用
            requestRecords (page, limit) {
                const params = { visitor_user_id: this.current.visitor_id, page, limit }
                const request = this.current.merged
                    ? historyVisitorRecordsApi(params)
                    : historyRecordsApi({ ...params, agent_user_id: this.current.agent_user_id })
                return request.then(res => ({
                    list: (res.data && res.data.list) || [],
                    count: (res.data && res.data.count) || 0
                }))
            },
            fetchRecords (append = false) {
                if (!this.current) return Promise.resolve()
                this.recordsLoading = true
                return this.requestRecords(this.recordPage, RECORD_LIMIT).then(({ list, count }) => {
                    //接口按时间正序返回，翻页取到的是更晚的消息，故追加在后
                    this.records = append ? [...this.records, ...list] : list
                    this.recordTotal = count
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
    .sess-item-all {
        font-weight: 500;
    }
    .sess-hint {
        margin-bottom: 10px;
        padding: 6px 10px;
        background: #f0f7ff;
        color: #2d8cf0;
        border-radius: 4px;
        font-size: 12px;
    }
    .chat-handover {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        margin: 14px 0 8px;
        color: #808695;
        font-size: 12px;
    }
    .chat-handover::before,
    .chat-handover::after {
        content: '';
        flex: 1;
        height: 1px;
        background: #e8eaec;
    }
    .chat-handover-tip {
        color: #c5c8ce;
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
    .sess-export {
        display: flex;
        gap: 8px;
        padding-bottom: 12px;
        margin-bottom: 12px;
        border-bottom: 1px solid #eef1f6;
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
