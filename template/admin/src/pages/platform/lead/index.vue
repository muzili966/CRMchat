<template>
    <div>
        <div class="i-layout-page-header">
            <div class="i-layout-page-header">
                <span class="ivu-page-header-title">{{ $route.meta.title }}</span>
            </div>
        </div>

        <Card :bordered="false" dis-hover class="ivu-mt">
            <!-- 阶段概览兼作筛选：销售最常做的动作就是"只看某个阶段" -->
            <div class="stage-bar">
                <div class="stage-card" :class="{ active: searchWhere.stage === '' }" @click="filterStage('')">
                    <b>{{ totalNum }}</b><span>全部</span>
                </div>
                <div class="stage-card" v-for="item in stat" :key="item.stage"
                     :class="{ active: String(searchWhere.stage) === String(item.stage) }"
                     @click="filterStage(item.stage)">
                    <b>{{ item.num }}</b><span>{{ item.label }}</span>
                </div>
            </div>

            <Form :label-width="labelWidth" :label-position="labelPosition" @submit.native.prevent>
                <Row type="flex" :gutter="24">
                    <Col v-bind="grid">
                        <FormItem label="来源：">
                            <Select v-model="searchWhere.source" placeholder="全部来源" clearable @on-change="search">
                                <Option v-for="item in sources" :value="item.value" :key="item.value">{{ item.label }}</Option>
                            </Select>
                        </FormItem>
                    </Col>
                    <Col v-bind="grid">
                        <FormItem label="搜索：">
                            <Input search enter-button placeholder="联系人 / 公司 / 电话" v-model="searchWhere.keyword" @on-search="search"/>
                        </FormItem>
                    </Col>
                    <Col v-bind="grid">
                        <Button type="primary" icon="md-add" @click="openCreate">录入线索</Button>
                    </Col>
                </Row>
            </Form>

            <Table :columns="columns" :data="list" :loading="loading" highlight-row class="mt25"
                   no-userFrom-text="暂无线索" @on-row-click="openDetail">
                <template slot-scope="{ row }" slot="contact">
                    <div class="lead-name">{{ row.name || '—' }}</div>
                    <div class="lead-sub">{{ row.company || '未填公司' }}</div>
                </template>
                <template slot-scope="{ row }" slot="stage">
                    <Tag :color="stageColor(row.stage)">{{ row.stage_text }}</Tag>
                </template>
                <template slot-scope="{ row }" slot="owner">
                    <span v-if="row.owner_name">{{ row.owner_name }}</span>
                    <span v-else-if="row.from_kefu" class="lead-sub">待认领 · {{ row.from_kefu }}</span>
                    <span v-else class="lead-sub">未指派</span>
                </template>
                <template slot-scope="{ row }" slot="follow">
                    <span v-if="!row._next_follow_time" class="lead-sub">未约定</span>
                    <span v-else :class="{ overdue: row.overdue }">
                        {{ row._next_follow_time }}<template v-if="row.overdue"> · 逾期</template>
                    </span>
                </template>
                <template slot-scope="{ row, index }" slot="action">
                    <a @click.stop="openDetail(row)">跟进</a>
                    <Divider type="vertical"/>
                    <a class="danger-link" @click.stop="del(row, index)">删除</a>
                </template>
            </Table>
            <div class="acea-row row-right page">
                <Page :total="total" :current="searchWhere.page" show-total @on-change="pageChange" :page-size="searchWhere.limit"/>
            </div>
        </Card>

        <!-- 详情抽屉：左侧资料，右侧跟进时间线 -->
        <Drawer v-model="detailDrawer" width="620" :title="detail.name || '线索详情'">
            <Spin fix v-if="detailLoading"></Spin>
            <template v-if="detail.id">
                <div class="detail-head">
                    <Tag :color="stageColor(detail.stage)">{{ detail.stage_text }}</Tag>
                    <span class="lead-sub">{{ detail.source_text }} · {{ detail._create_time }}</span>
                </div>
                <div class="detail-grid">
                    <div><label>公司</label><span>{{ detail.company || '—' }}</span></div>
                    <div><label>电话</label><span>{{ detail.phone || '—' }}</span></div>
                    <div><label>邮箱</label><span>{{ detail.email || '—' }}</span></div>
                    <div><label>团队规模</label><span>{{ detail.scale || '—' }}</span></div>
                    <div><label>意向版本</label><span>{{ detail.intent_plan || '—' }}</span></div>
                    <div><label>跟进人</label><span>{{ detail.owner_name || '未指派' }}</span></div>
                    <div v-if="detail.from_kefu"><label>转入客服</label><span>{{ detail.from_kefu }}</span></div>
                </div>
                <div class="detail-content" v-if="detail.content">
                    <label>需求描述</label>
                    <p>{{ detail.content }}</p>
                </div>

                <Divider orientation="left" size="small">记录跟进</Divider>
                <Form :label-width="80">
                    <FormItem label="跟进内容：">
                        <Input v-model="followForm.content" type="textarea" :rows="3" :maxlength="1000"
                               placeholder="沟通了什么、对方顾虑、下一步安排"/>
                    </FormItem>
                    <FormItem label="推进阶段：">
                        <Select v-model="followForm.stage" placeholder="不变更阶段" clearable style="width:200px">
                            <Option v-for="item in stages" :value="item.value" :key="item.value">{{ item.label }}</Option>
                        </Select>
                    </FormItem>
                    <FormItem label="下次跟进：">
                        <DatePicker v-model="followForm.next" type="date" placeholder="选择日期" style="width:200px"/>
                    </FormItem>
                    <FormItem>
                        <Button type="primary" :loading="submitting" @click="submitFollow">保存跟进</Button>
                        <Button class="ml10" @click="openLink" v-if="detail.stage !== stageWon">标记成交并关联租户</Button>
                    </FormItem>
                </Form>

                <Divider orientation="left" size="small">跟进记录</Divider>
                <div class="timeline" v-if="detail.follows && detail.follows.length">
                    <div class="tl-item" v-for="f in detail.follows" :key="f.id">
                        <div class="tl-head">
                            <b>{{ f.admin_name || '系统' }}</b>
                            <span class="lead-sub">{{ f._create_time }}</span>
                            <Tag size="small" color="blue" v-if="f.stage_to">{{ f.stage_from_text }} → {{ f.stage_to_text }}</Tag>
                        </div>
                        <p v-if="f.content">{{ f.content }}</p>
                    </div>
                </div>
                <div class="lead-sub" v-else>还没有跟进记录</div>
            </template>
        </Drawer>

        <!-- 录入线索 -->
        <Modal v-model="createModal" title="录入线索" :loading="submitting" @on-ok="submitCreate">
            <Form :label-width="90">
                <FormItem label="联系人："><Input v-model="createForm.name" :maxlength="50"/></FormItem>
                <FormItem label="公司名称："><Input v-model="createForm.company" :maxlength="100"/></FormItem>
                <FormItem label="联系电话："><Input v-model="createForm.phone" :maxlength="30"/></FormItem>
                <FormItem label="邮箱："><Input v-model="createForm.email" :maxlength="100"/></FormItem>
                <FormItem label="团队规模："><Input v-model="createForm.scale" :maxlength="30" placeholder="如 20-50人"/></FormItem>
                <FormItem label="意向版本："><Input v-model="createForm.intent_plan" :maxlength="50"/></FormItem>
                <FormItem label="需求描述："><Input v-model="createForm.content" type="textarea" :rows="3" :maxlength="1000"/></FormItem>
            </Form>
        </Modal>

        <!-- 关联租户 -->
        <Modal v-model="linkModal" title="关联租户" :loading="submitting" @on-ok="submitLink">
            <p class="lead-sub mb15">关联后该线索标记为已成交，便于日后追溯这个客户从哪来。</p>
            <Select v-model="linkTenantId" filterable placeholder="选择已开通的租户">
                <Option v-for="item in tenants" :value="item.id" :key="item.id">{{ item.name }}</Option>
            </Select>
        </Modal>
    </div>
</template>

<script>
    import { mapState } from 'vuex'
    import {
        leadListApi, leadOptionsApi, leadInfoApi, leadSaveApi,
        leadFollowApi, leadLinkApi, leadDeleteApi, tenantListApi
    } from '@/api/tenant'

    const STAGE_WON = 4

    const emptyCreateForm = () => ({
        name: '', company: '', phone: '', email: '', scale: '', intent_plan: '', content: ''
    })

    export default {
        name: 'platform_lead',
        data () {
            return {
                grid: { xl: 7, lg: 7, md: 12, sm: 24, xs: 24 },
                loading: false,
                submitting: false,
                list: [],
                total: 0,
                stat: [],
                stages: [],
                sources: [],
                tenants: [],
                stageWon: STAGE_WON,
                searchWhere: { stage: '', source: '', keyword: '', page: 1, limit: 15 },
                columns: [
                    { title: '联系人', slot: 'contact', minWidth: 160 },
                    { title: '电话', key: 'phone', minWidth: 130 },
                    { title: '阶段', slot: 'stage', width: 110 },
                    { title: '来源', key: 'source_text', width: 110 },
                    { title: '跟进人', slot: 'owner', width: 110 },
                    { title: '下次跟进', slot: 'follow', minWidth: 130 },
                    { title: '操作', slot: 'action', fixed: 'right', width: 120 }
                ],
                detailDrawer: false,
                detailLoading: false,
                detail: {},
                followForm: { content: '', stage: '', next: '' },
                createModal: false,
                createForm: emptyCreateForm(),
                linkModal: false,
                linkTenantId: ''
            }
        },
        computed: {
            ...mapState('media', ['isMobile']),
            labelWidth () {
                return this.isMobile ? undefined : 60
            },
            labelPosition () {
                return this.isMobile ? 'top' : 'right'
            },
            totalNum () {
                return this.stat.reduce((sum, item) => sum + item.num, 0)
            }
        },
        created () {
            this.getOptions()
            this.getList()
        },
        methods: {
            stageColor (stage) {
                //已成交与已关闭是终态，用中性色，避免和进行中的线索抢注意力
                return { 1: 'orange', 2: 'blue', 3: 'green', 4: 'default', 5: 'default' }[stage] || 'default'
            },
            getOptions () {
                leadOptionsApi().then(res => {
                    this.stages = res.data.stages || []
                    this.sources = res.data.sources || []
                }).catch(() => {})
            },
            getList () {
                this.loading = true
                leadListApi(this.searchWhere).then(res => {
                    this.list = res.data.list || []
                    this.total = res.data.count || 0
                    this.stat = res.data.stat || []
                    this.loading = false
                }).catch(res => {
                    this.loading = false
                    this.$Message.error(res.msg)
                })
            },
            search () {
                this.searchWhere.page = 1
                this.getList()
            },
            filterStage (stage) {
                this.searchWhere.stage = String(this.searchWhere.stage) === String(stage) ? '' : stage
                this.search()
            },
            pageChange (index) {
                this.searchWhere.page = index
                this.getList()
            },
            openDetail (row) {
                this.detailDrawer = true
                this.detailLoading = true
                this.detail = {}
                this.followForm = { content: '', stage: '', next: '' }
                leadInfoApi(row.id).then(res => {
                    this.detail = res.data || {}
                    this.detailLoading = false
                }).catch(res => {
                    this.detailLoading = false
                    this.$Message.error(res.msg)
                })
            },
            submitFollow () {
                if (!this.followForm.content.trim() && !this.followForm.stage) {
                    return this.$Message.warning('请填写跟进内容或选择要推进的阶段')
                }
                this.submitting = true
                const data = { content: this.followForm.content, stage: this.followForm.stage || 0 }
                //后端按时间戳存，不传则保留原有约定时间
                if (this.followForm.next) {
                    data.next_follow_time = Math.floor(new Date(this.followForm.next).getTime() / 1000)
                }
                leadFollowApi(this.detail.id, data).then(res => {
                    this.submitting = false
                    this.$Message.success(res.msg)
                    this.openDetail(this.detail)
                    this.getList()
                }).catch(res => {
                    this.submitting = false
                    this.$Message.error(res.msg)
                })
            },
            openCreate () {
                this.createForm = emptyCreateForm()
                this.createModal = true
            },
            submitCreate () {
                this.submitting = true
                leadSaveApi(this.createForm).then(res => {
                    this.submitting = false
                    this.createModal = false
                    this.$Message.success(res.msg)
                    this.search()
                }).catch(res => {
                    this.submitting = false
                    //保持弹窗打开，避免已填内容丢失
                    this.$Message.error(res.msg)
                })
            },
            openLink () {
                this.linkTenantId = ''
                this.linkModal = true
                if (this.tenants.length) return
                //复用租户列表接口，取一页足够选择；线索关联的多是新开通的租户
                tenantListApi({ page: 1, limit: 200 }).then(res => {
                    this.tenants = (res.data && res.data.list) || []
                }).catch(() => {})
            },
            submitLink () {
                if (!this.linkTenantId) {
                    this.submitting = false
                    return this.$Message.warning('请选择要关联的租户')
                }
                this.submitting = true
                leadLinkApi(this.detail.id, { tenant_id: this.linkTenantId }).then(res => {
                    this.submitting = false
                    this.linkModal = false
                    this.$Message.success(res.msg)
                    this.openDetail(this.detail)
                    this.getList()
                }).catch(res => {
                    this.submitting = false
                    this.$Message.error(res.msg)
                })
            },
            del (row, index) {
                this.$Modal.confirm({
                    title: '删除线索',
                    content: `确定删除「${row.name || row.company}」这条线索吗？跟进记录将一并不再展示。`,
                    onOk: () => {
                        leadDeleteApi(row.id).then(res => {
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
    .stage-bar {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    .stage-card {
        min-width: 96px;
        padding: 12px 16px;
        border: 1px solid #e5ebf5;
        border-radius: 8px;
        background: #fff;
        cursor: pointer;
        transition: .15s;
    }

    .stage-card:hover {
        border-color: #b8c6e8;
    }

    .stage-card.active {
        border-color: #2d8cf0;
        background: #f2f7ff;
    }

    .stage-card b {
        display: block;
        font-size: 22px;
        line-height: 1.2;
        color: #17233d;
    }

    .stage-card span {
        font-size: 13px;
        color: #808695;
    }

    .lead-name {
        color: #17233d;
    }

    .lead-sub {
        font-size: 12px;
        color: #808695;
    }

    /* 逾期未跟进必须显眼，否则线索就这么放凉了 */
    .overdue {
        color: #ed4014;
        font-weight: 600;
    }

    .detail-head {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 16px;
    }

    .detail-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px 20px;
    }

    .detail-grid label,
    .detail-content label {
        display: inline-block;
        min-width: 62px;
        color: #808695;
        font-size: 13px;
    }

    .detail-content {
        margin-top: 14px;
    }

    .detail-content p {
        margin-top: 6px;
        padding: 10px 12px;
        background: #f6f8fb;
        border-radius: 6px;
        line-height: 1.7;
        white-space: pre-wrap;
    }

    .timeline {
        display: grid;
        gap: 14px;
    }

    .tl-item {
        padding-left: 12px;
        border-left: 2px solid #e5ebf5;
    }

    .tl-head {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .tl-item p {
        margin-top: 4px;
        color: #515a6e;
        line-height: 1.7;
        white-space: pre-wrap;
    }

    .mb15 {
        margin-bottom: 15px;
    }
</style>
