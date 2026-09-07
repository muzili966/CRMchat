// +---------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +---------------------------------------------------------------------

import BasicLayout from '@/components/main'

const pre = 'export_'

// 归在「设置管理」下，故 header 用 setting，与菜单表 header 字段一致
export default {
  path: '/admin/export',
  name: 'export',
  header: 'setting',
  redirect: {
    name: `${pre}list`
  },
  component: BasicLayout,
  children: [
    {
      path: 'list',
      name: `${pre}list`,
      meta: {
        auth: ['export-center'],
        title: '下载中心'
      },
      component: () => import('@/pages/export/list/index')
    }
  ]
}
