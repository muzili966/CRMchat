// +---------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +---------------------------------------------------------------------

import BasicLayout from '@/components/main'

const pre = 'sensitive_'

// 归在「设置管理」下，故 header 用 setting，与菜单表 header 字段一致
export default {
  path: '/admin/sensitive',
  name: 'sensitive',
  header: 'setting',
  redirect: {
    name: `${pre}word`
  },
  component: BasicLayout,
  children: [
    {
      path: 'word',
      name: `${pre}word`,
      meta: {
        auth: ['sensitive-word'],
        title: '敏感词管理'
      },
      component: () => import('@/pages/sensitive/word/index')
    },
    {
      path: 'hit',
      name: `${pre}hit`,
      meta: {
        auth: ['sensitive-hit'],
        title: '敏感词命中'
      },
      component: () => import('@/pages/sensitive/hit/index')
    }
  ]
}
