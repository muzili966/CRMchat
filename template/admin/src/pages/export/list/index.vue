<template>
    <div>
        <div class="i-layout-page-header">
            <div class="i-layout-page-header">
                <span class="ivu-page-header-title">{{ $route.meta.title }}</span>
            </div>
        </div>

        <Card :bordered="false" dis-hover class="ivu-mt">
            <div class="exp-bar">
                <Select v-model="where.status" clearable placeholder="全部状态" style="width: 140px"
                        @on-change="reload">
                    <Option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</Option>
                </Select>
                <div class="exp-tip">导出文件保留 {{ keepDays }} 天，到期自动清理</div>
            </div>

            <Table :columns="columns" :data="list" :loading="loading" no-data-text="暂无导出任务">
                <template slot-scope="{ row }" slot="status">
                    <Tag :color="statusColor(row.status)">{{ row.status_text }}</Tag>
                    <!-- 失败原因必须让用户看见，否则只能干等 -->
                    <Tooltip v-if="row.message" :content="row.message" placement="top" max-width="320">
                        <Icon type="ios-information-circle-outline" class="exp-msg"/>
                    </Tooltip>
                </template>
                <template slot-scope="{ row }" slot="size">
                    <span>{{ row.row_count ? row.row_count + ' 行' : '—' }}</span>
                    <span v-if="row.file_size" class="exp-sub">{{ humanSize(row.file_size) }}</span>
                </template>
                <template slot-scope="{ row }" slot="action">
                    <a v-if="row.can_download" @click="download(row)">下载</a>
                    <span v-else class="exp-disabled">下载</span>
                    <Divider type="vertical"/>
                    <a class="exp-del" @click="remove(row)">删除</a>
                </template>
            </Table>

            <div class="exp-page">
                <Page :total="total" :current="where.page" :page-size="where.limit" show-total
                      @on-change="onPage"/>
            </div>
        </Card>
    </div>
</template>

<script>
    import { exportTaskListApi, exportTaskDeleteApi } from '@/api/exportTask'

    //与后端 ExportTask::STATUS_* 对应
    const STATUS_PENDING = 0
    const STATUS_RUNNING = 1
    const STATUS_SUCCESS = 2
    const STATUS_FAILED = 3

    const STATUS_COLOR = {
        [STATUS_PENDING]: 'default',
        [STATUS_RUNNING]: 'blue',
        [STATUS_SUCCESS]: 'green',
        [STATUS_FAILED]: 'red'
    }

    //有任务未结束时自动刷新，让用户不必手动点
    const POLL_INTERVAL = 5000

    export default {
        name: 'exportList',
        data () {
            return {
                loading: false,
                list: [],
                total: 0,
                keepDays: 7,
                timer: null,
                where: { status: '', page: 1, limit: 20 },
                statusOptions: [
                    { value: STATUS_PENDING, label: '排队中' },
                    { value: STATUS_RUNNING, label: '生成中' },
                    { value: STATUS_SUCCESS, label: '已完成' },
                    { value: STATUS_FAILED, label: '失败' }
                ],
                columns: [
                    { title: '导出内容', key: 'type_name', minWidth: 120 },
                    { title: '格式', key: 'format', width: 80 },
                    { title: '状态', slot: 'status', width: 120 },
                    { title: '数据量', slot: 'size', width: 140 },
                    { title: '发起人', key: 'admin_name', width: 130 },
                    { title: '发起时间', key: '_create_time', width: 170 },
                    { title: '有效期至', key: '_expire_time', width: 140 },
                    { title: '操作', slot: 'action', width: 110 }
                ]
            }
        },
        created () {
            this.getList()
        },
        beforeDestroy () {
            this.stopPoll()
        },
        methods: {
            statusColor (status) {
                return STATUS_COLOR[status] || 'default'
            },
            humanSize (bytes) {
                if (bytes < 1024) return bytes + ' B'
                if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
                return (bytes / 1048576).toFixed(1) + ' MB'
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
                exportTaskListApi(this.where).then(res => {
                    this.list = (res.data && res.data.list) || []
                    this.total = (res.data && res.data.count) || 0
                    this.loading = false
                    this.syncPoll()
                }).catch(res => {
                    this.loading = false
                    this.stopPoll()
                    this.$Message.error(res.msg)
                })
            },
            //只在有任务未结束时轮询，全部结束就停，避免页面长期空转
            syncPoll () {
                const pending = this.list.some(item =>
                    item.status === STATUS_PENDING || item.status === STATUS_RUNNING)
                pending ? this.startPoll() : this.stopPoll()
            },
            startPoll () {
                if (this.timer) return
                this.timer = setInterval(() => this.getList(), POLL_INTERVAL)
            },
            stopPoll () {
                if (!this.timer) return
                clearInterval(this.timer)
                this.timer = null
            },
            download (row) {
                window.open(location.origin + row.file_url)
            },
            remove (row) {
                this.$Modal.confirm({
                    title: '删除导出任务',
                    content: '删除后文件将无法下载，确定删除？',
                    onOk: () => {
                        exportTaskDeleteApi(row.id).then(res => {
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
    .exp-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
    }
    .exp-tip {
        color: #a3aab8;
        font-size: 13px;
    }
    .exp-page {
        margin-top: 16px;
        text-align: right;
    }
    .exp-msg {
        color: #ed4014;
        cursor: pointer;
        vertical-align: middle;
    }
    .exp-sub {
        color: #a3aab8;
        margin-left: 6px;
    }
    .exp-disabled {
        color: #c5c8ce;
        cursor: not-allowed;
    }
    .exp-del {
        color: #ed4014;
    }
</style>
