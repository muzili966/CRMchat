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
                        <FormItem label="状态：" label-for="status">
                            <Select v-model="status" placeholder="请选择" @on-change="userSearchs" clearable>
                                <Option value="all">全部</Option>
                                <Option value="0">待开具</Option>
                                <Option value="1">已开具</Option>
                                <Option value="2">已驳回</Option>
                            </Select>
                        </FormItem>
                    </Col>
                </Row>
            </Form>
            <Table :columns="columns" :data="list" class="mt25" :loading="loading" highlight-row
                   no-userFrom-text="暂无数据" no-filtered-userFrom-text="暂无筛选结果">
                <template slot-scope="{ row }" slot="tenant">
                    <span>{{ tenantMap[row.tenant_id] || `租户#${row.tenant_id}` }}</span>
                </template>
                <template slot-scope="{ row }" slot="type">
                    <Tag :color="row.type === 2 ? 'purple' : 'blue'">{{ row.type === 2 ? '专票' : '普票' }}</Tag>
                </template>
                <template slot-scope="{ row }" slot="amount">
                    <span>￥{{ row.amount }}</span>
                </template>
                <template slot-scope="{ row }" slot="status">
                    <Tag :color="statusColor(row.status)">{{ statusText(row.status) }}</Tag>
                </template>
                <template slot-scope="{ row }" slot="action">
                    <template v-if="row.status === 0">
                        <a @click="issue(row)">开具</a>
                        <Divider type="vertical"/>
                        <a class="reject-link" @click="reject(row)">驳回</a>
                    </template>
                    <span v-else>-</span>
                </template>
            </Table>
            <div class="acea-row row-right page">
                <Page :total="total" :current="searchWhere.page" show-elevator show-total @on-change="pageChange" :page-size="searchWhere.limit"/>
            </div>
        </Card>

        <!-- 开具发票 -->
        <Modal v-model="issueModal" :title="`开具发票 - ${currentRow.title || ''}`" :closable="false">
            <Form ref="issueForm" :model="issueForm" :rules="issueRules" :label-width="90">
                <FormItem label="发票号码：" prop="invoice_no">
                    <Input v-model="issueForm.invoice_no" placeholder="请输入发票号码"/>
                </FormItem>
                <FormItem label="备注：">
                    <Input v-model="issueForm.remark" type="textarea" :rows="2" placeholder="选填"/>
                </FormItem>
            </Form>
            <div slot="footer">
                <Button @click="issueModal = false">取消</Button>
                <Button type="primary" :loading="submitting" @click="saveIssue">确定开具</Button>
            </div>
        </Modal>

        <!-- 驳回发票 -->
        <Modal v-model="rejectModal" :title="`驳回发票 - ${currentRow.title || ''}`" :closable="false">
            <Form ref="rejectForm" :model="rejectForm" :rules="rejectRules" :label-width="90">
                <FormItem label="驳回原因：" prop="remark">
                    <Input v-model="rejectForm.remark" type="textarea" :rows="3" placeholder="请输入驳回原因"/>
                </FormItem>
            </Form>
            <div slot="footer">
                <Button @click="rejectModal = false">取消</Button>
                <Button type="error" :loading="submitting" @click="saveReject">确定驳回</Button>
            </div>
        </Modal>
    </div>
</template>

<script>
    import { mapState } from 'vuex'
    import { invoiceListApi, invoiceAuditApi, tenantListApi } from '@/api/tenant'

    const STATUS_PENDING = 0
    const STATUS_ISSUED = 1
    const STATUS_REJECTED = 2

    export default {
        name: 'tenant_invoice',
        data () {
            return {
                grid: { xl: 7, lg: 7, md: 12, sm: 24, xs: 24 },
                loading: false,
                submitting: false,
                total: 0,
                status: '',
                searchWhere: { status: '', page: 1, limit: 20 },
                list: [],
                tenantMap: {},
                currentRow: {},
                issueModal: false,
                rejectModal: false,
                issueForm: { invoice_no: '', remark: '' },
                rejectForm: { remark: '' },
                issueRules: {
                    invoice_no: [{ required: true, message: '请输入发票号码', trigger: 'blur' }]
                },
                rejectRules: {
                    remark: [{ required: true, message: '请输入驳回原因', trigger: 'blur' }]
                },
                columns: [
                    { title: 'ID', key: 'id', width: 70 },
                    { title: '租户', slot: 'tenant', minWidth: 120 },
                    { title: '对账单号', key: 'order_no', minWidth: 180 },
                    { title: '发票抬头', key: 'title', minWidth: 150 },
                    { title: '税号', key: 'tax_no', minWidth: 150 },
                    { title: '类型', slot: 'type', width: 80 },
                    { title: '金额', slot: 'amount', minWidth: 100 },
                    { title: '状态', slot: 'status', minWidth: 90 },
                    { title: '发票号码', key: 'invoice_no', minWidth: 130 },
                    { title: '备注/驳回原因', key: 'remark', minWidth: 140 },
                    { title: '申请时间', key: '_create_time', minWidth: 150 },
                    { title: '操作', slot: 'action', fixed: 'right', minWidth: 110 }
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
            this.getTenantMap()
            this.getList()
        },
        methods: {
            statusText (status) {
                return { [STATUS_PENDING]: '待开具', [STATUS_ISSUED]: '已开具', [STATUS_REJECTED]: '已驳回' }[status] || '未知'
            },
            statusColor (status) {
                return { [STATUS_PENDING]: 'orange', [STATUS_ISSUED]: 'green', [STATUS_REJECTED]: 'red' }[status] || 'default'
            },
            getTenantMap () {
                tenantListApi({ page: 1, limit: 500 }).then(res => {
                    this.tenantMap = (res.data.list || []).reduce((map, item) => ({ ...map, [item.id]: item.name }), {})
                }).catch(() => {
                    this.tenantMap = {}
                })
            },
            getList () {
                this.loading = true
                invoiceListApi(this.searchWhere).then(res => {
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
                this.searchWhere.status = this.status === 'all' ? '' : this.status
                this.searchWhere.page = 1
                this.getList()
            },
            issue (row) {
                this.currentRow = row
                this.issueForm = { invoice_no: '', remark: '' }
                this.issueModal = true
            },
            saveIssue () {
                this.$refs.issueForm.validate(valid => {
                    if (!valid) return
                    this.audit(this.currentRow.id, { status: STATUS_ISSUED, ...this.issueForm }, () => {
                        this.issueModal = false
                    })
                })
            },
            reject (row) {
                this.currentRow = row
                this.rejectForm = { remark: '' }
                this.rejectModal = true
            },
            saveReject () {
                this.$refs.rejectForm.validate(valid => {
                    if (!valid) return
                    this.audit(this.currentRow.id, { status: STATUS_REJECTED, invoice_no: '', ...this.rejectForm }, () => {
                        this.rejectModal = false
                    })
                })
            },
            audit (id, data, onDone) {
                this.submitting = true
                invoiceAuditApi(id, data).then(res => {
                    this.submitting = false
                    onDone()
                    this.$Message.success(res.msg)
                    this.getList()
                }).catch(res => {
                    this.submitting = false
                    this.$Message.error(res.msg)
                })
            }
        }
    }
</script>

<style scoped>
    .reject-link {
        color: #ed4014;
    }
</style>
