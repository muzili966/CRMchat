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
    <Modal v-model="codeModal" :title="`接入代码 - ${current.name || ''}`" width="900" footer-hide>
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
    </Modal>

    <!-- 新增/编辑应用（后端FormBuilder表单） -->
    <app-from :FromData="FromData" ref="appfrom" @submitFail="getList"></app-from>
  </div>
</template>

<script>
import { mapState } from 'vuex'
import { appListApi, appCreateFormApi, appEditFormApi, appResetTokenApi } from '@/api/application'
import appFrom from '@/components/from/from'
import alink from './components/alink'
import wangye from './components/wangye'
import kaifa from './components/kaifa'

export default {
  name: 'system_code',
  components: { appFrom, alink, wangye, kaifa },
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
