<template>
    <div>
        <div class="i-layout-page-header">
            <div class="i-layout-page-header">
                <span class="ivu-page-header-title">{{ $route.meta.title }}</span>
            </div>
        </div>

        <Card :bordered="false" dis-hover class="ivu-mt">
            <!-- 平台视角维护的是对所有租户强制生效的合规词，说明清楚以免误加业务词 -->
            <Alert v-if="isPlatform" type="warning" show-icon>
                平台级词库
                <template slot="desc">
                    这里的词条对<b>所有租户强制生效</b>，租户不可见也不可修改，用于合规拦截。
                    租户自己的业务词（如防飞单）请让租户在各自后台维护。
                </template>
            </Alert>
            <Alert v-else type="info" show-icon>
                租户词库
                <template slot="desc">
                    这里维护本租户的业务词，例如防止客服私发微信、手机号带走客户。
                    平台的合规词库始终生效，不在此列表中展示。
                </template>
            </Alert>

            <div class="sw-bar">
                <div class="sw-filters">
                    <Input v-model="where.keyword" search enter-button="搜索" placeholder="词条"
                           style="width: 200px" @on-search="reload"/>
                    <Select v-model="where.status" clearable placeholder="全部状态" style="width: 120px"
                            @on-change="reload">
                        <Option :value="1">启用</Option>
                        <Option :value="0">停用</Option>
                    </Select>
                </div>
                <div>
                    <Button type="primary" @click="openForm()">新增词条</Button>
                    <Button class="ml10" @click="importVisible = true">批量导入</Button>
                </div>
            </div>

            <Table :columns="columns" :data="list" :loading="loading" no-data-text="暂无词条">
                <template slot-scope="{ row }" slot="action">
                    <Tag :color="actionColor(row.action)">{{ actionLabel(row.action) }}</Tag>
                </template>
                <template slot-scope="{ row }" slot="scope">
                    <span>{{ scopeLabel(row.scope) }}</span>
                </template>
                <template slot-scope="{ row }" slot="status">
                    <i-switch :value="row.status === 1" @on-change="toggle(row, $event)"/>
                </template>
                <template slot-scope="{ row }" slot="handle">
                    <a @click="openForm(row)">编辑</a>
                    <Divider type="vertical"/>
                    <a class="sw-del" @click="remove(row)">删除</a>
                </template>
            </Table>

            <div class="sw-page">
                <Page :total="total" :current="where.page" :page-size="where.limit" show-total
                      @on-change="onPage"/>
            </div>
        </Card>

        <Modal v-model="formVisible" :title="form.id ? '编辑词条' : '新增词条'" @on-ok="submit"
               :loading="submitting">
            <Form :model="form" :label-width="90">
                <FormItem label="词条">
                    <Input v-model="form.word" placeholder="命中即触发处置，支持中英文"/>
                </FormItem>
                <FormItem label="分类">
                    <Input v-model="form.category" placeholder="选填，便于批量管理"/>
                </FormItem>
                <FormItem label="处置方式">
                    <RadioGroup v-model="form.action">
                        <Radio v-for="a in actionOptions" :key="a.value" :label="a.value">{{ a.label }}</Radio>
                    </RadioGroup>
                    <div class="sw-desc">{{ actionDesc }}</div>
                </FormItem>
                <FormItem label="作用范围">
                    <CheckboxGroup v-model="form.scopeList">
                        <Checkbox v-for="s in scopeOptions" :key="s.value" :label="s.value">{{ s.label }}</Checkbox>
                    </CheckboxGroup>
                    <div class="sw-desc">不勾选任何一项时默认全部生效</div>
                </FormItem>
                <FormItem label="状态">
                    <i-switch v-model="form.status" :true-value="1" :false-value="0"/>
                </FormItem>
                <FormItem label="备注">
                    <Input v-model="form.remark" type="textarea" :rows="2" placeholder="选填"/>
                </FormItem>
            </Form>
        </Modal>

        <Modal v-model="importVisible" title="批量导入" @on-ok="submitImport" :loading="importing">
            <Form :label-width="90">
                <FormItem label="词条">
                    <Input v-model="importForm.words" type="textarea" :rows="8"
                           placeholder="一行一个词，也可用逗号、顿号分隔；已存在的会自动跳过"/>
                </FormItem>
                <FormItem label="分类">
                    <Input v-model="importForm.category" placeholder="选填，本批词条统一使用"/>
                </FormItem>
                <FormItem label="处置方式">
                    <RadioGroup v-model="importForm.action">
                        <Radio v-for="a in actionOptions" :key="a.value" :label="a.value">{{ a.label }}</Radio>
                    </RadioGroup>
                </FormItem>
                <FormItem label="作用范围">
                    <CheckboxGroup v-model="importForm.scopeList">
                        <Checkbox v-for="s in scopeOptions" :key="s.value" :label="s.value">{{ s.label }}</Checkbox>
                    </CheckboxGroup>
                </FormItem>
            </Form>
        </Modal>
    </div>
</template>

<script>
    import {
        sensitiveWordListApi, sensitiveWordSaveApi, sensitiveWordUpdateApi,
        sensitiveWordDeleteApi, sensitiveWordImportApi
    } from '@/api/sensitive'
    import {
        ACTION_OPTIONS, SCOPE_OPTIONS, SCOPE_ALL, ACTION_BLOCK, ACTION_REPLACE,
        scopeToArray, scopeToMask, actionText, scopeText
    } from '@/config/sensitive'

    const ACTION_COLOR = {
        [ACTION_BLOCK]: 'red',
        [ACTION_REPLACE]: 'orange'
    }

    export default {
        name: 'sensitiveWord',
        data () {
            return {
                loading: false,
                submitting: false,
                importing: false,
                formVisible: false,
                importVisible: false,
                isPlatform: false,
                list: [],
                total: 0,
                where: { keyword: '', status: '', page: 1, limit: 20 },
                form: this.emptyForm(),
                importForm: { words: '', category: '', action: ACTION_REPLACE, scopeList: scopeToArray(SCOPE_ALL) },
                actionOptions: ACTION_OPTIONS,
                scopeOptions: SCOPE_OPTIONS,
                columns: [
                    { title: '词条', key: 'word', minWidth: 140 },
                    { title: '分类', key: 'category', width: 110 },
                    { title: '处置', slot: 'action', width: 100 },
                    { title: '作用范围', slot: 'scope', width: 170 },
                    { title: '状态', slot: 'status', width: 90 },
                    { title: '备注', key: 'remark', minWidth: 120 },
                    { title: '创建时间', key: '_create_time', width: 150 },
                    { title: '操作', slot: 'handle', width: 110 }
                ]
            }
        },
        computed: {
            actionDesc () {
                const found = ACTION_OPTIONS.find(item => item.value === this.form.action)
                return found ? found.desc : ''
            }
        },
        created () {
            this.getList()
        },
        methods: {
            emptyForm () {
                return {
                    id: 0, word: '', category: '', action: ACTION_REPLACE,
                    scopeList: scopeToArray(SCOPE_ALL), status: 1, remark: ''
                }
            },
            actionLabel (action) { return actionText(action) },
            actionColor (action) { return ACTION_COLOR[action] || 'default' },
            scopeLabel (mask) { return scopeText(mask) },
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
                sensitiveWordListApi(this.where).then(res => {
                    this.list = (res.data && res.data.list) || []
                    this.total = (res.data && res.data.count) || 0
                    this.isPlatform = !!(res.data && res.data.is_platform)
                    this.loading = false
                }).catch(res => {
                    this.loading = false
                    this.$Message.error(res.msg)
                })
            },
            openForm (row) {
                this.form = row
                    ? { ...row, scopeList: scopeToArray(row.scope) }
                    : this.emptyForm()
                this.formVisible = true
            },
            submit () {
                const payload = { ...this.form, scope: scopeToMask(this.form.scopeList) }
                delete payload.scopeList
                this.submitting = true
                const request = payload.id
                    ? sensitiveWordUpdateApi(payload.id, payload)
                    : sensitiveWordSaveApi(payload)
                request.then(res => {
                    this.submitting = false
                    this.formVisible = false
                    this.$Message.success(res.msg)
                    this.getList()
                }).catch(res => {
                    //保存失败要留在弹窗里让用户改，不能关掉丢失已填内容
                    this.submitting = false
                    this.$Message.error(res.msg)
                })
            },
            toggle (row, checked) {
                sensitiveWordUpdateApi(row.id, { status: checked ? 1 : 0 }).then(() => {
                    this.getList()
                }).catch(res => {
                    this.$Message.error(res.msg)
                    this.getList()
                })
            },
            remove (row) {
                this.$Modal.confirm({
                    title: '删除词条',
                    content: `确定删除「${row.word}」？删除后该词不再拦截。`,
                    onOk: () => {
                        sensitiveWordDeleteApi(row.id).then(res => {
                            this.$Message.success(res.msg)
                            this.getList()
                        }).catch(res => this.$Message.error(res.msg))
                    }
                })
            },
            submitImport () {
                const payload = { ...this.importForm, scope: scopeToMask(this.importForm.scopeList) }
                delete payload.scopeList
                this.importing = true
                sensitiveWordImportApi(payload).then(res => {
                    this.importing = false
                    this.importVisible = false
                    this.importForm.words = ''
                    this.$Message.success(res.msg)
                    this.getList()
                }).catch(res => {
                    this.importing = false
                    this.$Message.error(res.msg)
                })
            }
        }
    }
</script>

<style scoped>
    .sw-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin: 16px 0;
    }
    .sw-filters {
        display: flex;
        gap: 10px;
    }
    .sw-page {
        margin-top: 16px;
        text-align: right;
    }
    .sw-desc {
        color: #a3aab8;
        font-size: 12px;
        line-height: 1.6;
    }
    .sw-del {
        color: #ed4014;
    }
</style>
