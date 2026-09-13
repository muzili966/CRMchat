<template>
    <div>
        <div class="i-layout-page-header">
            <div class="i-layout-page-header">
                <span class="ivu-page-header-title">{{ $route.meta.title }}</span>
            </div>
        </div>
        <Card :bordered="false" dis-hover class="ivu-mt">
            <Form :model="where" :label-width="labelWidth" :label-position="labelPosition" @submit.native.prevent>
                <Row type="flex" :gutter="24">
                    <Col v-bind="grid">
                        <FormItem label="状态：">
                            <Select v-model="where.status" placeholder="全部" clearable @on-change="search">
                                <Option v-for="item in options.statuses" :value="String(item.value)" :key="item.value">{{ item.label }}</Option>
                            </Select>
                        </FormItem>
                    </Col>
                    <Col v-bind="grid">
                        <FormItem label="渠道：">
                            <Select v-model="where.channel" placeholder="全部" clearable @on-change="search">
                                <Option v-for="item in options.channels" :value="item.code" :key="item.code">{{ item.name }}</Option>
                            </Select>
                        </FormItem>
                    </Col>
                    <Col v-bind="grid">
                        <FormItem label="异常：">
                            <Select v-model="where.abnormal" placeholder="全部" clearable @on-change="search">
                                <Option value="1">仅看异常</Option>
                            </Select>
                        </FormItem>
                    </Col>
                    <Col v-bind="grid">
                        <FormItem label="搜索：">
                            <Input search enter-button placeholder="请输入支付单号" v-model="where.pay_no" @on-search="search"/>
                        </FormItem>
                    </Col>
                </Row>
                <Row type="flex">
                    <Col v-bind="grid">
                        <Button type="primary" icon="md-add" @click="openCreate">新建支付单</Button>
                    </Col>
                </Row>
            </Form>
            <Table :columns="columns" :data="list" class="mt25" :loading="loading" no-data-text="暂无数据">
                <template slot-scope="{ row }" slot="subject">
                    <div>{{ row.tenant_name }}</div>
                    <div class="sub-text">{{ row.subject }}</div>
                </template>
                <template slot-scope="{ row }" slot="amount">
                    <div>￥{{ row.amount }}</div>
                    <div v-if="row.status === STATUS.PAID" class="sub-text">实收 ￥{{ row.paid_amount }}</div>
                </template>
                <template slot-scope="{ row }" slot="status">
                    <Tag :color="STATUS_COLORS[row.status] || 'default'">{{ row._status }}</Tag>
                    <Tooltip v-if="row.abnormal" :content="row.abnormal_reason" max-width="280" transfer>
                        <Tag color="red">异常</Tag>
                    </Tooltip>
                    <Tag v-else-if="row.fulfilled_time" color="cyan">已开通</Tag>
                </template>
                <template slot-scope="{ row }" slot="channel">
                    <div>{{ channelNames[row.channel] || '-' }}</div>
                    <div v-if="row.trade_no" class="sub-text">{{ row.trade_no }}</div>
                </template>
                <template slot-scope="{ row }" slot="creator">
                    <span>{{ SOURCE_TEXT[row.source] || '' }} {{ row.creator_name }}</span>
                </template>
                <template slot-scope="{ row }" slot="time">
                    <div>创建 {{ row._create_time }}</div>
                    <div v-if="row._paid_time" class="sub-text">支付 {{ row._paid_time }}</div>
                    <div v-else-if="row.status === STATUS.PENDING" class="sub-text">过期 {{ row._expire_time }}</div>
                </template>
                <template slot-scope="{ row }" slot="action">
                    <div class="actions">
                        <a v-if="row.payable" @click="showLinks(row.id)">链接</a>
                        <a v-if="row.status === STATUS.PENDING" @click="openConfirm(row)">确认到账</a>
                        <a v-if="canSync(row)" @click="runAction(row, 'sync')">同步</a>
                        <a v-if="row.status === STATUS.PENDING" class="danger" @click="runAction(row, 'close')">关闭</a>
                        <a v-if="row.status === STATUS.PAID && !row.fulfilled_time" @click="runAction(row, 'fulfill')">补开通</a>
                    </div>
                </template>
            </Table>
            <div class="acea-row row-right page">
                <Page :total="total" :current="where.page" show-elevator show-total @on-change="pageChange" :page-size="where.limit"/>
            </div>
        </Card>

        <Modal v-model="createShow" title="新建支付单" width="480" :mask-closable="false">
            <Form :label-width="80" @submit.native.prevent>
                <FormItem label="租户" required>
                    <Select v-model="form.tenant_id" filterable remote :remote-method="searchTenants" :loading="tenantLoading" placeholder="输入租户名称搜索">
                        <Option v-for="item in tenants" :value="item.id" :key="item.id">{{ item.name }}</Option>
                    </Select>
                </FormItem>
                <FormItem label="套餐" required>
                    <Select v-model="form.plan_id" placeholder="请选择套餐">
                        <Option v-for="item in plans" :value="item.id" :key="item.id">{{ item.name }}（￥{{ item.price }}/月）</Option>
                    </Select>
                </FormItem>
                <FormItem label="月数" required>
                    <InputNumber v-model="form.months" :min="MONTHS_MIN" :max="MONTHS_MAX" :precision="0"/>
                </FormItem>
                <FormItem label="应付金额">
                    <span class="amount-preview">￥{{ amountPreview }}</span>
                </FormItem>
                <FormItem label="备注">
                    <Input v-model="form.remark" :maxlength="REMARK_MAX" placeholder="选填，仅平台可见"/>
                </FormItem>
            </Form>
            <div slot="footer">
                <Button @click="createShow = false">取消</Button>
                <Button type="primary" :loading="saving" @click="submitCreate">创建并获取链接</Button>
            </div>
        </Modal>

        <Modal v-model="linksShow" title="支付链接" width="460" footer-hide>
            <div v-if="links" class="links">
                <p>{{ links.subject }}</p>
                <p class="links-amount">￥{{ links.amount }}</p>
                <img :src="links.qrcode" class="links-qr" alt="支付二维码">
                <p class="sub-text">{{ links._expire_time }} 前有效；扫码或打开链接后由付款人自选支付方式，到账自动开通</p>
                <div class="links-url">
                    <linkWithQr :link="links.url"/>
                </div>
                <a :href="links.qrcode" target="_blank" rel="noopener">打开二维码图片</a>
            </div>
        </Modal>

        <Modal v-model="confirmShow" title="确认到账" width="460" :mask-closable="false">
            <Alert type="warning" show-icon>请先核对银行流水或收款记录；确认后按下单时的套餐与月数自动开通</Alert>
            <Form :label-width="80" @submit.native.prevent>
                <FormItem label="应付金额">
                    <span>￥{{ confirmRow.amount }}</span>
                </FormItem>
                <FormItem label="实收金额" required>
                    <Input v-model="confirmForm.paid_amount" placeholder="与应付不一致时会记为异常，不自动开通"/>
                </FormItem>
                <FormItem label="流水号">
                    <Input v-model="confirmForm.trade_no" placeholder="选填，银行或收款记录的流水号"/>
                </FormItem>
                <FormItem label="备注">
                    <Input v-model="confirmForm.remark" placeholder="选填"/>
                </FormItem>
            </Form>
            <div slot="footer">
                <Button @click="confirmShow = false">取消</Button>
                <Button type="primary" :loading="confirming" @click="submitConfirm">确认到账</Button>
            </div>
        </Modal>
    </div>
</template>

<script>
    import { mapState } from 'vuex'
    import linkWithQr from '@/components/linkWithQr'
    import { tenantListApi, planAllApi } from '@/api/tenant'
    import {
        paymentListApi, paymentOptionsApi, paymentCreateApi, paymentLinksApi,
        paymentConfirmApi, paymentCloseApi, paymentSyncApi, paymentFulfillApi
    } from '@/api/payment'

    // 与后端 PaymentStatus 一致
    const STATUS = { PENDING: 0, PAID: 1, CLOSED: 2, REFUNDED: 3 }
    const STATUS_COLORS = { 0: 'orange', 1: 'green', 2: 'default', 3: 'purple' }
    const SOURCE_TEXT = { kefu: '客服', admin: '后台' }
    // 人工收款在渠道侧没有订单，无从查询
    const MANUAL_CHANNEL = 'manual'
    // 平台自营租户不是付款方
    const PLATFORM_TENANT_ID = 1
    // 与后端 TenantPlanPayable 的月数范围一致
    const MONTHS_MIN = 1
    const MONTHS_MAX = 36
    const REMARK_MAX = 255
    const TENANT_SEARCH_LIMIT = 20

    const ACTIONS = {
        sync: { api: paymentSyncApi, confirm: '' },
        close: { api: paymentCloseApi, confirm: '关闭前会先向渠道核对是否已支付；关闭后链接与二维码立即失效，确定关闭？' },
        fulfill: { api: paymentFulfillApi, confirm: '确认已处理好异常原因，按下单时的套餐与月数开通？' }
    }

    const emptyForm = () => ({ tenant_id: '', plan_id: '', months: MONTHS_MIN, remark: '' })
    const emptyConfirm = () => ({ paid_amount: '', trade_no: '', remark: '' })
    const blankToEmpty = value => (value === undefined || value === null ? '' : value)

    export default {
        name: 'tenant_payment',
        components: { linkWithQr },
        data () {
            return {
                STATUS,
                STATUS_COLORS,
                SOURCE_TEXT,
                MONTHS_MIN,
                MONTHS_MAX,
                REMARK_MAX,
                grid: { xl: 6, lg: 6, md: 12, sm: 24, xs: 24 },
                loading: false,
                total: 0,
                list: [],
                where: { status: '', channel: '', abnormal: '', pay_no: '', page: 1, limit: 20 },
                options: { channels: [], statuses: [] },
                createShow: false,
                saving: false,
                form: emptyForm(),
                tenants: [],
                tenantLoading: false,
                plans: [],
                linksShow: false,
                links: null,
                confirmShow: false,
                confirming: false,
                confirmRow: {},
                confirmForm: emptyConfirm(),
                columns: [
                    { title: '支付单号', key: 'pay_no', minWidth: 200 },
                    { title: '租户 / 内容', slot: 'subject', minWidth: 200 },
                    { title: '金额', slot: 'amount', minWidth: 110 },
                    { title: '状态', slot: 'status', minWidth: 150 },
                    { title: '渠道', slot: 'channel', minWidth: 150 },
                    { title: '发起', slot: 'creator', minWidth: 120 },
                    { title: '时间', slot: 'time', minWidth: 190 },
                    { title: '操作', slot: 'action', minWidth: 170 }
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
            },
            channelNames () {
                return this.options.channels.reduce((map, item) => ({ ...map, [item.code]: item.name }), {})
            },
            amountPreview () {
                const plan = this.plans.find(item => item.id === this.form.plan_id)
                return plan ? (Number(plan.price) * (this.form.months || 0)).toFixed(2) : '0.00'
            }
        },
        created () {
            this.getOptions()
            this.getList()
        },
        methods: {
            getOptions () {
                paymentOptionsApi().then(res => {
                    this.options = res.data
                }).catch(res => this.$Message.error(res.msg))
            },
            // 下拉清空后值可能是 undefined，传给后端要归一成空串
            buildParams () {
                return {
                    ...this.where,
                    status: blankToEmpty(this.where.status),
                    channel: blankToEmpty(this.where.channel),
                    abnormal: blankToEmpty(this.where.abnormal)
                }
            },
            getList () {
                this.loading = true
                paymentListApi(this.buildParams()).then(res => {
                    this.list = res.data.list
                    this.total = res.data.count
                    this.loading = false
                }).catch(res => {
                    this.loading = false
                    this.$Message.error(res.msg)
                })
            },
            search () {
                this.where.page = 1
                this.getList()
            },
            pageChange (page) {
                this.where.page = page
                this.getList()
            },
            canSync (row) {
                return row.status === STATUS.PENDING && !!row.channel && row.channel !== MANUAL_CHANNEL
            },
            openCreate () {
                this.form = emptyForm()
                this.createShow = true
                this.searchTenants('')
                if (!this.plans.length) {
                    planAllApi().then(res => {
                        this.plans = (res.data || []).filter(item => Number(item.price) > 0)
                    }).catch(res => this.$Message.error(res.msg))
                }
            },
            searchTenants (name) {
                this.tenantLoading = true
                tenantListApi({ name, page: 1, limit: TENANT_SEARCH_LIMIT }).then(res => {
                    this.tenants = (res.data.list || []).filter(item => item.id !== PLATFORM_TENANT_ID)
                    this.tenantLoading = false
                }).catch(res => {
                    this.tenantLoading = false
                    this.$Message.error(res.msg)
                })
            },
            submitCreate () {
                if (!this.form.tenant_id || !this.form.plan_id) {
                    this.$Message.warning('请选择租户和套餐')
                    return
                }
                this.saving = true
                paymentCreateApi(this.form).then(res => {
                    this.saving = false
                    this.createShow = false
                    this.links = res.data
                    this.linksShow = true
                    this.getList()
                }).catch(res => {
                    this.saving = false
                    this.$Message.error(res.msg)
                })
            },
            showLinks (id) {
                paymentLinksApi(id).then(res => {
                    this.links = res.data
                    this.linksShow = true
                }).catch(res => this.$Message.error(res.msg))
            },
            openConfirm (row) {
                this.confirmRow = row
                this.confirmForm = { ...emptyConfirm(), paid_amount: row.amount }
                this.confirmShow = true
            },
            submitConfirm () {
                this.confirming = true
                paymentConfirmApi(this.confirmRow.id, this.confirmForm).then(res => {
                    this.confirming = false
                    this.confirmShow = false
                    this.$Message.success(res.msg)
                    this.getList()
                }).catch(res => {
                    this.confirming = false
                    this.$Message.error(res.msg)
                })
            },
            runAction (row, kind) {
                const action = ACTIONS[kind]
                if (!action.confirm) {
                    this.callAction(action.api, row)
                    return
                }
                this.$Modal.confirm({ title: '提示', content: action.confirm, onOk: () => this.callAction(action.api, row) })
            },
            callAction (api, row) {
                api(row.id).then(res => {
                    const unpaid = res.data && res.data.paid === false
                    this.$Message.success(unpaid ? '渠道侧尚未支付' : res.msg)
                    this.getList()
                }).catch(res => this.$Message.error(res.msg))
            }
        }
    }
</script>

<style scoped>
    .sub-text {
        color: #808695;
        font-size: 12px;
    }

    .actions {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 12px;
    }

    .actions .danger {
        color: #ed4014;
    }

    .amount-preview {
        font-size: 18px;
        font-weight: 600;
    }

    .links {
        text-align: center;
    }

    .links-amount {
        margin-top: 4px;
        font-size: 22px;
        font-weight: 600;
    }

    .links-qr {
        display: block;
        width: 180px;
        height: 180px;
        margin: 12px auto;
    }

    .links-url {
        margin: 12px 0 8px;
        text-align: left;
    }
</style>
