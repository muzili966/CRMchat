<template>

  <div class="pc_customerServer">
    <div class="fixed" v-if="upperData.noCanClose == 1"></div>
    <div class="pc_customerServer_container max-width_con" :class="[themeClass, bubbleClass, {'max-width_advertisement': upperData.noCanClose == 1 || upperData.windowStyle == `center`}]" :style="themeRootStyle">
      <!-- 客服头部开始 -->
      <div class="pc_customerServer_container_header" :style="themeBgStyle">
        <div class="pc_customerServer_container_header_title">

          <img v-if="headerLogo" :src="headerLogo" alt="">
          <span>{{headerTitle}}</span>
        </div>
        <div class="pc_customerServer_container_header_handle" @click="closeIframe" v-if="upperData.noCanClose != '1'">
          <span class="iconfont">&#xe6c5;</span>
        </div>
      </div>
      <!-- 客服头部结束 -->

      <div class="layout_content">

        <div class="layout_customerServer_content">
          <!-- 聊天内容开始 -->
          <div class="pc_customerServer_container_content">
            <div class="productMessage_container" v-if="isShowProductModel">
              <div class="productMessage_container_image">
                <img :src="productMessage.image" alt="">
              </div>
              <div class="productMessage_container_content">
                <div class="productMessage_container_content_title">{{productMessage.store_name}}</div>
                <div class="productMessage_container_content_priceOrHandle">
                  <div>￥{{productMessage.price}}</div>
                  <div @click="sendProduct">发送客服</div>
                </div>
              </div>
            </div>
            <happy-scroll size="1" resize hide-horizontal :scroll-top="scrollTop" @vertical-start="scrollHandler">
              <div class="scroll_content" id="chat_scroll" :class="{ 'pt140': isShowProductModel || inputConType == 2 }">
                <!-- 滑动到容器顶部时，动画加载 -->
                <Spin v-show="isLoad">
                  <Icon type="ios-loading" size=18 class="demo-spin-icon-load"></Icon>
                  <div>Loading</div>
                </Spin>
                <!-- 动画结束 -->

                <!-- 聊天内容列表 -->
                <div class="chart_list">

                  <div class="chart_list_item" v-for="(item, index) in records" :key="index">

                    <div class="chart_list_item_time" v-show="item.show">{{item.time}}</div>
                    <div class="chart_list_item_content" :class="{'right-box': item.user_id == chatServerData.user_id}">
                      <div class="chart_list_item_avatar">
                        <img :src="item.avatar" alt="">
                      </div>
                      <!-- 文字及表情信息 -->
                      <div class="chart_list_item_text" v-if="item.msn_type <= 2" :style="messageBubbleStyle(item)">
                        <span v-html="replace_em(item.msn)"></span>
                      </div>
                      <!-- 图片信息 -->
                      <div class="chart_list_item_img" v-if="item.msn_type == 3">
                        <img v-lazy="item.msn" @load="imageLoad" />
                      </div>

                      <!-- 图文信息 -->
                      <div class="chart_list_item_imgOrText" v-if="item.msn_type == 5">
                        <div class="order-wrapper">
                          <div class="img-box">
                            <img :src="item.other.image" alt="">
                          </div>
                          <div class="order-info">
                            <div class="price-box">
                              <div class="num">¥ {{item.other.price}}</div>
                              <!-- <a herf="javascript:;" class="more" @click.stop="lookGoods(item)">查看商品 ></a> -->
                            </div>
                            <div class="name">{{item.other.store_name}}</div>
                          </div>

                        </div>
                      </div>

                    </div>

                  </div>
                </div>
                <!-- 聊天内容列表结束 -->
              </div>
            </happy-scroll>

          </div>
          <!-- 聊天内容结束 -->

          <!-- 内容输入开始 -->
          <div class="pc_customerServer_container_footer">
            <div class="pc_customerServer_container_footer_header">
              <!-- 表情及图片容器 -->
              <div class="pc_customerServer_container_footer_emoji" v-if="inputConType == 2">
                <div class="emoji-item" v-for="(emoji, index) in emojiList" :key="index">
                  <i class="em" :class="emoji" @click.stop="select(emoji)"></i>
                </div>
              </div>
              <div class="pc_customerServer_container_footer_header_handle">
                <div @click="inputConType = 2;goPageBottom()">
                  <img src="@/assets/images/customerServer/face.png" alt="">
                </div>
                <div>
                  <img src="@/assets/images/customerServer/picture.png" alt="">
                  <input type="file" accept=".jp2,.jpe,.jpeg,.jpg,.png,.svf,.tif,.tiff" class="type_file" @change="uploadFile">
                </div>
                <div class="transfer_service" title="转人工客服" @click="transferService" v-if="isShowTransfer">
                  <Icon type="md-person" size="18" />
                </div>
              </div>
            </div>

            <!-- 输入框容器 -->
            <div class="pc_customerServer_container_footer_input" @click="inputConType = 1">
              <!-- <textarea v-model="userMessage" @keyup.enter="sendText" class="pc_customerServer_container_footer_input-textarea opacity0" rows="5" placeholder="请输入文字"></textarea> -->
              <div v-paste="handleParse" ref="inputDiv" @keyup.enter="sendText" contenteditable class="pc_customerServer_container_footer_input-textarea" :class="{'readyEmojiHeight': inputConType == 2}">
              </div>
            </div>
            <!-- 输入框容器结束 -->
            <!-- 表情及图片容器结束 -->
            <!-- 相关操作 -- 点击发送 -->
            <div class="pc_customerServer_container_footer_handle">

              <div class="pc_customerServer_container_footer_handle_send" @click="sendText" :style="themeBgStyle">
                <span>发送</span>
              </div>

            </div>
            <div class="pc_customerServer_container_footer_copyright" @click="tolink" v-if="isShowPlatformBrand && upperData.noCanClose != '1' && upperData.windowStyle != `center`">
              <span>QiaLink 洽联智能客服</span>
            </div>
            <!-- 相关操作结束 -->

          </div>
          <!-- 内容输入结束 -->
        </div>

        <div class="pc_customerServer_container_advertisement" v-if="upperData.noCanClose == '1' || upperData.windowStyle == `center`">
          <div class="advertisement">
            <!-- 广告位优先级：装修轮播图 > 装修自定义HTML -->
            <Carousel class="theme_banner" v-if="themeBanners.length" v-model="bannerIndex" loop autoplay :autoplay-speed="bannerAutoplaySpeed" :height="bannerHeight" arrow="never" radius-dot>
              <CarouselItem v-for="(banner, index) in themeBanners" :key="index">
                <div class="theme_banner_item" :class="{'theme_banner_item-link': banner.link}" :style="{ height: bannerHeight + 'px' }" @click="openBanner(banner)">
                  <img :src="banner.image" alt="">
                </div>
              </CarouselItem>
            </Carousel>
            <div v-else-if="themeCustomHtml" v-html="themeCustomHtml"></div>
            <div class="copyright" @click="tolink" v-if="isShowPlatformBrand">
              <span>QiaLink 洽联智能客服</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 平台标识，白标套餐(show_platform_brand=0)下隐藏 -->
      <div class="platform_brand" v-if="isShowPlatformBrand">{{platformBrandText}}</div>

    </div>

  </div>
</template>
<script>
import { HappyScroll } from 'vue-happy-scroll'
import emojiList from "@/utils/emoji";
import socketServer from './minix/socketServer';
import appTheme from './minix/appTheme';

// 与后端转人工关键词保持一致，后端收到该文本后自动分配人工坐席
const TRANSFER_KEYWORD = '转人工';
const MSN_TYPE_TEXT = 1;
// 广告位固定高度，避免图片异步加载时轮播算不出高度而塌陷
const BANNER_HEIGHT = 150;

export default {
  components: {
    HappyScroll
  },
  mixins: [socketServer, appTheme],
  data() {
    return {
      happyScroll: false,
      isLoad: false,
      scrollTop: 0,
      emojiList: emojiList,
      inputConType: 1,
      isTransferred: false,
      bannerHeight: BANNER_HEIGHT
    }
  },
  watch: {
    'chatServerData.to_user_id'(val, oldVal) {
      // 首次拿到接待坐席时 oldVal 为空，只有换人（to_transfer）才算已转接
      if (oldVal && val != oldVal) {
        this.isTransferred = true;
      }
    }
  },
  computed: {
    // 仅与 AI 坐席对话时才需要转人工入口
    isShowTransfer() {
      //已转接过人工则不再展示；字段缺失(旧后端)时保持显示
      if (this.isTransferred) return false;
      let isAi = this.chatServerData.to_user_is_ai;
      return isAi === undefined || isAi === null ? true : !!Number(isAi);
    },
    records() {
      return this.chatServerData.serviceList.map((item, index) => {
        item.time = this.$moment(item.add_time * 1000).format('MMMDo H:mm')
        if(index) {
          if(
            item.add_time -
            this.chatServerData.serviceList[index - 1].add_time >=
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
    }
  },
  methods: {

    getScrollTop() {
      console.log(123);
    },
    getScrollEnd() {
      console.log(321);
    },
    scrollHandler(e) {
      console.log('滑动到顶部了');
      this.isLoad = true;
      setTimeout(() => {
        this.isLoad = false;
      }, 2000)
    },
    // 转人工：以访客身份发送关键词文本，转接动作由后端识别关键词后完成
    transferService() {
      this.$Modal.confirm({
        title: '转人工客服',
        content: '确定要转接人工客服吗？',
        onOk: () => {
          this.sendMsg(TRANSFER_KEYWORD, MSN_TYPE_TEXT);
        }
      });
    },
    // 聊天表情转换
    replace_em(str) {
      str = str.replace(/\[em-([a-z_]*)\]/g, "<span class='em em-$1'/></span>");
      return str;
    },
  }
}
</script>
<style lang="less" scoped>
.max-width_con {
  max-width: 600px;
}
.max-width_advertisement {
  max-width: 840px;
}
.pc_customerServer_container {
  width: 100%;
  height: 100%;
  max-height: 654px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  background: #fff;
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  box-shadow: 1px 1px 15px 0px rgba(0, 0, 0, 0.3);
  border-radius: 8px;
  overflow: hidden;
  &_header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(270deg, #1890ff, #3875ea);
    padding: 14px 14px;
    box-sizing: border-box;
    height: 56px;
    font-size: 16px;
    color: #fff;
    &_title {
      display: flex;
      align-items: center;
      img {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        margin-right: 10px;
      }
    }
    &_handle {
      cursor: pointer;
    }
  }

  &_content {
    flex: 1;
    overflow: hidden;

    .scroll_content {
      width: 100%;
      height: 100%;
      overflow-y: auto;
      padding-bottom: 20px;
      box-sizing: border-box;
      position: relative;
      .chart_list {
        &_item {
          &_content {
            display: flex;
            align-items: center;
            padding: 8px;
            box-sizing: border-box;
          }

          &_avatar {
            width: 33px;
            height: 33px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 10px;
            align-self: flex-start;
            img {
              width: 100%;
              height: 100%;
            }
          }
          &_text {
            max-width: 60%;
            word-wrap: break-word;
            background: #f5f5f5;
            padding: 7px 14px;
            font-size: 15px;
            border-radius: 4px;
          }
          &_img {
            max-width: 60%;
            img {
              width: 100%;
              height: auto;
            }
          }
          .chart_list_item_imgOrText {
            background: #ccc;
            padding: 4px 10px;
            border-radius: 8px;
            width: 226px;
            box-sizing: border-box;
            .order-wrapper {
              .img-box {
                width: 100%;
                img {
                  width: 100%;
                  height: auto;
                }
              }
              .order-info {
                .price-box {
                  color: #ff0000;
                  font-size: 18px;
                }
                .name {
                  font-size: 14px;
                }
              }
            }
          }
          &_time {
            text-align: center;
            margin: 10px auto;
          }
          .right-box {
            flex-direction: row-reverse;
            .chart_list_item_avatar {
              margin-left: 10px;
            }
            .chart_list_item_text {
              text-align: left;
              background: #cde0ff;
              color: #000000;
            }
            .chart_list_item_img {
              text-align: right;
              background: #fff;
              img {
                width: 100%;
                height: auto;
              }
            }
            .chart_list_item_imgOrText {
              background: #fff;
              padding: 10px;
              border-radius: 8px;
              width: 226px;
              box-sizing: border-box;
              .order-wrapper {
                .img-box {
                  width: 100%;
                  img {
                    width: 100%;
                    height: auto;
                  }
                }
                .order-info {
                  .price-box {
                    color: #ff0000;
                    font-size: 18px;
                  }
                  .name {
                    font-size: 14px;
                  }
                }
              }
            }
          }
          &_text {
          }
        }
      }
    }
  }
  &_footer {
    background: #fff;
    padding: 14px 18px;
    min-height: 180px;
    border-top: 1px solid #ececec;
    &_header {
      position: relative;
      &_handle {
        display: flex;

        > div {
          margin-right: 19px;
          position: relative;
          img {
            width: 18px;
            height: 18px;
          }
          .type_file {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            opacity: 0;
          }
        }
        .transfer_service {
          height: 18px;
          line-height: 18px;
          color: #666;
          cursor: pointer;
        }
      }
    }

    &_input {
      &-textarea {
        width: 100%;
        height: 100%;
        border: none;
        outline: none;
        padding-top: 4px;
        font-size: 14px;
        line-height: 16px;
      }
      > div {
        height: 90px;
        max-height: 90px;
        overflow: auto;
      }
      .opacity0 {
        opacity: 0;
      }
      .readyEmojiHeight {
        height: 90px;
      }
    }
    &_emoji {
      max-width: 420px;
      display: grid;
      grid-template-columns: repeat(9, 1fr);
      padding-top: 10px;
      max-height: 150px;
      overflow-y: auto;
      background: #fff;
      position: absolute;
      bottom: 50px;
      left: 0;

      .emoji-item {
        padding: 6px;
        display: flex;
        justify-content: center;
        align-items: center;
      }
    }

    &_handle {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      margin-bottom: 10px;
      &_send {
        width: 56px;
        height: 26px;
        background: #3875ea;
        border-radius: 3px;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        position: relative;
        z-index: 100;
        margin: 0 20px 20px 0;
      }
    }
    &_copyright {
      position: absolute;
      left: -8%;
      width: 110%;
      display: block;
      text-align: center;
      // 让位给容器底部的平台标识行，两者显示条件一致，不会出现留白
      bottom: 22px;
      color: #bbb;
      padding: 5px 10px;
      /*background-color: #eee;*/
    }
  }
}

/* 预设的具体数值来自 @/config/chatThemes.js，此处只声明桌面版的基准尺寸并乘以密度倍率，
   移动版与后台预览用同一套变量各自换算，改预设只需改那一个文件 */
.pc_customerServer_container {
  .pc_customerServer_container_header {
    justify-content: var(--chat-header-justify, space-between);

    &_title img {
      display: var(--chat-header-logo, block);
      width: ~"calc(30px * var(--chat-avatar-scale, 1))";
      height: ~"calc(30px * var(--chat-avatar-scale, 1))";
    }
  }

  .pc_customerServer_container_content {
    .chart_list_item_content {
      padding-top: ~"calc(8px * var(--chat-density, 1))";
      padding-bottom: ~"calc(8px * var(--chat-density, 1))";
    }

    .chart_list_item_avatar {
      display: var(--chat-avatar-display, block);
      width: ~"calc(33px * var(--chat-avatar-scale, 1))";
      height: ~"calc(33px * var(--chat-avatar-scale, 1))";
    }

    .scroll_content .chart_list_item_text,
    .scroll_content .right-box .chart_list_item_text {
      max-width: ~"calc(60% * var(--chat-bubble-width-scale, 1))";
      padding-right: var(--chat-bubble-pad-x, 14px);
      padding-left: var(--chat-bubble-pad-x, 14px);
      border-width: var(--chat-bubble-border-width, 1px);
      box-shadow: var(--chat-bubble-shadow, none);
    }

    .scroll_content .chart_list_item_text {
      background: var(--chat-bubble-fill, var(--chat-incoming));
      border-radius: var(--chat-bubble-radius-in, 14px 14px 14px 4px);
    }

    .scroll_content .right-box .chart_list_item_text {
      border-radius: var(--chat-bubble-radius-out, 14px 14px 4px 14px);
    }
  }

  .pc_customerServer_container_footer {
    min-height: ~"calc(180px * var(--chat-density, 1))";
    padding-top: ~"calc(14px * var(--chat-density, 1))";
    padding-bottom: ~"calc(14px * var(--chat-density, 1))";
  }
}

.platform_brand {
  position: absolute;
  left: 0;
  bottom: 0;
  width: 100%;
  text-align: center;
  font-size: 12px;
  line-height: 14px;
  color: #bbb;
  padding: 4px 0;
  background: #fff;
  z-index: 1;
}

.theme_banner {
  margin-bottom: 10px;
  &_item {
    overflow: hidden;
    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }
  }
  &_item-link {
    cursor: pointer;
  }
}

.layout_content {
  flex: 1;
  display: flex;
  // justify-content: space-between;
  .layout_customerServer_content {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    border-right: 1px solid #ececec;
  }
  .pc_customerServer_container_advertisement {
    width: 260px;
    background: #fff;
    .advertisement {
      padding: 5px;
      box-sizing: border-box;
      height: 550px;
      overflow-y: auto;
      img {
        max-width: 100% !important;
      }
    }
    .copyright {
      position: fixed;
      // 同上，让位给底部平台标识行
      bottom: 42px;
      text-align: center;
      width: 230px;
      transition: 0.3s;
      z-index: 99;
      cursor: pointer;
      span {
        color: #ccc;
      }

      span:hover {
        color: #007aff;
      }
    }
  }
}

.demo-spin-icon-load {
  animation: ani-demo-spin 1s linear infinite;
}

.productMessage_container {
  height: 94px;
  width: 100%;
  padding: 12px;
  box-sizing: border-box;
  background: #fff;
  display: flex;
  justify-content: space-between;
  &_image {
    margin-right: 12px;
    img {
      width: 77px;
      height: 77px;
    }
  }
  &_content {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    &_title {
      font-size: 14px;
      color: #333;
      height: 42px;
      font-weight: 800;
      overflow: hidden;
      text-overflow: ellipsis;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      text-align: left !important;
    }
    &_priceOrHandle {
      display: flex;
      justify-content: space-between;
      > div:nth-child(1) {
        font-size: 18px;
        color: #e93323;
        text-align: left;
      }
      > div:nth-child(2) {
        width: 65px;
        height: 25px;
        background: #e83323;
        opacity: 1;
        border-radius: 62px;
        color: #fff;
        font-size: 12px;
        text-align: center;
        line-height: 25px;
        cursor: pointer;
      }
    }
  }
}
.fixed {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.7);
}
.pt140 {
  padding-bottom: 140px !important;
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
/deep/ .happy-scroll-content {
  width: 100% !important;
  box-sizing: border-box;
}

.pc_customerServer_container {
  color: var(--chat-text);
  background: var(--chat-surface);
  border: 1px solid var(--chat-border);
  box-shadow: var(--chat-shadow);

  &_header {
    box-shadow: 0 5px 18px rgba(16, 42, 85, .13);

    &_title img {
      object-fit: cover;
      border: 2px solid rgba(255, 255, 255, .7);
    }
  }

  &_content {
    background: var(--chat-page-bg);

    .scroll_content .chart_list_item {
      &_text {
        color: var(--chat-text);
        line-height: 1.55;
        background: var(--chat-incoming);
        border: 1px solid var(--chat-border);
        border-radius: 14px 14px 14px 4px;
        box-shadow: 0 3px 12px rgba(31, 45, 61, .05);
      }

      &_time {
        color: var(--chat-muted);
        font-size: 11px;
      }

      .right-box .chart_list_item_text {
        color: #fff;
        background: var(--chat-primary);
        border-color: transparent;
        border-radius: 14px 14px 4px 14px;
      }
    }
  }

  &_footer {
    color: var(--chat-text);
    background: var(--chat-surface);
    border-color: var(--chat-border);

    &_input-textarea {
      color: var(--chat-text);
      background: transparent;
    }

    &_header_handle .transfer_service,
    &_copyright {
      color: var(--chat-muted);
    }
  }

  .layout_customerServer_content {
    border-color: var(--chat-border);
  }

  .pc_customerServer_container_advertisement,
  .productMessage_container,
  .platform_brand {
    color: var(--chat-text);
    background: var(--chat-surface);
  }

  .productMessage_container_content_title {
    color: var(--chat-text);
  }

  .platform_brand {
    color: var(--chat-muted);
  }
}
</style>
<style lang="less">
.advertisement {
  img,
  p,
  div,
  span {
    max-width: 100%;
  }
}
.happy-scroll-container {
  width: 100% !important;
}
.advertisement {
  overflow: auto !important;
}
.advertisement::-webkit-scrollbar {
  /*滚动条整体样式*/
  width: 1px; /*高宽分别对应横竖滚动条的尺寸*/
  height: 1px;
}
.advertisement::-webkit-scrollbar-thumb {
  /*滚动条里面小方块*/
  border-radius: 10px;
  background-color: skyblue;
  background-image: -webkit-linear-gradient(
    45deg,
    rgba(255, 255, 255, 0.2) 25%,
    transparent 25%,
    transparent 50%,
    rgba(255, 255, 255, 0.2) 50%,
    rgba(255, 255, 255, 0.2) 75%,
    transparent 75%,
    transparent
  );
}
.advertisement::-webkit-scrollbar-track {
  /*滚动条里面轨道*/
  box-shadow: inset 0 0 5px rgba(0, 0, 0, 0.2);
  background: #ededed;
  border-radius: 10px;
}
</style>
