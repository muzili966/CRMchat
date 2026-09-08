<template>
    <div>
        <div class="i-layout-page-header">
            <div class="i-layout-page-header">
                <span class="ivu-page-header-title">{{ $route.meta.title }}</span>
            </div>
        </div>

        <Card :bordered="false" dis-hover class="ivu-mt">
            <div class="sh-bar">
                <div class="sh-filters">
                    <Input v-model="where.keyword" search enter-button="搜索" placeholder="命中词"
                           style="width: 180px" @on-search="reload"/>
                    <Select v-model="where.scope" clearable placeholder="全部来源" style="width: 130px"
                            @on-change="reload">
                        <Option v-for="s in scopeOptions" :key="s.value" :value="s.value">{{ s.label }}</Option>
                    </Select>
                    <Select v-model="where.handled" clearable placeholder="全部状态" style="width: 130px"
                            @on-change="reload">
                        <Option :value="0">待处理</Option>
                        <Option :value="1">已处理</Option>
                    </Select>
                    <DatePicker v-model="dateRange" type="daterange" placeholder="按时间筛选"
                                style="width: 210px" @on-change="onDateChange"/>
                </div>
                <div v-if="pending" class="sh-pending">
                    待处理 <b>{{ pending }}</b> 条
                </div>
            </div>

            <Table :columns="columns" :data="list" :loading="loading" no-data-text="暂无命中记录">
                <template slot-scope="{ row }" slot="word">
                    <span>{{ row.word }}</span>
                    <!-- 平台合规词租户改不了，标出来免得租户去词库里找 -->
                    <Tag v-if="row.is_platform" color="purple" size="small">平台</Tag>
                </template>
                <template slot-scope="{ row }" slot="content">
                    <Tooltip :content="row.content" placement="top" max-width="420">
                        <span class="sh-content">{{ row.content }}</span>
                    </Tooltip>
                </template>
                <template slot-scope="{ row }" slot="handled">
                    <Tag :color="row.handled ? 'green' : 'orange'">{{ row.handled ? '已处理' : '待处理' }}</Tag>
                </template>
                <template slot-scope="{ row }" slot="handle">
                    <a v-if="!row.handled" @click="markHandled(row)">标记已处理</a>
                    <span v-else class="sh-done">已处理</span>
                </template>
            </Table>

            <div class="sh-page">
                <Page :total="total" :current="where.page" :page-size="where.limit" show-total
                      @on-change="onPage"/>
            </div>
        </Card>
    </div>
</template>

<script>
    import { sensitiveHitListApi, sensitiveHitHandleApi } from '@/api/sensitive'
    import { SCOPE_OPTIONS } from '@/config/sensitive'

    export default {
        name: 'sensitiveHit',
        data () {
            return {
                loading: false,
                list: [],
                total: 0,
                pending: 0,
                dateRange: [],
                scopeOptions: SCOPE_OPTIONS,
                where: { keyword: '', scope: '', handled: '', start: '', end: '', page: 1, limit: 20 },
                columns: [
                    { title: '命中词', slot: 'word', width: 150 },
                    { title: '来源', key: 'scope_text', width: 90 },
                    { title: '处置', key: 'action_text', width: 90 },
                    { title: '发送方', key: 'nickname', width: 130 },
                    { title: '原文', slot: 'content', minWidth: 220 },
                    { title: '时间', key: '_create_time', width: 160 },
                    { title: '状态', slot: 'handled', width: 100 },
                    { title: '操作', slot: 'handle', width: 110 }
                ]
            }
        },
        created () {
            this.getList()
        },
        methods: {
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
                sensitiveHitListApi(this.where).then(res => {
                    this.list = (res.data && res.data.list) || []
                    this.total = (res.data && res.data.count) || 0
                    this.pending = (res.data && res.data.pending) || 0
                    this.loading = false
                }).catch(res => {
                    this.loading = false
                    this.$Message.error(res.msg)
                })
            },
            markHandled (row) {
                sensitiveHitHandleApi(row.id).then(res => {
                    this.$Message.success(res.msg)
                    this.getList()
                }).catch(res => this.$Message.error(res.msg))
            }
        }
    }
</script>

<style scoped>
    .sh-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
    }
    .sh-filters {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .sh-pending {
        color: #ff9900;
    }
    .sh-content {
        display: inline-block;
        max-width: 320px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        vertical-align: middle;
        color: #515a6e;
    }
    .sh-page {
        margin-top: 16px;
        text-align: right;
    }
    .sh-done {
        color: #c5c8ce;
    }
</style>
