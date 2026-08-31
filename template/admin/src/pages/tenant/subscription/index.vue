<template>
    <div>
        <div class="i-layout-page-header">
          <div class="i-layout-page-header">
            <span class="ivu-page-header-title">{{$route.meta.title}}</span>
          </div>
        </div>
        <Card :bordered="false" dis-hover class="ivu-mt">
            <Alert v-if="loadError" type="warning" show-icon>{{ loadError }}</Alert>
            <Tabs v-model="curTab" @on-click="onTabChange" v-else>
                <TabPane label="我的套餐" name="plan">
                    <div v-if="summary.tenant" class="plan-panel">
                        <div class="upgrade-bar">
                            <Button type="primary" icon="md-trending-up" @click="openPlans">套餐升级与续订</Button>
                        </div>
                        <Row :gutter="16">
                            <Col span="8">
                                <div class="info-cell">
                                    <div class="info-label">租户名称</div>
                                    <div class="info-value">{{ summary.tenant.name }}</div>
                                </div>
                            </Col>
                            <Col span="8">
                                <div class="info-cell">
                                    <div class="info-label">当前套餐</div>
                                    <div class="info-value">
                                        <Tag color="blue" v-if="summary.plan">{{ summary.plan.name }}（{{ summary.plan.price }}元/月）</Tag>
                                        <span v-else>未订购</span>
                                    </div>
                                </div>
                            </Col>
                            <Col span="8">
                                <div class="info-cell">
                                    <div class="info-label">到期时间</div>
                                    <div class="info-value" :class="{ 'expire-danger': summary.tenant.is_expired }">
                                        {{ summary.tenant._expire_time }}
                                        <Tag color="red" v-if="summary.tenant.is_expired">已到期</Tag>
                                    </div>
                                </div>
                            </Col>
                        </Row>
                        <Divider orientation="left" size="small">配额用量</Divider>
                        <Row :gutter="16" v-if="summary.plan">
                            <Col span="8">
                                <div class="info-cell">
                                    <div class="info-label">接入应用</div>
                                    <div class="info-value">{{ summary.usage.app_count }} / {{ limitText(summary.plan.app_limit) }}</div>
                                </div>
                            </Col>
                            <Col span="8">
                                <div class="info-cell">
                                    <div class="info-label">客服坐席</div>
                                    <div class="info-value">{{ summary.usage.seat_count }} / {{ limitText(summary.plan.seat_limit) }}</div>
                                </div>
                            </Col>
                            <Col span="8">
                                <div class="info-cell">
                                    <div class="info-label">日消息上限</div>
                                    <div class="info-value">{{ limitText(summary.plan.daily_msg_limit) }}</div>
                                </div>
                            </Col>
                            <Col span="8">
                                <div class="info-cell">
                                    <div class="info-label">存储空间</div>
                                    <div class="info-value">{{ limitText(summary.plan.storage_limit_mb, 'MB') }}</div>
                                </div>
                            </Col>
                            <Col span="8">
                                <div class="info-cell">
                                    <div class="info-label">聊天记录保留</div>
                                    <div class="info-value">{{ summary.plan.record_keep_days > 0 ? summary.plan.record_keep_days + '天' : '永久' }}</div>
                                </div>
                            </Col>
                        </Row>
                        <template v-if="summary.plan">
                            <Divider orientation="left" size="small">功能权益</Divider>
                            <Tag :color="summary.plan.auto_reply ? 'green' : 'default'">自动回复</Tag>
                            <Tag :color="summary.plan.brand_custom ? 'green' : 'default'">品牌定制</Tag>
                            <Tag :color="summary.plan.data_export ? 'green' : 'default'">数据导出</Tag>
                            <Tag :color="summary.plan.app_push ? 'green' : 'default'">应用推送</Tag>
                        </template>
                        <Alert v-if="summary.tenant.is_expired" type="error" show-icon class="ivu-mt">
                            套餐已到期，续费请联系平台客服。
                        </Alert>
                    </div>
                </TabPane>
                <TabPane label="订阅订单" name="orders">
                    <Table :columns="orderColumns" :data="orderList" :loading="orderLoading" highlight-row no-userFrom-text="暂无数据">
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
                        <Page :total="orderTotal" :current="orderWhere.page" show-total @on-change="orderPageChange" :page-size="orderWhere.limit"/>
                    </div>
                </TabPane>
                <TabPane label="我的发票" name="invoice">
                    <Button type="primary" icon="md-add" @click="openApply">申请开票</Button>
                    <Table :columns="invoiceColumns" :data="invoiceList" :loading="invoiceLoading" highlight-row class="mt25" no-userFrom-text="暂无数据">
                        <template slot-scope="{ row }" slot="type">
                            <Tag :color="row.type === 2 ? 'purple' : 'blue'">{{ row.type === 2 ? '专票' : '普票' }}</Tag>
                        </template>
                        <template slot-scope="{ row }" slot="amount">
                            <span>￥{{ row.amount }}</span>
                        </template>
                        <template slot-scope="{ row }" slot="status">
                            <Tag :color="invoiceStatusColor(row.status)">{{ invoiceStatusText(row.status) }}</Tag>
                        </template>
                    </Table>
                    <div class="acea-row row-right page">
                        <Page :total="invoiceTotal" :current="invoiceWhere.page" show-total @on-change="invoicePageChange" :page-size="invoiceWhere.limit"/>
                    </div>
                </TabPane>
            </Tabs>
        </Card>

        <!-- 套餐升级与续订 -->
        <Modal v-model="plansModal" title="套餐升级与续订" width="960" footer-hide>
            <Alert type="info" show-icon>
                在线自助开通暂未开放，如需升级或续费，请联系您的客户经理办理，我们将在确认后为您开通。
            </Alert>
            <Row :gutter="16" class="plans-row">
                <Col :xl="6" :lg="6" :md="12" :sm="24" v-for="item in plans" :key="item.id">
                    <div class="plan-card" :class="{ 'plan-card-current': isCurrentPlan(item) }">
                        <div class="plan-card-badge" v-if="isCurrentPlan(item)">当前套餐</div>
                        <div class="plan-card-name">{{ item.name }}</div>
                        <div class="plan-card-price">
                            <span class="plan-card-amount">￥{{ item.price }}</span>
                            <span class="plan-card-unit">/月</span>
                        </div>
                        <ul class="plan-card-quota">
                            <li v-for="q in quotaFields" :key="q.key">
                                {{ q.label }}：{{ quotaText(item, q) }}
                            </li>
                        </ul>
                        <ul class="plan-card-features">
                            <!-- 悬停给出能力说明，标签本身说不清"自定义广告位"到底能做什么 -->
                            <li v-for="field in featureFields" :key="field"
                                :class="{ 'feature-off': !item[field] }">
                                <Tooltip :content="featureText(field).desc" max-width="240" placement="right">
                                    <Icon :type="item[field] ? 'md-checkmark-circle' : 'md-close-circle'"/>
                                    <span>{{ featureText(field).name }}</span>
                                </Tooltip>
                            </li>
                        </ul>
                        <Button long :type="isCurrentPlan(item) ? 'default' : 'primary'" @click="contactUpgrade(item)">
                            {{ isCurrentPlan(item) ? '联系客户经理续费' : '联系客户经理升级' }}
                        </Button>
                    </div>
                </Col>
            </Row>
        </Modal>

        <!-- 申请开票 -->
        <Modal v-model="applyModal" title="申请开票" :closable="false">
            <Form ref="applyForm" :model="applyForm" :rules="applyRules" :label-width="90">
                <FormItem label="对账单：" prop="order_id">
                    <Select v-model="applyForm.order_id" placeholder="请选择需要开票的对账单">
                        <Option v-for="item in effectiveOrders" :value="item.id" :key="item.id">
                            {{ item.order_no }}（{{ item.plan_name }} ￥{{ item.amount }}）
                        </Option>
                    </Select>
                </FormItem>
                <FormItem label="发票抬头：" prop="title">
                    <Input v-model="applyForm.title" placeholder="公司名称或个人"/>
                </FormItem>
                <FormItem label="税号：">
                    <Input v-model="applyForm.tax_no" placeholder="专票必填"/>
                </FormItem>
                <FormItem label="发票类型：">
                    <RadioGroup v-model="applyForm.type">
                        <Radio :label="1">普票</Radio>
                        <Radio :label="2">专票</Radio>
                    </RadioGroup>
                </FormItem>
                <FormItem label="接收邮箱：" prop="email">
                    <Input v-model="applyForm.email" placeholder="电子发票接收邮箱"/>
                </FormItem>
            </Form>
            <div slot="footer">
                <Button @click="applyModal = false">取消</Button>
                <Button type="primary" :loading="submitting" @click="saveApply">提交申请</Button>
            </div>
        </Modal>
    </div>
</template>

<script>
    import { mySubscriptionApi, orderListApi, invoiceListApi, invoiceApplyApi, tenantPlansApi } from '@/api/tenant'
    import { PLAN_FEATURE_FIELDS, PLAN_QUOTA_FIELDS, getPlanFeatureText } from '@/config/planFeatures'

    const INVOICE_STATUS_TEXT = { 0: '待开具', 1: '已开具', 2: '已驳回' }
    const INVOICE_STATUS_COLOR = { 0: 'orange', 1: 'green', 2: 'red' }

    export default {
        name: 'tenant_subscription',
        data () {
            return {
                curTab: 'plan',
                loadError: '',
                submitting: false,
                summary: {},
                orderLoading: false,
                orderTotal: 0,
                orderWhere: { page: 1, limit: 10 },
                orderList: [],
                invoiceLoading: false,
                invoiceTotal: 0,
                invoiceWhere: { page: 1, limit: 10 },
                invoiceList: [],
                plansModal: false,
                plans: [],
                applyModal: false,
                applyForm: { order_id: '', title: '', tax_no: '', type: 1, email: '' },
                applyRules: {
                    order_id: [{ required: true, type: 'number', message: '请选择对账单', trigger: 'change' }],
                    title: [{ required: true, message: '请输入发票抬头', trigger: 'blur' }],
                    email: [{ required: true, message: '请输入接收邮箱', trigger: 'blur' }]
                },
                orderColumns: [
                    { title: '对账单号', key: 'order_no', minWidth: 180 },
                    { title: '套餐', key: 'plan_name', minWidth: 100 },
                    { title: '月数', key: 'months', width: 70 },
                    { title: '金额', slot: 'amount', minWidth: 100 },
                    { title: '支付方式', slot: 'pay_type', minWidth: 100 },
                    { title: '状态', slot: 'status', minWidth: 90 },
                    { title: '订购后到期', key: '_expire_after', minWidth: 110 },
                    { title: '订购时间', key: '_create_time', minWidth: 150 }
                ],
                invoiceColumns: [
                    { title: '对账单号', key: 'order_no', minWidth: 180 },
                    { title: '发票抬头', key: 'title', minWidth: 150 },
                    { title: '类型', slot: 'type', width: 80 },
                    { title: '金额', slot: 'amount', minWidth: 100 },
                    { title: '状态', slot: 'status', minWidth: 90 },
                    { title: '发票号码', key: 'invoice_no', minWidth: 130 },
                    { title: '备注/驳回原因', key: 'remark', minWidth: 140 },
                    { title: '申请时间', key: '_create_time', minWidth: 150 }
                ]
            }
        },
        computed: {
            featureFields () {
                return PLAN_FEATURE_FIELDS
            },
            quotaFields () {
                return PLAN_QUOTA_FIELDS
            },
            effectiveOrders () {
                return this.orderList.filter(item => item.status === 1)
            }
        },
        created () {
            this.getSummary()
            this.getOrders()
            this.getInvoices()
        },
        methods: {
            featureText (field) {
                return getPlanFeatureText(field)
            },
            //0 一律表示不限制，记录保留等字段另有说法
            quotaText (plan, quota) {
                const value = Number(plan[quota.key] || 0)
                if (value > 0) return value + quota.unit
                return quota.zeroText || '不限'
            },
            limitText (value, unit = '') {
                return value > 0 ? `${value}${unit}` : '不限'
            },
            invoiceStatusText (status) {
                return INVOICE_STATUS_TEXT[status] || '未知'
            },
            invoiceStatusColor (status) {
                return INVOICE_STATUS_COLOR[status] || 'default'
            },
            onTabChange () {},
            getSummary () {
                mySubscriptionApi().then(res => {
                    this.summary = res.data
                }).catch(res => {
                    this.loadError = res.msg
                })
            },
            getOrders () {
                this.orderLoading = true
                orderListApi(this.orderWhere).then(res => {
                    this.orderTotal = res.data.count
                    this.orderList = res.data.list
                    this.orderLoading = false
                }).catch(res => {
                    this.orderLoading = false
                    this.$Message.error(res.msg)
                })
            },
            orderPageChange (index) {
                this.orderWhere.page = index
                this.getOrders()
            },
            getInvoices () {
                this.invoiceLoading = true
                invoiceListApi(this.invoiceWhere).then(res => {
                    this.invoiceTotal = res.data.count
                    this.invoiceList = res.data.list
                    this.invoiceLoading = false
                }).catch(res => {
                    this.invoiceLoading = false
                    this.$Message.error(res.msg)
                })
            },
            invoicePageChange (index) {
                this.invoiceWhere.page = index
                this.getInvoices()
            },
            isCurrentPlan (item) {
                return this.summary.plan && this.summary.plan.id === item.id
            },
            openPlans () {
                this.plansModal = true
                if (this.plans.length) return
                tenantPlansApi().then(res => {
                    this.plans = res.data || []
                }).catch(res => {
                    this.$Message.error(res.msg)
                })
            },
            contactUpgrade (item) {
                const action = this.isCurrentPlan(item) ? '续费' : '升级'
                this.$Modal.info({
                    title: `${action}「${item.name}」`,
                    content: `如需${action}【${item.name}】（￥${item.price}/月），请联系您的客户经理或平台客服办理，我们将在收到款项后为您开通并生成对账单。`
                })
            },
            openApply () {
                if (!this.effectiveOrders.length) {
                    return this.$Message.warning('暂无可开票的对账单')
                }
                this.applyForm = { order_id: '', title: '', tax_no: '', type: 1, email: '' }
                this.applyModal = true
            },
            saveApply () {
                this.$refs.applyForm.validate(valid => {
                    if (!valid) return
                    this.submitting = true
                    invoiceApplyApi(this.applyForm).then(res => {
                        this.submitting = false
                        this.applyModal = false
                        this.$Message.success(res.msg)
                        this.getInvoices()
                    }).catch(res => {
                        this.submitting = false
                        this.$Message.error(res.msg)
                    })
                })
            }
        }
    }
</script>

<style scoped>
    .plan-panel {
        padding: 8px 0;
    }
    .info-cell {
        margin-bottom: 16px;
    }
    .info-label {
        color: #808695;
        font-size: 12.5px;
        margin-bottom: 4px;
    }
    .info-value {
        font-size: 15px;
    }
    .expire-danger {
        color: #ed4014;
    }
    .upgrade-bar {
        margin-bottom: 16px;
    }
    .plans-row {
        margin-top: 16px;
    }
    .plan-card {
        position: relative;
        border: 1px solid #dcdee2;
        border-radius: 6px;
        padding: 20px 16px;
        margin-bottom: 16px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .plan-card-current {
        border-color: #2d8cf0;
        box-shadow: 0 0 0 1px #2d8cf0;
    }
    .plan-card-badge {
        position: absolute;
        top: 0;
        right: 0;
        background: #2d8cf0;
        color: #fff;
        font-size: 12px;
        padding: 2px 10px;
        border-radius: 0 5px 0 6px;
    }
    .plan-card-name {
        font-size: 16px;
        font-weight: 600;
    }
    .plan-card-price {
        color: #2d8cf0;
    }
    .plan-card-amount {
        font-size: 26px;
        font-weight: 700;
    }
    .plan-card-unit {
        color: #808695;
        font-size: 13px;
    }
    .plan-card-quota {
        list-style: none;
        margin: 0;
        padding: 0;
        color: #515a6e;
        font-size: 13px;
        line-height: 2;
        border-top: 1px dashed #e8eaec;
        padding-top: 8px;
    }
    .plan-card-features {
        min-height: 50px;
        margin-bottom: 14px;
        text-align: left;
    }

    .plan-card-features li {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 3px 0;
        font-size: 13px;
        color: #17233d;
    }

    .plan-card-features li i {
        color: #19be6b;
    }

    /* 未包含的能力保留但置灰，让租户看清升级能多拿什么 */
    .plan-card-features li.feature-off {
        color: #c5c8ce;
    }

    .plan-card-features li.feature-off i {
        color: #dcdee2;
    }
</style>
