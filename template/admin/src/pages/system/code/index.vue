<template>
  <div>
    <div class="i-layout-page-header">
      <div class="i-layout-page-header">
        <span class="ivu-page-header-title">应用管理</span>
      </div>
    </div>
    <Card :bordered="false" dis-hover class="ivu-mt">
      <Form ref="searchForm" :model="searchWhere" :label-width="labelWidth" :label-position="labelPosition" @submit.native.prevent>
        <Row type="flex" :gutter="24">
          <Col v-bind="grid">
            <FormItem label="搜索：" label-for="name">
              <Input search enter-button placeholder="请输入应用名称" v-model="searchWhere.name" @on-search="userSearchs"/>
            </FormItem>
          </Col>
        </Row>
        <Row type="flex">
          <Col v-bind="grid">
            <Button type="primary" icon="md-add" @click="add">添加应用</Button>
          </Col>
        </Row>
      </Form>
      <Table :columns="columns" :data="list" class="mt25" :loading="loading" highlight-row no-userFrom-text="暂无数据">
        <template slot-scope="{ row }" slot="icon">
          <viewer>
            <div class="tabBox_img"><img v-lazy="row.icon" v-if="row.icon"></div>
          </viewer>
        </template>
        <template slot-scope="{ row }" slot="auth_mode">
          <Tag :color="row.auth_mode === 1 ? 'green' : 'orange'">
            {{ row.auth_mode === 1 ? '签名模式' : '兼容模式' }}
          </Tag>
        </template>
        <template slot-scope="{ row }" slot="link">
          <linkWithQr :link="chatLink(row)"/>
        </template>
        <template slot-scope="{ row, index }" slot="action">
          <a @click="showCode(row)">接入代码</a>
          <Divider type="vertical"/>
          <a @click="edit(row)">编辑</a>
          <Divider type="vertical"/>
          <a @click="resetToken(row)">重置token</a>
          <Divider type="vertical"/>
          <a class="danger-link" @click="del(row, index)">删除</a>
        </template>
      </Table>
      <div class="acea-row row-right page">
        <Page :total="total" :current="searchWhere.page" show-total @on-change="pageChange" :page-size="searchWhere.limit"/>
      </div>
    </Card>

    <!-- 接入代码：按选中的应用生成，多应用互不串用 -->
    <Modal v-model="codeModal" :title="`接入代码 - ${current.name || ''}`" width="1040" footer-hide class="code-modal">
      <div class="getCode_container">
        <Tabs value="name1" v-if="current.token">
          <TabPane label="网页内嵌" name="name1">
            <wangye :tokeninfo="current" :siteUrl="siteUrl" @cgetCopy="getCopy"></wangye>
          </TabPane>
          <TabPane label="超链接" name="name2">
            <alink :tokeninfo="current" :siteUrl="siteUrl" @cgetCopy="getCopy"></alink>
          </TabPane>
          <TabPane label="定制开发" name="name3">
            <kaifa :tokeninfo="current" :siteUrl="siteUrl" @cgetCopy="getCopy"></kaifa>
          </TabPane>
        </Tabs>
      </div>
    </Modal>

    <!-- 新增/编辑应用（后端FormBuilder表单） -->
    <app-from :FromData="FromData" ref="appfrom" @submitFail="getList"></app-from>
  </div>
</template>

<script>
import { mapState } from 'vuex'
import linkWithQr from '@/components/linkWithQr'
import { appListApi, appCreateFormApi, appEditFormApi, appResetTokenApi } from '@/api/application'
import appFrom from '@/components/from/from'
import alink from './components/alink'
import wangye from './components/wangye'
import kaifa from './components/kaifa'

export default {
  name: 'system_code',
  components: { appFrom, alink, wangye, kaifa, linkWithQr },
  data () {
    return {
      grid: { xl: 7, lg: 7, md: 12, sm: 24, xs: 24 },
      loading: false,
      total: 0,
      searchWhere: { name: '', page: 1, limit: 15 },
      list: [],
      current: {},
      codeModal: false,
      FromData: null,
      siteUrl: `${location.origin}`,
      columns: [
        { title: '图标', slot: 'icon', width: 90 },
        { title: '应用名称', key: 'name', minWidth: 140 },
        { title: '应用ID', key: 'appid', minWidth: 180 },
        { title: '接入模式', slot: 'auth_mode', minWidth: 110 },
        { title: '简介', key: 'introduce', minWidth: 160 },
        { title: '接入链接', slot: 'link', minWidth: 260 },
        { title: '操作', slot: 'action', fixed: 'right', minWidth: 260 }
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
    //与接入代码页给出的地址保持一致，改一处即可
    chatLink (row) {
      return `${location.origin}/chat?token=${row.token_md5}&noCanClose=1`
    },
    getList () {
      this.loading = true
      appListApi(this.searchWhere).then(res => {
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
    showCode (row) {
      this.current = row
      this.codeModal = true
    },
    add () {
      appCreateFormApi().then(res => {
        this.FromData = res.data
        this.$refs.appfrom.modals = true
      }).catch(res => {
        this.$Message.error(res.msg)
      })
    },
    edit (row) {
      appEditFormApi(row.id).then(res => {
        this.FromData = res.data
        this.$refs.appfrom.modals = true
      }).catch(res => {
        this.$Message.error(res.msg)
      })
    },
    resetToken (row) {
      this.$Modal.confirm({
        title: '重置token',
        content: 'token重置后，原有接入代码将全部失效，需要重新部署，是否继续？',
        onOk: () => {
          appResetTokenApi(row.id).then(res => {
            this.$Message.success('重置成功，请重新复制接入代码')
            this.getList()
          }).catch(res => {
            this.$Message.error(res.msg)
          })
        }
      })
    },
    del (row, index) {
      const delfromData = {
        title: '删除应用',
        num: index,
        url: `app/${row.id}`,
        method: 'DELETE',
        ids: ''
      }
      this.$modalSure(delfromData).then(res => {
        this.$Message.success(res.msg)
        this.getList()
      }).catch(res => {
        this.$Message.error(res.msg)
      })
    },
    getCopy (id) {
      const content = this.copyToClipboard(document.getElementById(id))
      if (content) this.$Message.success('复制成功')
    },
    copyToClipboard (elem) {
      if (!elem) return false
      const isInput = elem.tagName === 'INPUT' || elem.tagName === 'TEXTAREA'
      let target = elem
      if (!isInput) {
        target = document.createElement('textarea')
        target.style.position = 'absolute'
        target.style.left = '-9999px'
        target.textContent = elem.textContent
        document.body.appendChild(target)
      }
      target.select()
      let succeed = false
      try {
        succeed = document.execCommand('copy')
      } catch (e) {
        succeed = false
      }
      if (!isInput) {
        document.body.removeChild(target)
      }
      return succeed
    }
  }
}
</script>

<style scoped>
.tabBox_img {
  width: 36px;
  height: 36px;
  border-radius: 4px;
  cursor: pointer;
}
.tabBox_img img {
  width: 100%;
  height: 100%;
}
.danger-link {
  color: #ed4014;
}
</style>

<!-- 接入代码三个子组件的样式原本挂在页面根节点上，改为弹窗后需在此提供；不加scoped以便作用到子组件 -->
<style lang="less">
.code-modal {
  .ivu-modal {
    // 窄屏时不超出视口
    max-width: 94vw;
  }

  .ivu-modal-body {
    // 三个Tab内容高度差很大，固定高度避免切Tab时弹窗上下跳动
    height: 70vh;
    // 滚动条常驻，否则切到没有滚动条的Tab时内容宽度会突变
    overflow-y: scroll;
    overflow-x: hidden;
  }

  .getCode_container {
    color: #323437;
    font-size: 13px;

    .content {
      width: 100%;
      // 项目无全局border-box重置，width:100%叠加padding会横向溢出
      box-sizing: border-box;
      background: #ffffff;
      padding: 4px 2px;
    }

    .font-w {
      font-weight: 600;
      margin: 10px 0 6px;
    }

    .text-i {
      text-indent: 2em;
      color: #515a6e;
      line-height: 22px;
    }

    .content > p {
      margin-bottom: 6px;
    }

    .typetitle {
      font-size: 15px;
      font-weight: 600;
      margin: 14px 0 8px;
    }

    // 弹窗内空间有限，压缩原全屏布局的留白
    .ivu-divider-horizontal {
      margin: 14px 0;
    }

    // 定制开发页存在fenlei嵌套，内层再叠一层边框会明显变窄
    .fenlei .fenlei {
      margin: 8px 0;
      padding: 10px 12px;
    }

    .fenlei {
      margin: 10px 0;
      border: 1px solid #eee;
      padding: 14px;
      border-radius: 6px;
    }

    .code-content-wrap {
      clear: both;
      border: 1px solid #e4e4e4;
      border-radius: 3px;
      padding: 10px 12px;
      background-color: #f8f8f8;
    }

    .code,
    .textarea {
      display: block;
      border: none;
      width: 100%;
      // 含padding计算宽度，否则width:100%会撑出父容器
      box-sizing: border-box;
      outline: 0;
      resize: vertical;
      background-color: #f8f8f8;
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size: 12.5px;
      color: #323437;
      line-height: 20px;
      text-align: left;
      // textarea默认软换行，改成pre会把长行横向截断
      white-space: pre-wrap;
      word-break: break-all;
      overflow-x: hidden;
      // 高度交给rows撑开且不限高，避免与弹窗body形成双滚动条；auto仅作兜底
      overflow-y: auto;
      min-height: 34px;
    }

    .other-wrap {
      margin: 10px 0 0;
      text-align: right;
    }

    .btn {
      display: inline-block;
      zoom: 1;
      padding: 6px 16px;
      border: 1px solid #d9dbdc;
      border-radius: 2px;
      line-height: 1;
      color: #323437;
      cursor: pointer;
      outline: 0;
      text-decoration: none;
      // 窄屏下按钮组会折行，禁止按钮内文字被拆开并补足行间距
      white-space: nowrap;
      margin-bottom: 4px;
    }

    .btn.btn-blue {
      color: #fff;
      background-color: #2d8cf0;
      border-color: #2d8cf0;

      &:hover {
        background-color: #57a3f3;
        border-color: #57a3f3;
      }
    }

    .mr10 {
      margin-right: 10px;
    }

    .setting-highlight {
      color: #f15755;
      margin-left: 5px;
      line-height: 30px;
    }
  }
}
</style>
