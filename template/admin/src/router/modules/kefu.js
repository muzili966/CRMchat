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

const pre = 'chat_'

export default {
  path: '/admin/chat',
  name: 'chat',
  header: 'kefu',
  redirect: {
    name: `${pre}ai`
  },
  component: BasicLayout,
  children: [
    {
      path: 'ai',
      name: `${pre}ai`,
      meta: {
        auth: ['chat-ai-config'],
        title: 'AI客服设置'
      },
      component: () => import('@/pages/chat/ai/index')
    },
    {
      path: 'theme',
      name: `${pre}theme`,
      meta: {
        auth: ['chat-theme'],
        title: '客户端装修'
      },
      component: () => import('@/pages/chat/theme/index')
    }
  ]
}
