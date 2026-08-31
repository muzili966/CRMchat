<template>
    <div>
        <div class="i-layout-page-header">
            <div class="i-layout-page-header">
                <span class="ivu-page-header-title">{{$route.meta.title}}</span>
            </div>
        </div>
        <planGate feature="ai_reply">
        <Card :bordered="false" dis-hover class="ivu-mt">
            <Tabs v-model="curTab">
                <TabPane label="基础设置" name="base">
                    <div class="usage-bar" v-if="hasUsage">
                        <span class="usage-item">近7天调用 <b>{{ usage.total_count }}</b> 次</span>
                        <Divider type="vertical"/>
                        <span class="usage-item">输入 token <b>{{ usage.prompt_tokens }}</b></span>
                        <Divider type="vertical"/>
                        <span class="usage-item">输出 token <b>{{ usage.completion_tokens }}</b></span>
                    </div>
                    <Form :model="form" :label-width="120" @submit.native.prevent>
                        <FormItem label="启用AI客服：">
                            <i-switch v-model="form.enable" :true-value="1" :false-value="0" size="large">
                                <span slot="open">开启</span>
                                <span slot="close">关闭</span>
                            </i-switch>
                        </FormItem>
                        <FormItem label="接待模式：">
                            <RadioGroup v-model="form.mode" class="mode-group">
                                <div class="mode-item" v-for="item in modeOptions" :key="item.value">
                                    <Radio :label="item.value">{{ item.label }}</Radio>
                                    <p class="mode-tip">{{ item.tip }}</p>
                                </div>
                            </RadioGroup>
                        </FormItem>
                        <FormItem label="AI欢迎语：">
                            <Input v-model="form.greeting" type="textarea" :rows="2"
                                   placeholder="AI 接待时的第一句话，留空则使用平台默认欢迎语"/>
                        </FormItem>
                        <FormItem label="身份设定：">
                            <Input v-model="form.system_prompt" type="textarea" :rows="6"
                                   :placeholder="promptPlaceholder"/>
                            <div class="counter" :class="{ 'counter-over': promptOver }">
                                {{ promptLength }}/{{ promptMax }}
                            </div>
                        </FormItem>
                        <FormItem label="转人工关键词：">
                            <Input v-model="form.transfer_keywords" placeholder="如：人工,转人工,投诉"/>
                            <p class="field-tip">逗号分隔，访客消息命中后直接转人工</p>
                        </FormItem>
                        <FormItem label="指定模型：">
                            <Input v-model="form.model" placeholder="留空使用平台默认"/>
                        </FormItem>
                    </Form>
                </TabPane>
                <TabPane label="常见问题" name="faq">
                    <div class="faq-summary" :class="{ 'counter-over': faqOver }">
                        共 {{ form.faq.length }} 条 / 总长 {{ faqLength }} 字（上限 {{ faqMax }}）
                    </div>
                    <div class="faq-empty" v-if="!form.faq.length">
                        暂无常见问题，AI 会优先按这里的标准答复回答访客
                    </div>
                    <div class="faq-item" v-for="(item, index) in form.faq" :key="index">
                        <Row :gutter="12">
                            <Col span="7">
                                <Input v-model="item.q" placeholder="访客会怎么问，如：怎么申请退款？"/>
                            </Col>
                            <Col span="14">
                                <Input v-model="item.a" type="textarea" :rows="2" placeholder="标准答复"/>
                            </Col>
                            <Col span="3">
                                <a class="faq-del" @click="removeFaq(index)">删除</a>
                            </Col>
                        </Row>
                    </div>
                    <Button type="dashed" long icon="md-add" class="faq-add" @click="addFaq">添加一条</Button>
                </TabPane>
            </Tabs>
            <div class="save-bar">
                <Button type="primary" :loading="saving" @click="save">保存</Button>
            </div>
        </Card>
        </planGate>
    </div>
</template>

<script>
    import { aiConfigApi, aiConfigSaveApi } from '@/api/ai'
    import planGate from '@/components/planGate'

    const MODE_STANDBY = 'standby'
    const MODE_AI_FIRST = 'ai_first'
    const ENABLE_ON = 1
    const ENABLE_OFF = 0
    const PROMPT_MAX = 4000
    const FAQ_MAX = 8000
    const PROMPT_PLACEHOLDER = '描述 AI 的身份与业务范围，例如：你是XX品牌的在线客服，主营家用净水器的销售与售后，'
        + '熟悉发货、退换货、滤芯更换周期等问题；回答简洁友好，不确定的问题引导访客转人工。'

    const MODE_OPTIONS = [
        {
            value: MODE_STANDBY,
            label: '值守（无人在线时 AI 接待，推荐）',
            tip: '有客服在线时全部走人工，只有客服都离线或长时间未响应才由 AI 顶上，对现有接待流程改动最小。'
        },
        {
            value: MODE_AI_FIRST,
            label: 'AI 优先（AI 先接待，可随时转人工）',
            tip: 'AI 先应答所有进线，命中转人工关键词或访客主动要求时立即转接人工，适合咨询量大、重复问题多的场景。'
        }
    ]

    const createForm = () => ({
        enable: ENABLE_OFF,
        mode: MODE_STANDBY,
        greeting: '',
        system_prompt: '',
        faq: [],
        transfer_keywords: '',
        model: ''
    })

    // 后端 json 字段可能以字符串或数组两种形态返回，统一成 {q, a} 数组
    const normalizeFaq = faq => {
        const list = typeof faq === 'string' ? safeParse(faq) : faq
        if (!Array.isArray(list)) return []
        return list.map(item => ({ q: item.q || '', a: item.a || '' }))
    }

    const safeParse = text => {
        if (!text) return []
        try {
            return JSON.parse(text)
        } catch (e) {
            console.error('AI常见问题解析失败', e)
            return []
        }
    }

    const isBlankFaq = item => !item.q.trim() && !item.a.trim()

    export default {
        components: { planGate },
        name: 'chat_ai',
        data () {
            return {
                curTab: 'base',
                saving: false,
                form: createForm(),
                usage: null,
                modeOptions: MODE_OPTIONS,
                promptPlaceholder: PROMPT_PLACEHOLDER,
                promptMax: PROMPT_MAX,
                faqMax: FAQ_MAX
            }
        },
        computed: {
            hasUsage () {
                return !!this.usage && this.usage.total_count > 0
            },
            promptLength () {
                return this.form.system_prompt.length
            },
            promptOver () {
                return this.promptLength > PROMPT_MAX
            },
            faqLength () {
                return this.form.faq.reduce((total, item) => total + item.q.length + item.a.length, 0)
            },
            faqOver () {
                return this.faqLength > FAQ_MAX
            }
        },
        created () {
            this.getConfig()
        },
        methods: {
            getConfig () {
                aiConfigApi().then(res => {
                    this.fillForm(res.data || {})
                }).catch(res => {
                    this.$Message.error(res.msg)
                })
            },
            fillForm (data) {
                const config = data.config || {}
                this.form = Object.assign(createForm(), config, { faq: normalizeFaq(config.faq) })
                this.usage = data.usage || null
            },
            addFaq () {
                this.form.faq.push({ q: '', a: '' })
            },
            removeFaq (index) {
                this.form.faq.splice(index, 1)
            },
            // 返回空串表示校验通过
            checkForm () {
                if (this.form.enable === ENABLE_ON && !this.form.system_prompt.trim()) {
                    return '启用AI客服时，身份设定不能为空'
                }
                if (this.promptOver) return `身份设定不能超过 ${PROMPT_MAX} 字`
                if (this.faqOver) return `常见问题总长不能超过 ${FAQ_MAX} 字`
                return ''
            },
            buildPayload () {
                return Object.assign({}, this.form, {
                    faq: this.form.faq.filter(item => !isBlankFaq(item))
                })
            },
            save () {
                const error = this.checkForm()
                if (error) return this.$Message.error(error)
                this.saving = true
                aiConfigSaveApi(this.buildPayload()).then(res => {
                    this.saving = false
                    this.$Message.success(res.msg)
                }).catch(res => {
                    this.saving = false
                    this.$Message.error(res.msg)
                })
            }
        }
    }
</script>

<style scoped>
    .usage-bar {
        background: #f8f8f9;
        border-radius: 4px;
        padding: 10px 16px;
        margin-bottom: 20px;
        color: #515a6e;
        font-size: 13px;
    }
    .usage-item b {
        color: #2d8cf0;
        font-weight: 600;
        padding: 0 2px;
    }
    .mode-group {
        display: block;
    }
    .mode-item {
        margin-bottom: 8px;
    }
    .mode-tip {
        color: #808695;
        font-size: 12px;
        line-height: 1.6;
        padding-left: 22px;
    }
    .field-tip {
        color: #808695;
        font-size: 12px;
        line-height: 1.6;
    }
    .counter {
        text-align: right;
        color: #808695;
        font-size: 12px;
        line-height: 1.6;
    }
    .counter-over {
        color: #ed4014;
    }
    .faq-summary {
        color: #515a6e;
        font-size: 13px;
        margin-bottom: 16px;
    }
    .faq-empty {
        color: #808695;
        font-size: 13px;
        text-align: center;
        padding: 24px 0;
    }
    .faq-item {
        margin-bottom: 12px;
    }
    .faq-del {
        line-height: 32px;
    }
    .faq-add {
        margin-top: 4px;
    }
    .save-bar {
        border-top: 1px solid #e8eaec;
        padding-top: 16px;
        margin-top: 8px;
    }
</style>
