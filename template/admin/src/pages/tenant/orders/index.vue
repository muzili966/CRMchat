<template>
    <div>
        <div class="i-layout-page-header">
          <div class="i-layout-page-header">
            <span class="ivu-page-header-title">{{$route.meta.title}}</span>
          </div>
        </div>
        <Card :bordered="false" dis-hover class="ivu-mt">
            <Form ref="searchForm" :model="searchWhere" :label-width="labelWidth" :label-position="labelPosition" @submit.native.prevent>
                <Row type="flex" :gutter="24">
                    <Col v-bind="grid">
                        <FormItem label="套餐：" label-for="plan_id">
                            <Select v-model="planId" placeholder="请选择套餐" @on-change="userSearchs" clearable>
                                <Option v-for="item in planOptions" :value="String(item.id)" :key="item.id">{{ item.name }}</Option>
                            </Select>
                        </FormItem>
                    </Col>
                    <Col v-bind="grid">
                        <FormItem label="状态：" label-for="status">
                            <Select v-model="status" placeholder="请选择" @on-change="userSearchs" clearable>
                                <Option value="all">全部</Option>
                                <Option value="1">已生效</Option>
                                <Option value="2">已作废</Option>
                            </Select>
                        </FormItem>
                    </Col>
                    <Col v-bind="grid">
                        <FormItem label="时间：" label-for="date">
                            <DatePicker type="daterange" v-model="dateRange" placeholder="订购时间范围" style="width: 100%" @on-change="userSearchs"/>
                        </FormItem>
                    </Col>
                    <Col v-bind="grid">
                        <FormItem label="搜索：" label-for="order_no">
                            <Input search enter-button placeholder="请输入对账单号" v-model="searchWhere.order_no" @on-search="userSearchs"/>
                        </FormItem>
                    </Col>
                </Row>
                <Row type="flex">
                    <Col v-bind="grid">
                        <Button type="primary" icon="ios-download-outline" :loading="exporting === 'xlsx'" @click="exportOrders('xlsx')">导出Excel</Button>
                        <Button class="ml10" icon="ios-download-outline" :loading="exporting === 'csv'" @click="exportOrders('csv')">导出对账CSV</Button>
                    </Col>
                </Row>
            </Form>
            <Table :columns="columns" :data="list" class="mt25" :loading="loading" highlight-row
                   no-userFrom-text="暂无数据" no-filtered-userFrom-text="暂无筛选结果">
                <template slot-scope="{ row }" slot="amount">
                    <span>￥{{ row.amount }}</span>
                </template>
                <template slot-scope="{ row }" slot="pay_type">
                    <Tag :color="row.pay_type === 1 ? 'blue' : 'orange'">{{ row.pay_type === 1 ? '后台开通' : '线下转账' }}</Tag>
                </template>
                <template slot-scope="{ row }" slot="status">
                    <Tag :color="row.status === 1 ? 'green' : 'default'">{{ row.status === 1 ? '已生效' : '已作废' }}</Tag>
                </template>
            </Table>
            <div class="acea-row row-right page">
                <Page :total="total" :current="searchWhere.page" show-elevator show-total @on-change="pageChange" :page-size="searchWhere.limit"/>
            </div>
        </Card>
    </div>
</template>

<script>
    import { mapState } from 'vuex'
    import { orderListApi, orderExportApi, planAllApi } from '@/api/tenant'

    export default {
        name: 'tenant_orders',
        data () {
            return {
                grid: { xl: 7, lg: 7, md: 12, sm: 24, xs: 24 },
                loading: false,
                exporting: '',
                total: 0,
                status: '',
                planId: '',
                dateRange: [],
                searchWhere: { plan_id: 0, order_no: '', status: '', start: 0, end: 0, page: 1, limit: 20 },
                planOptions: [],
                list: [],
                columns: [
                    { title: '对账单号', key: 'order_no', minWidth: 180 },
                    { title: '租户', key: 'tenant_name', minWidth: 120 },
                    { title: '套餐', key: 'plan_name', minWidth: 100 },
                    { title: '月数', key: 'months', width: 70 },
                    { title: '金额', slot: 'amount', minWidth: 100 },
                    { title: '支付方式', slot: 'pay_type', minWidth: 100 },
                    { title: '状态', slot: 'status', minWidth: 90 },
                    { title: '订购后到期', key: '_expire_after', minWidth: 110 },
                    { title: '订购时间', key: '_create_time', minWidth: 150 },
                    { title: '备注', key: 'remark', minWidth: 120 }
                ]
            }
        },
        computed: {
            ...mapState('media', ['isMobile']),
            labelWidth () {
                return this.isMobile ? undefined : 50
            },
            labelPosition () {
                return this.isMobile ? 'top' : 'left'
            }
        },
        created () {
            this.getPlanOptions()
            this.getList()
        },
        methods: {
            getPlanOptions () {
                planAllApi().then(res => {
                    this.planOptions = res.data || []
                }).catch(res => {
                    this.$Message.error(res.msg)
                })
            },
            buildWhere () {
                const [start, end] = this.dateRange
                this.searchWhere.plan_id = this.planId ? Number(this.planId) : 0
                this.searchWhere.status = this.status === 'all' ? '' : this.status
                this.searchWhere.start = start ? Math.floor(new Date(start).getTime() / 1000) : 0
                this.searchWhere.end = end ? Math.floor(new Date(end).getTime() / 1000) + 86399 : 0
            },
            getList () {
                this.loading = true
                this.buildWhere()
                orderListApi(this.searchWhere).then(res => {
                    this.total = res.data.count
                    this.list = res.data.list
                    this.loading = false
                }).catch(res => {
                    this.loading = false
                    this.$Message.error(res.msg)
                })
            },
            pageChange (index) {
                this.searchWhere.page = index
                this.getList()
            },
            userSearchs () {
                this.searchWhere.page = 1
                this.getList()
            },
            //两个按钮共用一个loading标识，避免导出其中一种时另一个也转圈
            exportOrders (format) {
                this.exporting = format
                this.buildWhere()
                orderExportApi({ ...this.searchWhere, format }).then(res => {
                    this.exporting = ''
                    this.$Message.success(res.msg)
                    if (res.data && res.data.url) {
                        window.open(location.origin + res.data.url)
                    }
                }).catch(res => {
                    this.exporting = ''
                    this.$Message.error(res.msg)
                })
            }
        }
    }
</script>

<style scoped>

</style>
