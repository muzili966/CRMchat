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
                                <Option value="1">在售</Option>
                                <Option value="0">停售</Option>
                            </Select>
                        </FormItem>
                    </Col>
                    <Col v-bind="grid">
                        <FormItem label="搜索：" label-for="name">
                            <Input search enter-button placeholder="请输入套餐名称" v-model="searchWhere.name" @on-search="userSearchs"/>
                        </FormItem>
                    </Col>
                </Row>
                <Row type="flex">
                    <Col v-bind="grid">
                        <Button type="primary" icon="md-add" @click="add">添加套餐</Button>
                    </Col>
                </Row>
            </Form>
            <Table :columns="columns" :data="list" class="mt25" :loading="loading" highlight-row
                   no-userFrom-text="暂无数据" no-filtered-userFrom-text="暂无筛选结果">
                <template slot-scope="{ row }" slot="price">
                    <span>{{ row.price }} 元/月</span>
                </template>
                <template slot-scope="{ row }" slot="quota">
                    <div class="quota-cell">
                        <span>应用 {{ limitText(row.app_limit) }}</span>
                        <span>坐席 {{ limitText(row.seat_limit) }}</span>
                        <span>日消息 {{ limitText(row.daily_msg_limit) }}</span>
                        <span>存储 {{ limitText(row.storage_limit_mb, 'MB') }}</span>
                        <span>记录保留 {{ limitText(row.record_keep_days, '天') }}</span>
                    </div>
                </template>
                <template slot-scope="{ row }" slot="features">
                    <Tag :color="row.auto_reply ? 'green' : 'default'">自动回复</Tag>
                    <Tag :color="row.brand_custom ? 'green' : 'default'">品牌定制</Tag>
                    <Tag :color="row.data_export ? 'green' : 'default'">数据导出</Tag>
                    <Tag :color="row.app_push ? 'green' : 'default'">应用推送</Tag>
                </template>
                <template slot-scope="{ row }" slot="status">
                    <i-switch v-model="row.status" :value="row.status" :true-value="1" :false-value="0" @on-change="onchangeIsShow(row)" size="large">
                        <span slot="open">在售</span>
                        <span slot="close">停售</span>
                    </i-switch>
                </template>
                <template slot-scope="{ row, index }" slot="action">
                    <a @click="edit(row)">编辑</a>
                    <Divider type="vertical"/>
                    <a @click="del(row, '删除套餐', index)">删除</a>
                </template>
            </Table>
        </Card>

        <!-- 创建/编辑套餐 -->
        <Modal v-model="planModal" :title="planForm.id ? '编辑套餐' : '添加套餐'" :closable="false" width="640">
            <Form ref="planForm" :model="planForm" :rules="planRules" :label-width="110">
                <Row :gutter="16">
                    <Col span="12">
                        <FormItem label="套餐名称：" prop="name">
                            <Input v-model="planForm.name" placeholder="如：标准版"/>
                        </FormItem>
                    </Col>
                    <Col span="12">
                        <FormItem label="价格(元/月)：" prop="price">
                            <InputNumber v-model="planForm.price" :min="0" :precision="2" style="width: 100%"/>
                        </FormItem>
                    </Col>
                </Row>
                <Divider orientation="left" size="small">配额（0 表示不限）</Divider>
                <Row :gutter="16">
                    <Col span="12">
                        <FormItem label="应用数上限：">
                            <InputNumber v-model="planForm.app_limit" :min="0" style="width: 100%"/>
                        </FormItem>
                    </Col>
                    <Col span="12">
                        <FormItem label="坐席数上限：">
                            <InputNumber v-model="planForm.seat_limit" :min="0" style="width: 100%"/>
                        </FormItem>
                    </Col>
                    <Col span="12">
                        <FormItem label="日消息上限：">
                            <InputNumber v-model="planForm.daily_msg_limit" :min="0" style="width: 100%"/>
                        </FormItem>
                    </Col>
                    <Col span="12">
                        <FormItem label="存储上限(MB)：">
                            <InputNumber v-model="planForm.storage_limit_mb" :min="0" style="width: 100%"/>
                        </FormItem>
                    </Col>
                    <Col span="12">
                        <FormItem label="记录保留(天)：">
                            <InputNumber v-model="planForm.record_keep_days" :min="0" style="width: 100%"/>
                        </FormItem>
                    </Col>
                    <Col span="12">
                        <FormItem label="排序：">
                            <InputNumber v-model="planForm.sort" :min="0" style="width: 100%"/>
                        </FormItem>
                    </Col>
                </Row>
                <Divider orientation="left" size="small">功能开关</Divider>
                <Row :gutter="16">
                    <Col span="12">
                        <FormItem label="自动回复：">
                            <i-switch v-model="planForm.auto_reply" :true-value="1" :false-value="0"/>
                        </FormItem>
                    </Col>
                    <Col span="12">
                        <FormItem label="品牌定制：">
                            <i-switch v-model="planForm.brand_custom" :true-value="1" :false-value="0"/>
                        </FormItem>
                    </Col>
                    <Col span="12">
                        <FormItem label="数据导出：">
                            <i-switch v-model="planForm.data_export" :true-value="1" :false-value="0"/>
                        </FormItem>
                    </Col>
                    <Col span="12">
                        <FormItem label="应用推送：">
                            <i-switch v-model="planForm.app_push" :true-value="1" :false-value="0"/>
                        </FormItem>
                    </Col>
                </Row>
            </Form>
            <div slot="footer">
                <Button @click="planModal = false">取消</Button>
                <Button type="primary" :loading="submitting" @click="savePlan">确定</Button>
            </div>
        </Modal>
    </div>
</template>

<script>
    import { mapState } from 'vuex'
    import { planListApi, planSaveApi, planUpdateApi, planSetStatusApi } from '@/api/tenant'

    const emptyPlanForm = () => ({
        id: 0,
        name: '',
        price: 0,
        app_limit: 0,
        seat_limit: 0,
        daily_msg_limit: 0,
        storage_limit_mb: 0,
        record_keep_days: 0,
        auto_reply: 0,
        brand_custom: 0,
        data_export: 0,
        app_push: 0,
        sort: 0
    })

    export default {
        name: 'tenant_plan',
        data () {
            return {
                grid: { xl: 7, lg: 7, md: 12, sm: 24, xs: 24 },
                loading: false,
                submitting: false,
                status: '',
                searchWhere: { name: '', status: '' },
                list: [],
                planModal: false,
                planForm: emptyPlanForm(),
                planRules: {
                    name: [{ required: true, message: '请输入套餐名称', trigger: 'blur' }],
                    price: [{ required: true, type: 'number', message: '请输入价格', trigger: 'blur' }]
                },
                columns: [
                    { title: 'ID', key: 'id', width: 70 },
                    { title: '套餐名称', key: 'name', minWidth: 110 },
                    { title: '价格', slot: 'price', minWidth: 100 },
                    { title: '配额', slot: 'quota', minWidth: 220 },
                    { title: '功能', slot: 'features', minWidth: 260 },
                    { title: '使用租户数', key: 'tenant_count', minWidth: 100 },
                    { title: '排序', key: 'sort', width: 70 },
                    { title: '状态', slot: 'status', minWidth: 90 },
                    { title: '操作', slot: 'action', fixed: 'right', minWidth: 120 }
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
            this.getList()
        },
        methods: {
            limitText (value, unit = '') {
                return value > 0 ? `${value}${unit}` : '不限'
            },
            getList () {
                this.loading = true
                planListApi(this.searchWhere).then(res => {
                    this.list = res.data.list
                    this.loading = false
                }).catch(res => {
                    this.loading = false
                    this.$Message.error(res.msg)
                })
            },
            userSearchs () {
                this.searchWhere.status = this.status === 'all' ? '' : this.status
                this.getList()
            },
            onchangeIsShow (row) {
                planSetStatusApi({ id: row.id, status: row.status }).then(res => {
                    this.$Message.success(res.msg)
                }).catch(res => {
                    this.$Message.error(res.msg)
                    row.status = row.status === 1 ? 0 : 1
                })
            },
            add () {
                this.planForm = emptyPlanForm()
                this.planModal = true
            },
            edit (row) {
                const { id, name, price, app_limit, seat_limit, daily_msg_limit, storage_limit_mb, record_keep_days, auto_reply, brand_custom, data_export, app_push, sort } = row
                this.planForm = { id, name, price: Number(price), app_limit, seat_limit, daily_msg_limit, storage_limit_mb, record_keep_days, auto_reply, brand_custom, data_export, app_push, sort }
                this.planModal = true
            },
            savePlan () {
                this.$refs.planForm.validate(valid => {
                    if (!valid) return
                    this.submitting = true
                    const { id, ...data } = this.planForm
                    const req = id ? planUpdateApi(id, data) : planSaveApi(data)
                    req.then(res => {
                        this.submitting = false
                        this.planModal = false
                        this.$Message.success(res.msg)
                        this.getList()
                    }).catch(res => {
                        this.submitting = false
                        this.$Message.error(res.msg)
                    })
                })
            },
            del (row, tit, num) {
                const delfromData = {
                    title: tit,
                    num: num,
                    url: `setting/tenant/plan/${row.id}`,
                    method: 'DELETE',
                    ids: ''
                }
                this.$modalSure(delfromData).then(res => {
                    this.$Message.success(res.msg)
                    this.list.splice(num, 1)
                }).catch(res => {
                    this.$Message.error(res.msg)
                })
            }
        }
    }
</script>

<style scoped>
    .quota-cell {
        display: flex;
        flex-wrap: wrap;
        gap: 2px 10px;
        padding: 4px 0;
    }
</style>
