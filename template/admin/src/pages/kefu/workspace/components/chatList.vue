<template>
  <div class="chatList">
    <div class="search_box">
      <Input prefix="ios-search" placeholder="搜索用户名称" @on-enter="bindSearch" @on-change="inputChange" />

    </div>
    <div class="tab-head">
      <div class="item" :class="{active:item.key == hdTabCur}" v-for="(item, index) in hdTab" :key="index" @click="changeTab(item)">{{item.title}}</div>
    </div>
    <div class="scroll-box">

      <vue-scroll :ops="ops" @handle-scroll="handleScroll" v-if="userList.length>0">
        <div class="chat-item" v-for="(item,index) in userList" :key="index"
             :class="{ active: curId == item.id, waiting: item.mssage_num > 0 }" @click="selectUser(item,index)">
          <div class="avatar">
            <img :src="item.avatar" alt="" @error="handleAvatarError">
            <div class="status" :class="{off:item.online == 0}"></div>
          </div>
          <div class="user-info">
            <div class="hd">
              <span class="name line1">{{item.nickname}}</span>
              <template v-if="item.type == 2">
                <span class="label">小程序</span>
              </template>
              <template v-if="item.type == 3">
                <span class="label H5">H5</span>
              </template>
              <template v-if="item.type == 1">
                <span class="label wechat">公众号</span>
              </template>
              <template v-if="item.type == 0">
                <span class="label pc">PC端</span>
              </template>
            </div>
            <!-- 接待归属单独一行：与终端标签挤在名称行会换行且看不清 -->
            <div class="handle-row" v-if="showHandler">
              <span class="chip ai" v-if="item.is_ai">AI接待</span>
              <span class="chip mine" v-else-if="item.is_mine">我接待</span>
              <span class="chip other" v-else-if="item.handler_name"
                    :class="{ off: !item.handler_online }">
                {{item.handler_name}}<i v-if="!item.handler_online">·离线</i>
              </span>
              <span class="chip none" v-else>未分配</span>
              <span class="chip wait" v-if="item.mssage_num > 0">待回复</span>
            </div>
            <div class="bd line1">
              <template v-if="item.message_type <=2">{{item.message}}</template>
              <template v-if="item.message_type ==3">[图片]</template>
              <template v-if="item.message_type ==5">[商品]</template>
              <template v-if="item.message_type ==6">[订单]</template>
            </div>
          </div>
          <div class="right-box">
            <div class="time">{{item.update_time | toDay}}</div>
            <div class="num">
              <Badge :count="item.mssage_num">
                <a href="#" class="demo-badge"></a>
              </Badge>
            </div>
          </div>
        </div>
      </vue-scroll>
      <empty v-else msg="暂无用户列表" status="1"></empty>
    </div>

  </div>
</template>

<script>
import { Socket } from '@/libs/socket';
import dayjs from 'dayjs'
import { record } from '@/api/kefu'
import { HappyScroll } from 'vue-happy-scroll'
import empty from "../../components/empty";
import { onAvatarError } from '@/libs/avatar';
import { forEach } from "../../../../libs/tools";
export default {
  name: "chatList",
  props: {
    userOnline: {
      type: Object,
      default: function() {
        return {}
      }
    },
    newRecored: {
      type: Object,
      default: function() {
        return {}
      }
    },
    searchData: {
      type: String,
      default: ''
    },
    isShow:{
      type: Boolean,
      default: false
    }
  },
  components: {
    HappyScroll,
    empty
  },
  watch: {
    userOnline: {
      handler(nVal, oVal) {
        if(nVal.hasOwnProperty('user_id')) {
          this.userList.forEach((el, index) => {
            if(el.to_user_id == nVal.user_id) {
              el.online = nVal.online
              if(nVal.online == 1) {
                this.$Notice.info({
                  title: '上线通知',
                  desc: `${el.nickname}上线`
                });
              }

            }
          })
        }
      },
      deep: true
    },
    searchData: {
      handler(nVal, oVal) {
        if(nVal != oVal) {
          this.nickname = nVal
          this.page = 1
          this.isScroll = true
          this.userList = []
          this.isSearch = true
          this.getList()
        }
      },
      deep: true
    },
    isShow: {
      handler(nVal, oVal) {
        console.log('isShow',nVal)
        if(nVal) {
          this.wsStart()
        }
      },
      deep: true
    }
  },
  data() {
    return {
      hdTabCur: 1,
      hdTab: [
        //scope=all 会带出其他客服与AI坐席接待的会话，行内标记接待人
        {
          key: 1,
          title: '我的接待',
          scope: 'mine',
          isTourist: ''
        },
        {
          key: 2,
          title: '全部会话',
          scope: 'all',
          isTourist: ''
        },
        {
          key: 0,
          title: '用户列表',
          scope: 'mine',
          isTourist: 0
        }
      ],
      userList: [],
      curId: '',
      page: 1,
      limit: 15,
      isScroll: true,
      nickname: '',
      isSearch: false,
      ops: {
        vuescroll: {
          mode: 'native',
          enable: false,
          tips: {
            deactive: 'Push to Load',
            active: 'Release to Load',
            start: 'Loading...',
            beforeDeactive: 'Load Successfully!'
          },
          auto: false,
          autoLoadDistance: 0,
          pullRefresh: {
            enable: false
          },
          pushLoad: {
            enable: true,
            auto: true,
            autoLoadDistance: 10
          }
        },
        bar: {
          background: '#393232',
          opacity: '.5',
          size: '5px'
        }
      },
    }
  },
  filters: {
    toDay: function(value) {
      if(!value) return ''
      return dayjs.unix(value).format('M月D日 HH:mm')

    }
  },
  mounted() {

    this.bus.$on('change', data => {
      this.nickname = data
    })
    this.getList();
    // this.wsStart();
  },
  computed: {
    currentTab() {
      return this.hdTab.find(item => item.key === this.hdTabCur) || this.hdTab[0]
    },
    //只有"全部会话"才需要区分接待人，自己的列表里全是自己没必要标
    showHandler() {
      return this.currentTab.scope === 'all'
    }
  },
  methods: {
    // 头像加载失败兜底为默认头像
    handleAvatarError(event) {
      onAvatarError(event);
    },
    // 搜索
    bindSearch(e) {
      this.$emit('search', e.target.value);
    },
    // inputChange
    inputChange(e) {
      console.log(e.target.value)
      this.bus.$emit('change', e.target.value)
    },
    deleteUserList(item){
      this.userList.forEach((el, index, arr) => {
        if(el.id == item.id){
          this.userList.splice(index,1)
        }
      })
      if(this.userList.length){
        this.selectUser(this.userList[0],0)
      }
    },
    updateUserList(data,op){
      let ids = [];
      this.userList.map(item=>{
        ids.push(item.id)
        if (item.id === data.id) {
          item.message = data.message
          item._update_time = data._update_time
        }
      })
      if(ids.indexOf(data.id) === -1 && op) {
        this.userList.unshift(data);
      }
    },
    wsStart() {
      let that = this
      this.bus.pageWs.then(ws => {
        // 用户转接
        ws.$on('transfer', data => {
          let status = false
          that.userList.forEach((el, index, arr) => {
            if(data.recored.id == el.id) {
              status = true
              let oldVal = data.recored
              arr.splice(index, 1)
              if(index == 0) {
                oldVal.index = index
                this.$emit('setDataId', oldVal)
                oldVal.mssage_num = 0
              }
              arr.unshift(oldVal)

              this.$Notice.info({
                title: '您有一条转接消息！'
              });
            }
          })
          if(!status) {
            if(data.recored.is_tourist == this.hdTabCur) { this.userList.unshift(data.recored) }
          }
        })
        //已被转接走
        ws.$on('rm_transfer',data=>{
          let rmIndex = -1;
          that.userList.forEach((value, index) => {
            if(value.id == data.recored.id){
              rmIndex = index
            }
          })
          if(rmIndex !== -1){
            this.userList.splice(rmIndex,1)
            if(this.userList.length){
              this.$emit('setDataId', this.userList[0])
            }
          }
        })
        ws.$on('mssage_num', data => {
          // console.log('mssage_num',data)
          if(data.recored.id) {
            let status = false
            that.userList.forEach((el, index, arr) => {
              if(data.recored.id == el.id) {
                status = true
                let oldVal = data.recored
                arr.splice(index, 1)
                arr.unshift(oldVal)
              }
            })
            if(!status) {
              if(data.recored.is_tourist == this.hdTabCur) { this.userList.unshift(data.recored) }
            }
          }


          if(data.recored.is_tourist != this.hdTabCur && data.recored.id) {
            this.$Notice.info({
              title: this.hdTabCur ? '用户发来消息啦！' : '游客发来消息啦！'
            });
          }

        })
      });
    },
    //切换
    changeTab(item) {
      if(this.hdTabCur == item.key) return
      this.hdTabCur = item.key
      this.isScroll = true
      this.page = 1
      this.userList = []
      this.$emit('changeType', item.key)
      this.getList()
    },
    // 接管 AI 会话后需要重新拉取列表，keepActive 保证不打断客服当前正在进行的对话
    reloadList() {
      this.page = 1
      this.isScroll = true
      this.userList = []
      this.getList(true)
    },
    getList(keepActive) {
      if(!this.isScroll) return
      record({
        nickname: this.nickname,
        page: this.page,
        limit: this.limit,
        is_tourist: this.currentTab.isTourist,
        scope: this.currentTab.scope
      }).then(res => {
        if(res.data.length > 0) {
          // 清零仅服务于随后自动选中的首条会话，刷新时保留未读数
          if(!keepActive) res.data[0].mssage_num = 0
          this.isScroll = res.data.length >= this.limit

          this.userList = this.userList.concat(res.data)

          if(this.page == 1 && res.data.length > 0 && !this.isSearch && !keepActive) {
            this.curId = res.data[0].id
            res.data[0].index = 0
            this.$emit('setDataId', res.data[0])
          }
          this.page++
        } else if(!keepActive) {
          this.$emit('setDataId', 0)
        }

      })
    },
    chartReachBottom() {
      this.getList()
    },
    // 选择用户
    selectUser(item,index) {
      if(this.curId == item.id) return
      item.mssage_num = 0
      this.curId = item.id
      item.index = index;
      //第二个参数标识是用户主动点击：自动选中不应在窄屏下跳到对话视图
      this.$emit('setDataId', item, true)
    },
    handleScroll(vertical, horizontal, nativeEvent) {
      if(vertical.process == 1) {
        this.getList()
      }
    }
  }
}
</script>

<style lang="stylus" scoped>
.chatList {
  display: flex;
  flex-direction: column;
  width: 320px;
  height: 742px;
  border-right: 1px solid #ECECEC;

  .tab-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 50px;
    flex-shrink: 0;
    padding: 0 52px;
    font-size: 14px;
    color: #000000;

    .item {
      position: relative;
      cursor: pointer;

      &:after {
        display: none;
        content: ' ';
        position: absolute;
        left: 50%;
        bottom: -15px;
        transform: translateX(-50%);
        height: 2px;
        width: 100%;
        background: #1890FF;
      }

      &.active {
        color: #1890FF;

        &:after {
          display: block;
        }
      }
    }
  }

  .scroll-box {
    flex: 1;
    height: 500px;
    overflow: hidden;
  }

  .chat-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px;
    /* 多一行接待状态，固定高度会把消息挤没 */
    min-height: 74px;
    height: auto;
    box-sizing: border-box;
    border-left: 3px solid transparent;
    cursor: pointer;

    /* 有未读即等待处理，左侧强调条让人一眼扫到 */
    &.waiting {
      border-left-color: #ff9900;
    }

    .handle-row {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 4px;
      margin: 2px 0 3px;
    }

    .chip {
      display: inline-flex;
      align-items: center;
      padding: 0 6px;
      height: 18px;
      border-radius: 9px;
      font-size: 11px;
      line-height: 18px;
      white-space: nowrap;

      i {
        font-style: normal;
        opacity: .8;
      }

      &.ai {
        background: #FFF1DB;
        color: #E38B00;
      }

      &.mine {
        background: #DCF5E7;
        color: #10893E;
      }

      &.other {
        background: #EAF1FF;
        color: #3875EA;
      }

      /* 接待人已离线：这条会话实际无人处理 */
      &.other.off, &.none {
        background: #F2F3F5;
        color: #8A94A6;
      }

      &.wait {
        background: #FFEDE9;
        color: #E8380D;
      }
    }

    &.active {
      background: #EFF0F1;
      border-left: 3px solid #1890FF;
    }

    .avatar {
      position: relative;
      width: 40px;
      height: 40px;

      img {
        display: block;
        width: 100%;
        height: 100%;
        border-radius: 50%;
      }

      .status {
        position: absolute;
        right: 3px;
        bottom: 0;
        width: 8px;
        height: 8px;
        background: #48D452;
        border: 1px solid #fff;
        border-radius: 50%;

        &.off {
          background: #999999;
        }
      }
    }

    .user-info {
      width: 155px;
      margin-left: 12px;
      margin-top: 5px;
      font-size: 16px;

      .hd {
        display: flex;
        align-items: center;
        color: rgba(0, 0, 0, 0.65);

        .name {
          max-width: 67%;
        }

        .label {
          margin-left: 5px;
          color: #3875EA;
          font-size: 12px;
          background: #D8E5FF;
          border-radius: 2px;
          padding: 1px 5px;

          /* 接待人标记：与终端类型标签同处，避免层级不足被覆盖 */
          &.ai {
            background: #FFF1DB;
            color: #E38B00;
          }

          &.mine {
            background: #DCF5E7;
            color: #10893E;
          }

          &.handler {
            background: #EEF0F5;
            color: #5A6478;
          }

          &.H5 {
            background: #FAF1D0;
            color: #DC9A04;
          }

          &.wechat {
            background: rgba(64, 194, 73, 0.16);
            color: #40C249;
          }

          &.pc {
            background: rgba(100, 64, 194, 0.16);
            color: #6440C2;
          }
        }
      }

      .bd {
        margin-top: 3px;
        font-size: 12px;
        color: #8E959E;
      }
    }

    .right-box {
      position: relative;
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      color: #8E959E;

      .num {
        margin-right: 12px;
      }
    }
  }
}

.chart-scroll {
  margin-top: -10px;
}

.search_box {
  margin: 10px 5px 0 5px;
}
</style>

