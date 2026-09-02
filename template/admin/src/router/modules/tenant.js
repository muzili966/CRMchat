// +---------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +---------------------------------------------------------------------
// | Copyright (c) 2016~2021 https://www.crmeb.com All rights reserved.
// +---------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +---------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +---------------------------------------------------------------------

import BasicLayout from '@/components/main'

const pre = 'tenant_'

export default {
  path: '/admin/tenant',
  name: 'tenant',
  header: 'tenant',
  redirect: {
    name: `${pre}list`
  },
  meta: {
    auth: ['admin-tenant']
  },
  component: BasicLayout,
  children: [
    {
      path: 'list',
      name: `${pre}list`,
      meta: {
        auth: ['tenant-list'],
        title: '租户列表'
      },
      component: () => import('@/pages/tenant/list/index')
    },
    {
      path: 'plan',
      name: `${pre}plan`,
      meta: {
        auth: ['tenant-plan'],
        title: '套餐管理'
      },
      component: () => import('@/pages/tenant/plan/index')
    },
    {
      path: 'orders',
      name: `${pre}orders`,
      meta: {
        auth: ['tenant-orders'],
        title: '订购对账'
      },
      component: () => import('@/pages/tenant/orders/index')
    },
    {
      path: 'invoice',
      name: `${pre}invoice`,
      meta: {
        auth: ['tenant-invoice'],
        title: '发票管理'
      },
      component: () => import('@/pages/tenant/invoice/index')
    },
    {
      path: 'notice',
      name: `${pre}notice`,
      meta: {
        auth: ['tenant-notice'],
        title: '通知管理'
      },
      component: () => import('@/pages/tenant/notice/index')
    },
    {
      //销售线索：平台自己的潜在客户，仅平台端可见
      path: 'lead',
      name: `${pre}lead`,
      meta: {
        auth: ['platform-lead'],
        title: '销售线索'
      },
      component: () => import('@/pages/platform/lead/index')
    },
    {
      path: 'subscription',
      name: `${pre}subscription`,
      meta: {
        auth: ['tenant-subscription'],
        title: '我的订阅'
      },
      component: () => import('@/pages/tenant/subscription/index')
    }
  ]
}
