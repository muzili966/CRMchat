<template>
  <div class="kefu-layouts" :class="{ 'is-narrow': isNarrow, 'show-chat': mobileShowChat }">
    <div class="content-wrapper">
      <baseHeader :kefuInfo="kefuInfo" :online="online" @setOnline="setOnline"></baseHeader>
      <div class="container">
        <chatList ref="chatList" @setDataId="setDataId" @search="bindSearch" @changeType="changeType" :isShow="isShow" :userOnline="userOnline" :newRecored="newRecored" :searchData="searchData"></chatList>
        <div class="chat-content">
          <!-- 窄屏为单栏，需要一个入口退回会话列表 -->
          <div class="narrow-back" v-if="isNarrow" @click="mobileShowChat = false">
            <Icon type="ios-arrow-back" size="20"/>
            <span>{{ userActive && userActive.nickname ? userActive.nickname : '返回会话列表' }}</span>
          </div>
          <div class="conversation-header" v-if="!isNarrow">
            <div class="conversation-user">
              <img v-if="userActive && userActive.avatar" :src="userActive.avatar" alt="">
              <span v-else class="conversation-user__placeholder"><Icon type="ios-person-outline" /></span>
              <div>
                <strong>{{ userActive && userActive.nickname ? userActive.nickname : '请选择会话' }}</strong>
                <small>{{ userActive ? '正在为客户提供服务' : '从左侧会话列表选择一位客户' }}</small>
              </div>
            </div>
            <span v-if="userActive" class="conversation-badge"><i></i> 当前会话</span>
          </div>
          <div class="chat-body">

            <happy-scroll size="5" resize hide-horizontal :scroll-top="scrollTop" @vertical-start="scrollHandler">
              <div class="chat-scroll-inner" id="chat_scroll" ref="scrollBox">
                <Spin v-show="isLoad">
                  <Icon type="ios-loading" size=18 class="demo-spin-icon-load"></Icon>
                  <div>Loading</div>
                </Spin>
                <div class="chat-item" v-for="(item,index) in records" :key="index" :class="[{'right-box':kefuInfo.user_ids.indexOf(item.user_id) !== -1},{'gary':item.msn_type==5}]" :id="`chat_${item.id}`">
                  <div class="time" v-show="item.show">{{item.time }}</div>
                  <div class="flex-box">
                    <div class="avatar">
                      <img v-lazy="item.avatar" alt="">
                    </div>
                    <div class="msg-wrapper">
                      <!-- 文档 -->
                      <template v-if="item.msn_type<=2">
                        <div class="txt-wrapper pad16" v-html="item.msn"></div>
                      </template>
                      <!-- 图片 -->
                      <template v-if="item.msn_type==3">
                        <div class="img-wraper" v-viewer>
                          <img v-lazy="item.msn" alt="">
                        </div>
                      </template>
                      <!-- 商品 -->

                      <template v-if="item.msn_type==5">
                        <div class="order-wrapper pad16">
                          <div class="img-box"><img :src="item.other.image" alt=""></div>
                          <div class="order-info">
                            <div class="name line1">{{item.other.store_name}}</div>
                            <div class="sku">库存：{{item.other.stock}} 销量：{{parseInt(item.other.sales) + parseInt(item.other.ficti?item.other.ficti:0)}}</div>
                            <div class="price-box">
                              <div class="num">¥ {{item.other.price}}</div>
                              <!-- <a herf="javascript:;" class="more" @click.stop="lookGoods(item)">查看商品 ></a> -->
                            </div>
                          </div>

                        </div>
                      </template>
                      <!-- 订单 -->
                      <template v-if="item.msn_type==6 && (item.orderInfo.length>0||item.orderInfo.id)">
                        <div class="order-wrapper pad16">
                          <div class="img-box"><img :src="item.orderInfo.cartInfo[0].productInfo.image" alt=""></div>
                          <div class="order-info">
                            <div class="name line1">{{item.orderInfo.order_id}}</div>
                            <div class="sku">商品数量：{{item.orderInfo.total_num}}</div>
                            <div class="price-box">
                              <div class="num">¥ {{item.orderInfo.pay_price}}</div>
                              <a href="javascript:;" class="more" @click.stop="lookOrder(item)">查看订单 ></a>
                            </div>
                          </div>

                        </div>
                      </template>

                    </div>

                  </div>
                </div>
              </div>
            </happy-scroll>
          </div>

          <div class="chat-textarea">
            <div class="chat-btn-wrapper">
              <div class="left-wrapper">
                <div class="icon-item" @click.stop="isEmoji = !isEmoji"><span class="iconfont iconbiaoqing1"></span></div>
                <div class="icon-item">
                  <Upload :show-upload-list="false" :headers="header" :data="uploadData" :on-success="handleSuccess" :format="['jpg','jpeg','png','gif']" :on-format-error="handleFormatError" :action="upload">
                    <span class="iconfont icontupian1"></span>
                  </Upload>
                </div>
                <div class="icon-item" @click.stop.stop="isMsg = true"><span class="iconfont iconliaotian"></span></div>
                <div class="icon-item" @click.stop.stop="authMsg = true"><Icon style="font-weight: bold" size="22" color="#515a6e" type="ios-chatboxes-outline" /></div>
                <div class="icon-item" @click.stop="openAiSession">
                  <Icon style="font-weight: bold" size="22" color="#515a6e" type="ios-people-outline" />
                  <span class="ai-label">AI会话</span>
                </div>
              </div>
              <div class="right-wrapper">
                <div class="icon-item" @click.stop="isTransfer = !isTransfer">
                  <span class="iconfont iconzhuanjie"></span>
                  <span>转接</span>
                </div>
                <div class="transfer-box" v-if="isTransfer">
                  <transfer ref="transfer" @transferSuccess="transferSuccess" @close="msgClose" @transferPeople="transferPeople" :userUid="userActive.to_user_id"></transfer>
                </div>
                <div class="transfer-bg" v-if="isTransfer" @click.stop="isTransfer = false"></div>
              </div>
              <!-- 表情 -->
              <div class="emoji-box" v-show="isEmoji">
                <div class="emoji-item" v-for="(emoji, index) in emojiList" :key="index">
                  <i class="em" :class="emoji" @click.stop="select(emoji)"></i>
                </div>
              </div>
            </div>
            <div class="textarea-box" style="position:relative;">
              <Input v-model="chatCon" type="textarea" :rows="4" @keydown.enter="sendText" placeholder="请输入文字内容" @on-enter="sendText" style="font-size:14px" />
              <div class="send-btn">
                <Button class="btns" type="primary" :disabled="disabled" @click.stop="sendText">发送</Button>
              </div>
            </div>
          </div>
        </div>
        <div class="right_menu">
          <rightMenu :isTourist="tourist" :uid="userActive.to_user_id" :webType="userActive.type" :canToLead="!!kefuInfo.can_to_lead" @bindPush="bindPush"></rightMenu>
          <div class="crmchat_link">
            <span>QiaLink 洽联智能客服</span>
          </div>
        </div>
      </div>
      <!-- 用户标签 -->
      <Modal v-model="isMsg" :mask="true" class="none-radius isMsgbox" width="600" :footer-hide="true">
        <msg-window v-if="isMsg" @close="msgWinClose" @activeTxt="activeTxt"></msg-window>
      </Modal>
      <!-- 自动回复 -->
      <Modal v-model="authMsg" :mask="true" class="none-radius isMsgbox" width="600" :footer-hide="true">
        <auth-reply v-if="authMsg" @close="msgAuthClose" @activeTxt="activeTxt"></auth-reply>
      </Modal>
      <!-- AI 坐席接待中的会话 -->
      <Modal v-model="aiModal" :mask="true" title="AI会话" width="620" :footer-hide="true">
        <div class="ai-session">
          <Input v-model="aiNickname" prefix="ios-search" placeholder="搜索用户名称" @on-enter="getAiSessionList" />
          <div class="ai-list" v-if="aiSessionList.length > 0">
            <div class="ai-item" v-for="(item,index) in aiSessionList" :key="index">
              <div class="avatar"><img :src="item.avatar" alt=""></div>
              <div class="info">
                <div class="name line1">{{item.nickname}}</div>
                <div class="msg line1">
                  <template v-if="item.message_type <= 2">{{item.message}}</template>
                  <template v-if="item.message_type == 3">[图片]</template>
                  <template v-if="item.message_type == 5">[商品]</template>
                  <template v-if="item.message_type == 6">[订单]</template>
                </div>
              </div>
              <div class="time">{{item.update_time ? $moment(item.update_time * 1000).format('MM-DD HH:mm') : ''}}</div>
              <Button type="primary" size="small" :loading="aiTakeId === item.to_user_id" @click="takeOverAi(item)">接管</Button>
            </div>
          </div>
          <div class="ai-empty" v-else-if="!aiLoading">暂无AI接待中的会话</div>
          <Spin fix v-if="aiLoading"></Spin>
        </div>
      </Modal>
      <!-- 商品弹窗 -->
      <!-- <div v-if="isProductBox">
        <div class="bg" @click.stop="isProductBox = false"></div>
        <goodsDetail :goodsId="goodsId"></goodsDetail>
      </div> -->
      <!-- 订单详情 -->
      <!-- <div v-if="isOrder">
        <Modal v-model="isOrder" title="订单信息" width="700" :footer-hide="true" :mask="true" class="none-radius">
          <orderDetail :orderId="orderId"></orderDetail>
        </Modal>
      </div> -->
    </div>
  </div>

</template>

<script>

//提示音统一走 notifySound：内部处理Chrome的自动播放限制
import Setting from '@/setting';
import { HappyScroll } from 'vue-happy-scroll'
import baseHeader from './components/baseHeader';
const NARROW_WIDTH = 1100

import chatList from './components/chatList'
import rightMenu from "./components/rightMenu";
import emojiList from "@/utils/emoji";
import { Socket } from '@/libs/socket';
import { initNotifySound, playNotifySound } from '@/libs/notifySound';
import msgWindow from "./components/msgWindow";
import authReply from "./components/authReply";
import transfer from './components/transfer'
import { serviceList, aiSessionListApi, aiTakeOverApi } from '@/api/kefu'
// import goodsDetail from "./components/goods_detail";
// import orderDetail from "./components/order_detail";
import { mapState } from 'vuex'
import { getCookies } from '@/libs/util'
import { serviceInfo } from '@/api/kefu_mobile'

// 将所得数组，按照 num 数量进行分组
const chunk = function(arr, num) {
  num = num * 1 || 1;
  var ret = [];
  arr.forEach(function(item, i) {
    if(i % num === 0) {
      ret.push([]);
    }
    ret[ret.length - 1].push(item);
  });

  return ret;
};


export default {
  name: 'index',
  components: {
    baseHeader,
    chatList,
    rightMenu,
    msgWindow,
    transfer,
    HappyScroll,
    authReply
    // goodsDetail,
    // orderDetail
  },
  data() {
    return {
      wsOpen:false,
      authMsg:false,
      isEmoji: false, // 是否显示表情弹框
      chatCon: '', // 输入框输入的聊天内容
      emojiGroup: chunk(emojiList, 20), // 表情列表 已20个一组进行分组
      emojiList: emojiList, // 表情总数据
      html: '',
      userActive: {}, //左侧用户列表选中信息
      kefuInfo: {}, //客服信息
      isMsg: false,
      isTransfer: false,
      activeMsg: '', // 选中的话术
      chatList: [],
      text: '',
      limit: 20,
      upperId: 0,
      online: true,//当前客服在线状态
      scrollTop: 0,
      isScroll: true,
      oldHeight: 0,
      isLoad: false,
      isProductBox: false,
      goodsId: "",
      isOrder: false,
      orderId: '',
      upload: '',
      header: {},
      uploadData: {
        filename: 'file'
      },
      userOnline: {},
      newRecored: {}, //新对话信息
      searchData: '', // 搜索文字
      scrollNum: 0, //滚动次数
      transferId: '', //转接id
      bodyClose: false,
      tourist: 0,
      isShow:false,
      toChat:false,
      aiModal: false, // AI会话弹窗
      aiLoading: false,
      aiNickname: '', // AI会话搜索名称
      aiSessionList: [],
      aiTakeId: '', // 正在接管的访客uid
      //窄屏下单栏切换：false=会话列表，true=对话
      mobileShowChat: false,
      viewportWidth: typeof window !== 'undefined' ? window.innerWidth : 1280,
    }
  },
  computed: {
    ...mapState({
      socketStatus: state => state.admin.kefu.socketStatus
    }),
    //三栏布局需要约1200px；不足则退为单栏，平板竖屏与手机都走这一路
    isNarrow() {
      return this.viewportWidth < NARROW_WIDTH
    },
    disabled() {
      if(this.chatCon.length == 0) {
        return true
      } else {
        return false
      }
    },
    records() {
      return this.chatList.map((item, index) => {
        item.time = this.$moment(item.add_time * 1000).format('MMMDo H:mm')
        if(index) {
          if(
            item.add_time -
            this.chatList[index - 1].add_time >=
            300
          ) {
            item.show = true;
          } else {
            item.show = false;
          }
        } else {
          item.show = true;
        }
        return item;
      });
    },
  },
  watch: {
    // socketStatus:{
    //     handler(nVal,Val){
    //         if(nVal){
    //             Socket.send({
    //                 data: util.cookies.kefuGet('token'),
    //                 type: "kefu_login"
    //             });
    //         }
    //     },
    //     deep:true
    // }
  },
   created() {
    this.upload = Setting.apiBaseURL.replace('admin', 'kefu') + '/upload'
    console.log(Setting.apiBaseURL, this.upload);
    serviceInfo().then(res => {
      this.kefuInfo = res.data;
      // this.online = !!this.kefuInfo.online
      if(this.kefuInfo.site_name) {
        document.title = this.kefuInfo.site_name;
      } else {
        this.kefuInfo.site_name = '';
      }
    })
  },
  mounted() {
    //Chrome需借一次用户交互解锁播放权限，否则收到消息时没有提示音
    initNotifySound()
    this.onResize = () => { this.viewportWidth = window.innerWidth }
    window.addEventListener('resize', this.onResize)
    let self = this
    window.addEventListener('click', function() {
      self.isEmoji = false
    });
    this.bus.pageWs = Socket(true, getCookies('kefu_token'));
    this.wsAgain();
    this.header['Authori-zation'] = 'Bearer ' + getCookies('kefu_token');
    this.text = this.replace_em('[em-smiling_imp]');

    console.log(this.$route);

    window.onbeforeunload = (e) => {
      if(this.$route.name == "kefu_workspace") {
        e = e || window.event;
        // 兼容IE8和Firefox 4之前的版本
        if(e) {
          e.returnValue = '您确定要离开吗？';
        }
        // Chrome, Safari, Firefox 4+, Opera 12+ , IE 9+
        return '您确定要离开吗?';
      } else {
        window.onbeforeunload = null
      }
    };


  },
  beforeDestroy() {
    //不解绑会随页面反复进出而不断累积
    this.onResize && window.removeEventListener('resize', this.onResize)
  },
  methods: {
    // 建立scoket 连接
    wsAgain() {
      this.bus.pageWs.then((ws) => {
        ws.$on('close',()=>{
          this.toChat = false;
        })
        ws.$on('success',(data)=>{

          this.isShow = true;
          let toChat = this.userActive ? this.userActive.to_user_id : this.userActive;
          if(!this.toChat && toChat){
            ws.send({
              data: {
                id: toChat,
                test:1
              },
              type: "to_chat",
            });
            this.toChat = true;
            this.online = !!data.online
          }
        });
        ws.$on(["reply", "chat"], (data) => {
          if(data.msn_type == 1) {
            data.msn = this.replace_em(data.msn);
          }
          if(data.msn_type == 2) {
            if(data.msn.indexOf("[") == -1) {
              data.msn = this.replace_em(`[${data.msn}]`);
            }
          }
          this.chatList.push(data);
          this.$refs.chatList.updateUserList(data.recored,false);
          this.$nextTick(()=>{
            this.scrollTop = document.querySelector(
              "#chat_scroll"
            ).offsetHeight;
          });
        });

        ws.$on('recored',(data)=>{
          console.log(data)
          this.$refs.chatList.updateUserList(data,true);
        });
        ws.$on("reply", (data) => {
          playNotifySound();
        });
        ws.$on("socket_error", () => {
          this.$Message.error("连接失败");
        });
        ws.$on("err_tip", (data) => {
          this.$Message.error(data.msg);
        });
        // 用户上线提醒广播
        ws.$on("user_online", (data) => {
          console.log(data);
          this.userOnline = data;
        });
        // 用户未读消息条数更改
        ws.$on("mssage_num", (data) => {
          if(data.num > 0) {
            playNotifySound();
          }
          this.chatList.forEach((item) => {
            if(item.to_uid == data.user_id) {
              item.mssage_num = data.num;
            }
          });
          if(data.recored.id) {
            playNotifySound();
            this.newRecored = data.recored;
          }

        });

      })
    },
    wsRestart() {
      this.bus.pageWs = Socket(true);
      this.wsOpen = true
      this.wsAgain();
    },

    handleFormatError(file) {
      this.$Message.error("上传图片只能是 jpg、jpg、jpeg、gif 格式!");
    },

    // 上传成功
    handleSuccess(res, file, fileList) {
      if(res.status === 200) {
        this.$Message.success(res.msg);
        this.sendMsg(res.data.url, 3)
      } else {
        this.$Message.error(res.msg);
      }
    },
    setOnline(data) {

      this.bus.pageWs.then(ws => {
        ws.send({
          data: {
            online: data
          },
          type: "online"
        })
      })
      this.online = data;
    },
    // 输入框选择表情
    select(data) {
      let val = `[${data}]`
      this.chatCon += val
      this.isEmoji = false
    },
    // 聊天表情转换
    replace_em(str) {
      str = str.replace(/\[em-([a-z_]*)\]/g, "<span class='em em-$1'/></span>");
      return str;
    },
    // 获取是否游客 获取会话列表
    changeType(data) {
      this.tourist = data;
      // console.log(this.tourist);
    },
    // 获取列表用户信息
    setDataId(data, fromClick = false) {
      this.userActive = data
      //窄屏单栏：仅用户主动点击会话才切到对话视图；
      //切换tab、加载列表都会自动选中首条，那时不应跳走
      if (fromClick) this.mobileShowChat = true
      this.chatList = []
      this.upperId = 0
      this.oldHeight = 0
      this.isScroll = true
      if(data) {
        window.document.title = data.nickname ? `正在和${data.nickname}对话中 - ${this.kefuInfo.site_name}` : '正在和游客对话中 - ' + this.kefuInfo.site_name

        this.bus.pageWs.then((ws) => {
          ws.send({
            data: {
              id: this.userActive ? this.userActive.to_user_id : this.userActive,
              test:2
            },
            type: "to_chat",
          });
          this.toChat = true
        });
        this.getChatList()
      } else {
        window.document.title = this.kefuInfo.site_name
        this.bus.pageWs.then((ws) => {
          ws.send({
            data: {
              id: this.userActive ? this.userActive.to_user_id : this.userActive,
            },
            type: "to_chat",
          });
        });
      }


    },
    // 打开AI会话弹窗
    openAiSession() {
      this.aiModal = true
      this.getAiSessionList()
    },
    // AI坐席接待中的会话
    getAiSessionList() {
      this.aiLoading = true
      aiSessionListApi({ nickname: this.aiNickname }).then(res => {
        this.aiLoading = false
        // 兼容列表直出与分页包装两种返回
        this.aiSessionList = Array.isArray(res.data) ? res.data : (res.data && res.data.list) || []
      }).catch(error => {
        this.aiLoading = false
        this.$Message.error(error.msg)
      })
    },
    // 接管AI会话，会话行的 to_user_id 才是访客uid，user_id 为虚拟AI坐席
    takeOverAi(item) {
      this.aiTakeId = item.to_user_id
      aiTakeOverApi({ user_id: item.to_user_id }).then(res => {
        this.aiTakeId = ''
        this.$Message.success(res.msg || '接管成功')
        this.getAiSessionList()
        this.$refs.chatList.reloadList()
      }).catch(error => {
        this.aiTakeId = ''
        this.$Message.error(error.msg)
      })
    },
    msgClose(e) {
      this.isTransfer = false
    },
    transferSuccess(e){
      this.$refs.chatList.deleteUserList(this.userActive)
    },
    msgWinClose() {
      this.isMsg = false
    },
    msgAuthClose() {
      this.authMsg = false
    },
    // 话术选中
    activeTxt(data) {
      this.chatCon = data
      this.isMsg = false
    },
    // 文本发送
    sendText() {
      let chatCon = this.chatCon.replace(/[\r\n]/g, '');
      if(!chatCon){
        this.chatCon = '';
        return this.$Message.error('请输入内容');
      }
      this.sendMsg(chatCon, 1)
      this.chatCon = '';
    },

    // 统一发送处理
    sendMsg(msn, type) {
      let obj = {
        type: 'chat',
        data: {
          msn,
          type,
          to_user_id: this.userActive.to_user_id,
          is_tourist: this.tourist
        }
      }
      this.bus.pageWs.then((ws) => {
        ws.send(obj);
      });
    },
    send(type, data) {
      Socket.send({
        data,
        type
      });
    },
    // 获取聊天列表
    getChatList() {

      serviceList({
        limit: this.limit,
        user_id: this.userActive.to_user_id,
        upperId: this.upperId,
        is_tourist: this.tourist
      }).then(res => {
        res.data.forEach(el => {
          if(el.msn_type == 1) {
            el.msn = this.replace_em(el.msn)
          } else if(el.msn_type == 2) {
            el.msn = this.replace_em(`[${el.msn}]`)
          }
        })
        let selector = ''
        if(this.upperId == 0) {
          selector = '';

        } else {
          selector = `chat_${this.chatList[0].id}`;
        }

        // this.chatList = res.data.concat(this.chatList)
        this.chatList = [...res.data, ...this.chatList];
        this.upperId = res.data.length > 0 ? res.data[0].id : 0
        this.isLoad = false
        this.$nextTick(() => {
          // this.scrollToTop()
          this.isScroll = res.data.length >= this.limit
          this.setPageScrollTo(selector)
        })
      })
    },
    // 设置页面滚动位置
    setPageScrollTo(selector) {
      this.$nextTick(() => {
        if(selector) {
          setTimeout(() => {
            let num = parseFloat(document.getElementById(selector).offsetTop) - 60
            this.scrollTop = num
          }, 0)
        } else {
          var container = document.querySelector("#chat_scroll");
          this.scrollTop = container.offsetHeight + 0.01
          setTimeout(res => {
            if(this.scrollTop != this.$refs.scrollBox.offsetHeight) {
              this.scrollTop = document.querySelector("#chat_scroll").offsetHeight
            }
          }, 300)
        }
      })

    },
    //滚动到顶部
    scrollHandler() {
      let self = this
      if(this.isScroll && this.upperId) {
        this.isLoad = true
        this.getChatList()
      }
    },
    // 滚动条动画
    scrollToTop(duration) {
      var container = document.querySelector("#chat_scroll");
      this.scrollTop = container.offsetHeight - this.oldHeight
      setTimeout(res => {
        console.log(this.$refs.scrollBox.offsetHeight)
        this.scrollTop = this.$refs.scrollBox.offsetHeight - this.oldHeight
      }, 300)

    },
    // 商品推送
    bindPush(data) {
      this.sendMsg(data, 5)
    },
    // 商品详情
    lookGoods(item) {
      this.goodsId = item.msn
      this.isProductBox = true
    },
    // 搜索用户
    bindSearch(data) {
      this.searchData = data
      this.oldHeight = 0
      this.upperId = 0
      this.isScroll = false

    },
    // 客服转接
    transferPeople(data) {
      this.transferId = data.id
      this.isTransfer = false
      this.$Message.success('转接成功')
      Socket.then(ws => {
        ws.send({
          type: 'to_chat',
          data: { id: data.uid }
        })
      })
    },
    // 客服转接确定
    transferOk() {

    }


  }
}
</script>

<style lang="stylus" scoped>
@import '../../../styles/emoji-awesome/css/google.min.css';

textarea.ivu-input {
  border: none;
  resize: none;
}

.kefu-layouts {
  padding: 18px;
  height: 100%;
  display: flex;
  background: #f2f6fc;
  overflow: hidden;
}

.content-wrapper {
  display: flex;
  flex-direction: column;
  width: 100%;
  max-width: 1480px;
  height: 100%;
  margin: 0 auto;
  background: #fff;
  border: 1px solid #e3eaf3;
  border-radius: 16px;
  box-shadow: 0 18px 55px rgba(43, 78, 125, .11);
  overflow: hidden;

  .container {
    flex: 1;
    min-height: 0;
    display: flex;
    background: #fff;

    .chat-content {
      flex: 1;
      min-width: 0;
      height: 100%;
      border-right: 1px solid #e7edf5;
      display: flex;
      flex-direction: column;
      background: #fff;

      .chat-body {
        flex: 1;
        min-height: 0;
        overflow: hidden;
        background: #fbfcfe;

        .chat-item {
          margin-bottom: 10px;

          .time {
            text-align: center;
            color: #999999;
            font-size: 14px;
            margin: 18px 0;
          }

          .flex-box {
            display: flex;
          }

          .avatar {
            width: 40px;
            height: 40px;
            margin-right: 16px;

            img {
              display: block;
              width: 100%;
              height: 100%;
              border-radius: 50%;
            }
          }

          .msg-wrapper {
            max-width: 320px;
            background: #F5F5F5;
            border-radius: 10px;
            color: #000000;
            font-size: 14px;
            overflow: hidden;

            .txt-wrapper {
              word-break: break-all;
            }

            .pad16 {
              padding: 9px;
            }

            .img-wraper img {
              max-width: 100%;
              height: auto;
              display: block;
            }

            .order-wrapper {
              display: flex;
              width: 320px;

              .img-box {
                width: 60px;
                height: 60px;

                img {
                  width: 100%;
                  height: 100%;
                  border-radius: 5px;
                }
              }

              .order-info {
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                width: 224px;
                margin-left: 10px;
                font-size: 12px;

                .price-box {
                  display: flex;
                  align-items: center;
                  justify-content: space-between;
                  font-size: 14px;
                  color: #FF0000;

                  .more {
                    font-size: 12px;
                    color: #1890FF;
                  }
                }

                .name {
                  font-size: 14px;
                }

                .sku {
                  margin: 1px 0;
                  color: #999999;
                }
              }
            }
          }

          &.right-box {
            .flex-box {
              flex-direction: row-reverse;

              .avatar {
                margin-right: 0;
                margin-left: 16px;
              }

              .msg-wrapper {
                color: #fff;
                background: linear-gradient(135deg, #3e86ef, #5d95f5);
              }
            }

            &.gary .msg-wrapper {
              background: #f5f5f5;
            }
          }
        }
      }

      .chat-textarea {
        height: 214px;
        border-top: 1px solid #e7edf5;
        background: #fff;

        .chat-btn-wrapper {
          position: relative;
          display: flex;
          align-items: center;
          justify-content: space-between;
          padding: 15px 0;

          .left-wrapper {
            display: flex;
            align-items: center;

            .icon-item {
              display: flex;
              align-items: center;
              margin-left: 20px;
              cursor: pointer;

              .iconfont {
                font-size: 22px;
                color: #333333;
              }

              .ai-label {
                margin-left: 4px;
                font-size: 14px;
                color: #515a6e;
              }
            }
          }

          .right-wrapper {
            position: relative;
            padding-right: 20px;

            .icon-item {
              display: flex;
              align-items: center;
              font-size: 15px;
              color: #333;
              cursor: pointer;

              span {
                margin-left: 10px;
              }
            }

            .transfer-box {
              z-index: 60;
              position: absolute;
              right: 1px;
              bottom: 43px;
              width: 140px;
              background: #fff;
              box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
              padding: 16px;
            }

            .transfer-bg {
              z-index: 50;
              position: fixed;
              left: 0;
              top: 0;
              width: 100%;
              height: 100%;
              background: transparent;
            }
          }

          .emoji-box {
            position: absolute;
            left: 0;
            top: 0;
            transform: translateY(-100%);
            display: flex;
            flex-wrap: wrap;
            width: 60%;
            padding: 15px 9px;
            box-shadow: 0px 0px 13px 1px rgba(0, 0, 0, 0.1);
            background: #fff;

            .emoji-item {
              margin-right: 13px;
              margin-bottom: 8px;
              cursor: pointer;

              &:nth-child(10n) {
                margin-right: 0;
              }
            }
          }
        }
      }
    }
  }
}

.send-btn {
  position: absolute;
  right: 0;
  bottom: 10px;
  display: flex;
  justify-content: flex-end;
  margin-top: 10px;
  margin-right: 10px;

  // width: 80px;
  .btns {
    width: 100%;
    background: #3875EA;

    &[disabled] {
      background: #CCCCCC;
      color: #fff;
    }
  }
}

.bg {
  z-index: 100;
  position: fixed;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
}

/deep/.happy-scroll-content {
  width: 100%;

  .demo-spin-icon-load {
    animation: ani-demo-spin 1s linear infinite;
  }

  @keyframes ani-demo-spin {
    from {
      transform: rotate(0deg);
    }

    50% {
      transform: rotate(180deg);
    }

    to {
      transform: rotate(360deg);
    }
  }

  .demo-spin-col {
    height: 100px;
    position: relative;
    border: 1px solid #eee;
  }
}

.isMsgbox {
  >>> .ivu-modal-body {
    padding: 0;
  }
}

.ai-session {
  position: relative;
  min-height: 120px;

  .ai-list {
    margin-top: 12px;
    max-height: 400px;
    overflow-y: auto;
  }

  .ai-item {
    display: flex;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #ECECEC;

    &:last-child {
      border-bottom: none;
    }

    .avatar {
      width: 40px;
      height: 40px;

      img {
        display: block;
        width: 100%;
        height: 100%;
        border-radius: 50%;
      }
    }

    .info {
      flex: 1;
      width: 0;
      margin-left: 12px;

      .name {
        font-size: 14px;
        color: rgba(0, 0, 0, 0.65);
      }

      .msg {
        margin-top: 3px;
        font-size: 12px;
        color: #8E959E;
      }
    }

    .time {
      margin: 0 12px;
      font-size: 12px;
      color: #8E959E;
    }
  }

  .ai-empty {
    padding: 40px 0;
    text-align: center;
    font-size: 14px;
    color: #8E959E;
  }
}

.right_menu {
  position: relative;
  background: #fbfcfe;

  .crmchat_link {
    position: absolute;
    bottom: 10px;
    left: 0;
    right: 0;
    margin: auto;
    text-align: center;
    transition: 0.3s;

    span {
      color: #ccc;
    }

  }
}

.conversation-header {
  height: 64px;
  padding: 0 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex: none;
  border-bottom: 1px solid #e7edf5;
  background: #fff;
}

.conversation-user {
  min-width: 0;
  display: flex;
  align-items: center;

  img, .conversation-user__placeholder {
    width: 38px;
    height: 38px;
    margin-right: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex: none;
    border-radius: 50%;
    object-fit: cover;
  }

  .conversation-user__placeholder {
    color: #7f9bc2;
    background: #edf4fd;
    font-size: 18px;
  }

  div {
    min-width: 0;
    display: flex;
    flex-direction: column;
  }

  strong {
    overflow: hidden;
    color: #2b3c56;
    font-size: 14px;
    line-height: 1.4;
    white-space: nowrap;
    text-overflow: ellipsis;
  }

  small {
    margin-top: 3px;
    color: #9aa8bb;
    font-size: 11px;
  }
}

.conversation-badge {
  padding: 5px 10px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 1px solid #dceee6;
  border-radius: 999px;
  color: #3c8c70;
  background: #f3fbf8;
  font-size: 11px;

  i {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #32c787;
  }
}

.content-wrapper /deep/ .chatList {
  width: 330px;
  height: 100%;
  border-right-color: #e7edf5;
  background: #fff;
}

.content-wrapper /deep/ .chatList .tab-head {
  height: 56px;
  color: #53657e;
  border-bottom: 1px solid #edf1f6;
}

.content-wrapper /deep/ .chatList .chat-item {
  margin: 4px 8px;
  border-left: 0;
  border-radius: 10px;
}

.content-wrapper /deep/ .chatList .chat-item.active {
  border-left: 0;
  background: #eef5ff;
  box-shadow: inset 3px 0 0 #3e86ef;
}

.content-wrapper .right_menu /deep/ .right-wrapper {
  height: 100%;
  border-left: 0;
  background: #fbfcfe;
}

/* 窄屏单栏：会话列表与对话互斥展示，右侧资料栏收起 */
.narrow-back {
  display: none;
  align-items: center;
  gap: 4px;
  height: 44px;
  padding: 0 12px;
  border-bottom: 1px solid #ECECEC;
  color: #17233d;
  font-size: 15px;
  cursor: pointer;
  flex: none;
}

.chat-scroll-inner {
  width: 100%;
  padding: 20px;
  box-sizing: border-box;
}

/* happy-scroll 默认按内容宽度收缩，会让右侧消息停在中间；对话区必须占满，左右消息才能镜像贴边 */
.chat-body /deep/ .happy-scroll,
.chat-body /deep/ .happy-scroll-container,
.chat-body /deep/ .happy-scroll-content {
  width: 100% !important;
}

.kefu-layouts.is-narrow {
  padding-top: 0;
  overflow: hidden;

  .content-wrapper {
    width: 100%;
    height: 100vh;
    border: 0;
    border-radius: 0;
    box-shadow: none;
  }

  .narrow-back {
    display: flex;
  }

  .chat-scroll-inner {
    width: 100%;
    box-sizing: border-box;
  }

  .container {
    position: relative;
    overflow: hidden;
  }

  /* 子组件把宽高都写死了，窄屏需一并放开 */
  /deep/ .chatList {
    width: 100%;
    height: 100%;
    border-right: 0;
  }

  .chat-content {
    display: none;
    width: 100%;
    height: 100%;
    border-right: 0;
  }

  /* 资料栏在窄屏挤不下，先收起；需要时可从对话页另开入口 */
  /deep/ .right-wrapper {
    display: none;
  }
}

.kefu-layouts.is-narrow.show-chat {
  /deep/ .chatList {
    display: none;
  }

  .chat-content {
    display: flex;
  }
}

</style>
