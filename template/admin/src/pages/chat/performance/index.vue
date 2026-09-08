<template>
    <div>
        <div class="i-layout-page-header">
            <div class="i-layout-page-header">
                <span class="ivu-page-header-title">{{ $route.meta.title }}</span>
            </div>
        </div>

        <Card :bordered="false" dis-hover class="ivu-mt">
            <div class="pf-bar">
                <RadioGroup v-model="quick" type="button" @on-change="onQuick">
                    <Radio label="today">今天</Radio>
                    <Radio label="7">近7天</Radio>
                    <Radio label="30">近30天</Radio>
                </RadioGroup>
                <div class="pf-filters">
                    <DatePicker v-model="dateRange" type="daterange" placeholder="自定义区间"
                                style="width: 220px" @on-change="onDateChange"/>
                    <Select v-model="where.kefu_user_id" clearable placeholder="全部客服"
                            style="width: 160px" @on-change="loadAll">
                        <Option v-for="k in agentOptions" :key="k.user_id" :value="k.user_id">{{ k.nickname }}</Option>
                    </Select>
                </div>
            </div>

            <Row :gutter="16" class="pf-cards">
                <Col v-for="card in cards" :key="card.key" :xs="12" :sm="8" :md="6" :lg="4">
                    <div class="pf-card">
                        <div class="pf-card-label">
                            {{ card.label }}
                            <Tooltip v-if="card.tip" :content="card.tip" placement="top" max-width="260">
                                <Icon type="ios-help-circle-outline"/>
                            </Tooltip>
                        </div>
                        <div class="pf-card-value">
                            {{ card.value }}<small v-if="card.unit">{{ card.unit }}</small>
                        </div>
                    </div>
                </Col>
            </Row>
        </Card>

        <Card :bordered="false" dis-hover class="ivu-mt" title="接待趋势">
            <div ref="trend" class="pf-chart"></div>
        </Card>

        <Card :bordered="false" dis-hover class="ivu-mt" title="客服绩效">
            <Table :columns="agentColumns" :data="agents" :loading="loading" no-data-text="该区间内没有接待记录">
                <template slot-scope="{ row }" slot="agent">
                    <span>{{ row.nickname }}</span>
                    <Tag v-if="row.is_ai" color="blue" size="small">AI</Tag>
                </template>
                <template slot-scope="{ row }" slot="first">
                    <span :class="costClass(row.first_reply_avg)">{{ formatCost(row.first_reply_avg) }}</span>
                </template>
                <template slot-scope="{ row }" slot="reply">
                    <span :class="costClass(row.reply_avg)">{{ formatCost(row.reply_avg) }}</span>
                </template>
                <template slot-scope="{ row }" slot="rate">
                    <span v-if="row.rated">{{ row.rate_avg }} 分 <small class="pf-sub">({{ row.rated }}条)</small></span>
                    <span v-else class="pf-sub">暂无评价</span>
                </template>
                <template slot-scope="{ row }" slot="transfer">
                    <span v-if="row.is_ai">{{ row.transfer_rate }}%</span>
                    <span v-else class="pf-sub">—</span>
                </template>
            </Table>
        </Card>

        <Card :bordered="false" dis-hover class="ivu-mt" title="会话明细">
            <Table :columns="sessionColumns" :data="sessions.list" :loading="sessionLoading"
                   no-data-text="该区间内没有会话">
                <template slot-scope="{ row }" slot="duration">
                    <span>{{ formatCost(row.duration) }}</span>
                </template>
                <template slot-scope="{ row }" slot="first">
                    <span v-if="row.first_reply_cost" :class="costClass(row.first_reply_cost)">
                        {{ formatCost(row.first_reply_cost) }}
                    </span>
                    <span v-else class="pf-warn">未回复</span>
                </template>
                <template slot-scope="{ row }" slot="rate">
                    <Rate v-if="row.rate" :value="row.rate" disabled/>
                    <span v-else class="pf-sub">未评价</span>
                </template>
            </Table>
            <div class="pf-page">
                <Page :total="sessions.count" :current="sessionPage" :page-size="20" show-total
                      @on-change="onSessionPage"/>
            </div>
        </Card>
    </div>
</template>

<script>
    import echarts from 'echarts'
    import {
        performanceOverviewApi, performanceAgentsApi, performanceTrendApi, performanceSessionsApi
    } from '@/api/performance'
    import { kefuListApi } from '@/api/setting'

    //响应时长的健康阈值（秒）：超过就着色提醒，免得一屏数字看不出好坏
    const COST_WARN = 60
    const COST_DANGER = 180

    export default {
        name: 'chatPerformance',
        data () {
            return {
                loading: false,
                sessionLoading: false,
                quick: '7',
                dateRange: [],
                where: { start: '', end: '', kefu_user_id: '' },
                overview: {},
                agents: [],
                agentOptions: [],
                sessions: { list: [], count: 0 },
                sessionPage: 1,
                chart: null,
                agentColumns: [
                    { title: '客服', slot: 'agent', minWidth: 150 },
                    { title: '接待会话', key: 'sessions', width: 110 },
                    { title: '应答率', key: 'reply_rate', width: 100,
                        render: (h, p) => h('span', p.row.reply_rate + '%') },
                    { title: '平均首响', slot: 'first', width: 110 },
                    { title: '平均响应', slot: 'reply', width: 110 },
                    { title: '满意度', slot: 'rate', width: 150 },
                    { title: '发出消息', key: 'kefu_msgs', width: 100 },
                    { title: '转人工率', slot: 'transfer', width: 110 }
                ],
                sessionColumns: [
                    { title: '客服', key: 'agent_name', minWidth: 130 },
                    { title: '访客', key: 'visitor_name', minWidth: 130 },
                    { title: '开始时间', key: '_start_time', width: 170 },
                    { title: '时长', slot: 'duration', width: 100 },
                    { title: '首响', slot: 'first', width: 110 },
                    { title: '访客/客服消息', width: 130,
                        render: (h, p) => h('span', `${p.row.visitor_msg_num} / ${p.row.kefu_msg_num}`) },
                    { title: '满意度', slot: 'rate', width: 150 }
                ]
            }
        },
        computed: {
            cards () {
                const o = this.overview
                return [
                    { key: 'sessions', label: '接待会话', value: o.sessions || 0 },
                    { key: 'reply_rate', label: '应答率', value: o.reply_rate || 0, unit: '%',
                        tip: '有客服回复过的会话占比，未回复的会话不计入平均首响' },
                    { key: 'first', label: '平均首响', value: this.formatCost(o.first_reply_avg),
                        tip: '访客首次提问到客服第一次回复的间隔，只统计有回复的会话' },
                    { key: 'reply', label: '平均响应', value: this.formatCost(o.reply_avg),
                        tip: '每次回复距上一条待回复访客消息的间隔均值' },
                    { key: 'rate', label: '满意度', value: o.rated ? o.rate_avg : '—',
                        tip: `共 ${o.rated || 0} 条评价，评价率 ${o.rate_rate || 0}%` },
                    { key: 'transfer', label: 'AI转人工率', value: o.ai_sessions ? o.transfer_rate : '—', unit: o.ai_sessions ? '%' : '',
                        tip: `AI接待 ${o.ai_sessions || 0} 场，其中 ${o.transferred || 0} 场转了人工` }
                ]
            }
        },
        created () {
            this.applyQuick('7')
            this.getAgentOptions()
            this.loadAll()
        },
        beforeDestroy () {
            if (this.chart) {
                this.chart.dispose()
                this.chart = null
            }
        },
        methods: {
            //秒数转人读格式，图表与表格共用
            formatCost (seconds) {
                const s = Number(seconds) || 0
                if (!s) return '—'
                if (s < 60) return s + ' 秒'
                if (s < 3600) return Math.floor(s / 60) + ' 分' + (s % 60 ? (s % 60) + ' 秒' : '')
                return (s / 3600).toFixed(1) + ' 小时'
            },
            costClass (seconds) {
                const s = Number(seconds) || 0
                if (!s) return ''
                if (s >= COST_DANGER) return 'pf-danger'
                if (s >= COST_WARN) return 'pf-warn'
                return 'pf-good'
            },
            applyQuick (value) {
                const end = new Date()
                const start = new Date()
                if (value !== 'today') {
                    start.setDate(start.getDate() - (Number(value) - 1))
                }
                const fmt = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
                this.where.start = fmt(start)
                this.where.end = fmt(end)
                this.dateRange = []
            },
            onQuick (value) {
                this.applyQuick(value)
                this.loadAll()
            },
            onDateChange (val) {
                if (!val || !val[0] || !val[1]) return
                this.where.start = val[0]
                this.where.end = val[1]
                this.quick = ''
                this.loadAll()
            },
            getAgentOptions () {
                kefuListApi({ page: 1, limit: 100 }).then(res => {
                    this.agentOptions = ((res.data && res.data.list) || []).filter(item => item.user_id)
                }).catch(() => {})
            },
            loadAll () {
                this.sessionPage = 1
                this.loading = true
                performanceOverviewApi(this.where).then(res => {
                    this.overview = res.data || {}
                }).catch(res => this.$Message.error(res.msg))
                performanceAgentsApi(this.where).then(res => {
                    this.agents = res.data || []
                    this.loading = false
                }).catch(res => {
                    this.loading = false
                    this.$Message.error(res.msg)
                })
                performanceTrendApi(this.where).then(res => {
                    this.renderTrend(res.data || [])
                }).catch(res => this.$Message.error(res.msg))
                this.getSessions()
            },
            getSessions () {
                this.sessionLoading = true
                performanceSessionsApi({ ...this.where, page: this.sessionPage, limit: 20 }).then(res => {
                    this.sessions = res.data || { list: [], count: 0 }
                    this.sessionLoading = false
                }).catch(res => {
                    this.sessionLoading = false
                    this.$Message.error(res.msg)
                })
            },
            onSessionPage (page) {
                this.sessionPage = page
                this.getSessions()
            },
            renderTrend (list) {
                if (!this.$refs.trend) return
                if (!this.chart) {
                    this.chart = echarts.init(this.$refs.trend)
                }
                this.chart.setOption({
                    tooltip: { trigger: 'axis' },
                    legend: { data: ['接待会话', '平均首响(秒)'] },
                    grid: { left: 50, right: 50, top: 40, bottom: 40 },
                    xAxis: { type: 'category', data: list.map(item => item.day) },
                    //两个指标量纲差得远，各用一条轴，否则会话数会被压成一条直线
                    yAxis: [
                        { type: 'value', name: '会话', minInterval: 1 },
                        { type: 'value', name: '秒' }
                    ],
                    series: [
                        { name: '接待会话', type: 'bar', data: list.map(item => item.sessions), itemStyle: { color: '#2d8cf0' } },
                        { name: '平均首响(秒)', type: 'line', yAxisIndex: 1, smooth: true,
                            data: list.map(item => item.first_reply_avg), itemStyle: { color: '#ff9900' } }
                    ]
                }, true)
            }
        }
    }
</script>

<style scoped>
    .pf-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }
    .pf-filters {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .pf-cards {
        margin-top: 20px;
    }
    .pf-card {
        padding: 14px 16px;
        border: 1px solid #eef1f6;
        border-radius: 8px;
        margin-bottom: 12px;
    }
    .pf-card-label {
        color: #808695;
        font-size: 13px;
        margin-bottom: 6px;
    }
    .pf-card-value {
        font-size: 22px;
        color: #1f2d3d;
        font-weight: 500;
    }
    .pf-card-value small {
        font-size: 13px;
        margin-left: 2px;
    }
    .pf-chart {
        width: 100%;
        height: 320px;
    }
    .pf-page {
        margin-top: 16px;
        text-align: right;
    }
    .pf-sub {
        color: #a3aab8;
    }
    .pf-good {
        color: #19be6b;
    }
    .pf-warn {
        color: #ff9900;
    }
    .pf-danger {
        color: #ed4014;
    }
</style>
