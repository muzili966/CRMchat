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
                                <Option value="1">正常</Option>
                                <Option value="0">禁用</Option>
                            </Select>
                        </FormItem>
                    </Col>
                    <Col v-bind="grid">
                        <FormItem label="搜索：" label-for="name">
                            <Input search enter-button placeholder="请输入租户名称" v-model="searchWhere.name" @on-search="userSearchs"/>
                        </FormItem>
                    </Col>
                </Row>
                <Row type="flex">
                    <Col v-bind="grid">
                        <Button type="primary" icon="md-add" @click="add">添加租户</Button>
                    </Col>
                </Row>
            </Form>
            <Table :columns="columns" :data="list" class="mt25" :loading="loading" highlight-row
                   no-userFrom-text="暂无数据" no-filtered-userFrom-text="暂无筛选结果">
                <template slot-scope="{ row }" slot="plan">
                    <Tag v-if="row.plan_id && planMap[row.plan_id]" color="blue">{{ planMap[row.plan_id] }}</Tag>
                    <span v-else>-</span>
                </template>
                <template slot-scope="{ row }" slot="expire">
                    <span :class="{ 'expire-danger': isExpired(row) }">{{ row._expire_time }}</span>
                </template>
                <template slot-scope="{ row }" slot="status">
                    <i-switch v-model="row.status" :value="row.status" :true-value="1" :false-value="0" @on-change="onchangeIsShow(row)" size="large">
                        <span slot="open">正常</span>
                        <span slot="close">禁用</span>
                    </i-switch>
                </template>
                <template slot-scope="{ row }" slot="action">
                    <a @click="edit(row)">编辑</a>
                    <Divider type="vertical"/>
                    <a @click="subscribe(row)">开通续费</a>
                    <Divider type="vertical"/>
                    <a @click="createAdmin(row)">创建管理员</a>
                    <Divider type="vertical"/>
                    <a @click="enterTenant(row)">以该租户身份查看</a>
                </template>
            </Table>
            <div class="acea-row row-right page">
                <Page :total="total" :current="searchWhere.page" show-elevator show-total @on-change="pageChange" :page-size="searchWhere.limit"/>
            </div>
        </Card>

        <!-- 创建/编辑租户 -->
        <Modal v-model="tenantModal" :title="tenantForm.id ? '编辑租户' : '添加租户'" :closable="false">
            <Form ref="tenantForm" :model="tenantForm" :rules="tenantRules" :label-width="90">
                <FormItem label="租户名称：" prop="name">
                    <Input v-model="tenantForm.name" placeholder="请输入租户名称"/>
                </FormItem>
                <FormItem label="独立域名：">
                    <Input v-model="tenantForm.domain" placeholder="选填，如 tenant.example.com"/>
                </FormItem>
                <FormItem label="备注：">
                    <Input v-model="tenantForm.remark" type="textarea" :rows="3" placeholder="选填"/>
                </FormItem>
                <template v-if="!tenantForm.id">
                    <Divider orientation="left" size="small">初始管理员</Divider>
                    <FormItem label="管理员账号：" prop="admin_account">
                        <Input v-model="tenantForm.admin_account" placeholder="登录账号，全局唯一"/>
                    </FormItem>
                    <FormItem label="管理员密码：" prop="admin_pwd">
                        <Input v-model="tenantForm.admin_pwd" type="password" placeholder="登录密码"/>
                    </FormItem>
                    <FormItem label="确认密码：" prop="admin_conf_pwd">
                        <Input v-model="tenantForm.admin_conf_pwd" type="password" placeholder="再次输入密码"/>
                    </FormItem>
                </template>
            </Form>
            <div slot="footer">
                <Button @click="tenantModal = false">取消</Button>
                <Button type="primary" :loading="submitting" @click="saveTenant">确定</Button>
            </div>
        </Modal>

        <!-- 开通/续费套餐 -->
        <Modal v-model="subscribeModal" :title="`开通续费 - ${currentRow.name || ''}`" :closable="false">
            <Form ref="subscribeForm" :model="subscribeForm" :rules="subscribeRules" :label-width="90">
                <FormItem label="套餐：" prop="plan_id">
                    <Select v-model="subscribeForm.plan_id" placeholder="请选择套餐">
                        <Option v-for="item in planOptions" :value="item.id" :key="item.id">{{ item.name }}（{{ item.price }}元/月）</Option>
                    </Select>
                </FormItem>
                <FormItem label="订购月数：" prop="months">
                    <InputNumber v-model="subscribeForm.months" :min="1" :max="60" style="width: 100%"/>
                </FormItem>
                <FormItem label="支付方式：">
                    <RadioGroup v-model="subscribeForm.pay_type">
                        <Radio :label="1">后台开通</Radio>
                        <Radio :label="2">线下转账</Radio>
                    </RadioGroup>
                </FormItem>
                <FormItem label="备注：">
                    <Input v-model="subscribeForm.remark" type="textarea" :rows="2" placeholder="选填"/>
                </FormItem>
            </Form>
            <div slot="footer">
                <Button @click="subscribeModal = false">取消</Button>
                <Button type="primary" :loading="submitting" @click="saveSubscribe">确定开通</Button>
            </div>
        </Modal>

        <!-- 创建租户管理员 -->
        <Modal v-model="adminModal" :title="`创建管理员 - ${currentRow.name || ''}`" :closable="false">
            <Form ref="adminForm" :model="adminForm" :rules="adminRules" :label-width="90">
                <FormItem label="账号：" prop="account">
                    <Input v-model="adminForm.account" placeholder="登录账号，全局唯一"/>
                </FormItem>
                <FormItem label="姓名：" prop="real_name">
                    <Input v-model="adminForm.real_name" placeholder="管理员姓名"/>
                </FormItem>
                <FormItem label="密码：" prop="pwd">
                    <Input v-model="adminForm.pwd" type="password" placeholder="登录密码"/>
                </FormItem>
                <FormItem label="确认密码：" prop="conf_pwd">
                    <Input v-model="adminForm.conf_pwd" type="password" placeholder="再次输入密码"/>
                </FormItem>
            </Form>
            <div slot="footer">
                <Button @click="adminModal = false">取消</Button>
                <Button type="primary" :loading="submitting" @click="saveAdmin">确定创建</Button>
            </div>
        </Modal>
    </div>
</template>

<script>
    import { mapState } from 'vuex'
    import { setViewTenant } from '@/libs/tenantView'
    import { tenantListApi, tenantSaveApi, tenantUpdateApi, tenantSetStatusApi, tenantCreateAdminApi, tenantSubscribeApi, planAllApi } from '@/api/tenant'

    const emptyTenantForm = () => ({ id: 0, name: '', domain: '', remark: '', admin_account: '', admin_pwd: '', admin_conf_pwd: '' })
    const emptySubscribeForm = () => ({ tenant_id: 0, plan_id: '', months: 1, pay_type: 1, remark: '' })
    const emptyAdminForm = () => ({ tenant_id: 0, account: '', real_name: '', pwd: '', conf_pwd: '' })

    export default {
        name: 'tenant_list',
        data () {
            return {
                grid: { xl: 7, lg: 7, md: 12, sm: 24, xs: 24 },
                loading: false,
                submitting: false,
                total: 0,
                status: '',
                searchWhere: { name: '', status: '', page: 1, limit: 20 },
                list: [],
                planOptions: [],
                currentRow: {},
                tenantModal: false,
                subscribeModal: false,
                adminModal: false,
                tenantForm: emptyTenantForm(),
                subscribeForm: emptySubscribeForm(),
                adminForm: emptyAdminForm(),
                tenantRules: {
                    name: [{ required: true, message: '请输入租户名称', trigger: 'blur' }],
                    admin_account: [{ required: true, message: '请输入管理员账号', trigger: 'blur' }],
                    admin_pwd: [{ required: true, message: '请输入管理员密码', trigger: 'blur' }],
                    admin_conf_pwd: [{ required: true, message: '请再次输入密码', trigger: 'blur' }]
                },
                subscribeRules: {
                    plan_id: [{ required: true, type: 'number', message: '请选择套餐', trigger: 'change' }]
                },
                adminRules: {
                    account: [{ required: true, message: '请输入账号', trigger: 'blur' }],
                    real_name: [{ required: true, message: '请输入姓名', trigger: 'blur' }],
                    pwd: [{ required: true, message: '请输入密码', trigger: 'blur' }],
                    conf_pwd: [{ required: true, message: '请再次输入密码', trigger: 'blur' }]
                },
                columns: [
                    { title: 'ID', key: 'id', width: 70 },
                    { title: '租户名称', key: 'name', minWidth: 140 },
                    { title: '当前套餐', slot: 'plan', minWidth: 100 },
                    { title: '到期时间', slot: 'expire', minWidth: 150 },
                    { title: '独立域名', key: 'domain', minWidth: 140 },
                    { title: '状态', slot: 'status', minWidth: 90 },
                    { title: '创建时间', key: '_create_time', minWidth: 150 },
                    { title: '备注', key: 'remark', minWidth: 120 },
                    { title: '操作', slot: 'action', fixed: 'right', minWidth: 200 }
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
            planMap () {
                return this.planOptions.reduce((map, item) => ({ ...map, [item.id]: item.name }), {})
            }
        },
        created () {
            this.getList()
            this.getPlanOptions()
        },
        methods: {
            //技术支持场景：平台需要看到租户实际配了什么。
            //切换后所有后台请求自动带 tenant_id，可用页面随之变为该租户的
            enterTenant (row) {
                this.$Modal.confirm({
                    title: '切换租户视角',
                    content: `将以「${row.name}」的身份查看后台，期间的查看与修改都作用于该租户。可随时退出。`,
                    okText: '进入',
                    onOk: () => {
                        setViewTenant({ id: row.id, name: row.name })
                        //权限随视角变化，整页重载最省心，也避免残留的旧数据
                        window.location.href = '/admin'
                    }
                })
            },
            isExpired (row) {
                return row.expire_time > 0 && row.expire_time * 1000 < Date.now()
            },
            getPlanOptions () {
                planAllApi().then(res => {
                    this.planOptions = res.data || []
                }).catch(res => {
                    this.$Message.error(res.msg)
                })
            },
            getList () {
                this.loading = true
                tenantListApi(this.searchWhere).then(res => {
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
            onchangeIsShow (row) {
                tenantSetStatusApi({ id: row.id, status: row.status }).then(res => {
                    this.$Message.success(res.msg)
                }).catch(res => {
                    this.$Message.error(res.msg)
                    row.status = row.status === 1 ? 0 : 1
                })
            },
            add () {
                this.tenantForm = emptyTenantForm()
                this.$refs.tenantForm && this.$refs.tenantForm.resetFields()
                this.tenantModal = true
            },
            edit (row) {
                this.tenantForm = { id: row.id, name: row.name, domain: row.domain, remark: row.remark }
                this.tenantModal = true
            },
            saveTenant () {
                this.$refs.tenantForm.validate(valid => {
                    if (!valid) return
                    if (!this.tenantForm.id && this.tenantForm.admin_pwd !== this.tenantForm.admin_conf_pwd) {
                        return this.$Message.error('两次输入的管理员密码不一致')
                    }
                    this.submitting = true
                    const { id, ...data } = this.tenantForm
                    const req = id ? tenantUpdateApi(id, data) : tenantSaveApi(data)
                    req.then(res => {
                        this.submitting = false
                        this.tenantModal = false
                        this.$Message.success(res.msg)
                        this.getList()
                    }).catch(res => {
                        this.submitting = false
                        this.$Message.error(res.msg)
                    })
                })
            },
            subscribe (row) {
                this.currentRow = row
                this.subscribeForm = { ...emptySubscribeForm(), tenant_id: row.id }
                this.subscribeModal = true
            },
            saveSubscribe () {
                this.$refs.subscribeForm.validate(valid => {
                    if (!valid) return
                    this.submitting = true
                    tenantSubscribeApi(this.subscribeForm).then(res => {
                        this.submitting = false
                        this.subscribeModal = false
                        this.$Message.success(res.msg)
                        this.getList()
                    }).catch(res => {
                        this.submitting = false
                        this.$Message.error(res.msg)
                    })
                })
            },
            createAdmin (row) {
                this.currentRow = row
                this.adminForm = { ...emptyAdminForm(), tenant_id: row.id }
                this.adminModal = true
            },
            saveAdmin () {
                this.$refs.adminForm.validate(valid => {
                    if (!valid) return
                    if (this.adminForm.pwd !== this.adminForm.conf_pwd) {
                        return this.$Message.error('两次输入的密码不一致')
                    }
                    this.submitting = true
                    tenantCreateAdminApi(this.adminForm).then(res => {
                        this.submitting = false
                        this.adminModal = false
                        this.$Message.success(res.msg)
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
    .expire-danger {
        color: #ed4014;
    }
</style>
