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
                        <FormItem label="类型：" label-for="type">
                            <Select v-model="type" placeholder="请选择" @on-change="userSearchs" clearable>
                                <Option value="all">全部</Option>
                                <Option value="announce">平台公告</Option>
                                <Option value="expire_warn">即将到期</Option>
                                <Option value="expired">已到期</Option>
                                <Option value="renew">续费成功</Option>
                            </Select>
                        </FormItem>
                    </Col>
                    <Col v-bind="grid">
                        <FormItem label="状态：" label-for="is_read">
                            <Select v-model="isRead" placeholder="请选择" @on-change="userSearchs" clearable>
                                <Option value="all">全部</Option>
                                <Option value="0">未读</Option>
                                <Option value="1">已读</Option>
                            </Select>
                        </FormItem>
                    </Col>
                </Row>
                <Row type="flex">
                    <Col v-bind="grid">
                        <Button type="primary" icon="md-send" @click="openSend">发送通知</Button>
                    </Col>
                </Row>
            </Form>
            <Table :columns="columns" :data="list" class="mt25" :loading="loading" highlight-row
                   no-userFrom-text="暂无数据" no-filtered-userFrom-text="暂无筛选结果">
                <template slot-scope="{ row }" slot="tenant">
                    <span>{{ tenantMap[row.tenant_id] || `租户#${row.tenant_id}` }}</span>
                </template>
                <template slot-scope="{ row }" slot="type">
                    <Tag :color="typeColor(row.type)">{{ typeText(row.type) }}</Tag>
                </template>
                <template slot-scope="{ row }" slot="is_read">
                    <Badge :status="row.is_read ? 'default' : 'processing'" :text="row.is_read ? '已读' : '未读'"/>
                </template>
                <template slot-scope="{ row }" slot="action">
                    <a v-if="!row.is_read" @click="markRead(row)">标记已读</a>
                    <span v-else>-</span>
                </template>
            </Table>
            <div class="acea-row row-right page">
                <Page :total="total" :current="searchWhere.page" show-elevator show-total @on-change="pageChange" :page-size="searchWhere.limit"/>
            </div>
        </Card>

        <!-- 发送通知 -->
        <Modal v-model="sendModal" title="发送通知" :closable="false">
            <Form ref="sendForm" :model="sendForm" :rules="sendRules" :label-width="90">
                <FormItem label="目标租户：">
                    <Select v-model="sendForm.tenant_ids" multiple placeholder="不选则发送给全部正常租户">
                        <Option v-for="(name, id) in tenantMap" :value="Number(id)" :key="id">{{ name }}</Option>
                    </Select>
                </FormItem>
                <FormItem label="通知内容：" prop="content">
                    <Input v-model="sendForm.content" type="textarea" :rows="4" placeholder="请输入通知内容"/>
                </FormItem>
            </Form>
            <div slot="footer">
                <Button @click="sendModal = false">取消</Button>
                <Button type="primary" :loading="submitting" @click="saveSend">发送</Button>
            </div>
        </Modal>
    </div>
</template>

<script>
    import { mapState } from 'vuex'
    import { noticeListApi, noticeReadApi, noticeSendApi, tenantListApi } from '@/api/tenant'

    const TYPE_TEXT = { announce: '平台公告', expire_warn: '即将到期', expired: '已到期', renew: '续费成功' }
    const TYPE_COLOR = { announce: 'blue', expire_warn: 'orange', expired: 'red', renew: 'green' }

    export default {
        name: 'tenant_notice',
        data () {
            return {
                grid: { xl: 7, lg: 7, md: 12, sm: 24, xs: 24 },
                loading: false,
                submitting: false,
                total: 0,
                type: '',
                isRead: '',
                searchWhere: { type: '', is_read: '', page: 1, limit: 20 },
                list: [],
                tenantMap: {},
                sendModal: false,
                sendForm: { tenant_ids: [], content: '' },
                sendRules: {
                    content: [{ required: true, message: '请输入通知内容', trigger: 'blur' }]
                },
                columns: [
                    { title: 'ID', key: 'id', width: 70 },
                    { title: '租户', slot: 'tenant', minWidth: 120 },
                    { title: '类型', slot: 'type', minWidth: 100 },
                    { title: '通知内容', key: 'content', minWidth: 300 },
                    { title: '状态', slot: 'is_read', minWidth: 90 },
                    { title: '时间', key: '_create_time', minWidth: 150 },
                    { title: '操作', slot: 'action', fixed: 'right', minWidth: 100 }
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
            typeText (type) {
                return TYPE_TEXT[type] || type
            },
            typeColor (type) {
                return TYPE_COLOR[type] || 'default'
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
                noticeListApi(this.searchWhere).then(res => {
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
                this.searchWhere.type = this.type === 'all' ? '' : this.type
                this.searchWhere.is_read = this.isRead === 'all' ? '' : this.isRead
                this.searchWhere.page = 1
                this.getList()
            },
            markRead (row) {
                noticeReadApi(row.id).then(res => {
                    this.$Message.success(res.msg)
                    row.is_read = 1
                }).catch(res => {
                    this.$Message.error(res.msg)
                })
            },
            openSend () {
                this.sendForm = { tenant_ids: [], content: '' }
                this.sendModal = true
            },
            saveSend () {
                this.$refs.sendForm.validate(valid => {
                    if (!valid) return
                    this.submitting = true
                    noticeSendApi(this.sendForm).then(res => {
                        this.submitting = false
                        this.sendModal = false
                        this.$Message.success(res.msg)
                        this.getList()
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

</style>
