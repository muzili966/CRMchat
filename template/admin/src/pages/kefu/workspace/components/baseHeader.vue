<template>
  <div class="base-header">
    <div class="brand-block">
      <img class="brand-logo" :src="brandLogo" alt="QiaLink 洽联">
      <span class="brand-divider"></span>
      <div class="brand-copy">
        <strong>客服工作台</strong>
        <small>Service Desk</small>
      </div>
    </div>
    <div class="header-actions">
      <div class="user_info">
        <img class="user-avatar" v-lazy="kefuInfo.avatar" :alt="kefuInfo.nickname || '客服头像'">
        <span class="user-name">{{kefuInfo.nickname}}</span>
        <div class="status-box">
          <button type="button" class="status" :class="online ? 'on':'off'" @click.stop="setOnline">
            <span class="dot"></span>
            {{online ? '在线': '离线'}}
            <Icon type="ios-arrow-down" />
          </button>

          <div class="online-down" v-show="isOnline">
            <div class="item" @click.stop="changeOnline(1)"><span class="iconfont iconduihao" v-if="online == 1"></span><i class="green"></i>在线</div>
            <div class="item" @click.stop="changeOnline(0)"><span class="iconfont iconduihao" v-if="online == 0"></span><i></i>离线</div>
          </div>
        </div>
      </div>
      <button type="button" class="logout-button" title="退出登录" @click.stop="outLogin">
        <Icon type="ios-log-out" />
        <span>退出登录</span>
      </button>
    </div>
  </div>
</template>

<script>
import { mapActions } from 'vuex';
import bus from '@/utils/bus'
import brandLogo from '@/assets/images/qialink-logo-horizontal.png'
export default {
  name: "baseHeader",
  props: {
    kefuInfo: {
      type: Object,
      default: function() {
        return {}
      }
    },
    online: {
      type: [Boolean, Number],
      default: true
    }
  },
  computed: {
  },
  data() {
    return {
      brandLogo,
      menuList: [
        {
          key: 0,
          title: '客户信息',
        },
        {
          key: 1,
          title: '交易订单',
        },
        {
          key: 2,
          title: '商品信息',
        },
      ],
      curIndex: 0,
      isOnline: false
    }
  },
  mounted() {
    document.addEventListener('click', this.handleDocumentClick)
  },
  beforeDestroy() {
    document.removeEventListener('click', this.handleDocumentClick)
  },
  methods: {
    ...mapActions('kefu/', [
      'logoutKefu'
    ]),
    handleDocumentClick() {
      this.isOnline = false
    },
    selectTab(item) {
      this.curIndex = item.key
      this.bus.$emit('selectRightMenu', this.curIndex)
    },
    setOnline() {
      this.isOnline = !this.isOnline

    },
    changeOnline(type) {
      if(type == 3) {
        this.outLogin();
        return;
      }
      this.$emit('setOnline', type);
      this.isOnline = false
    },
    // 退出登录
    outLogin() {
      let self = this
      this.$Modal.confirm({
        title: '退出登录确认',
        content: '您确定退出登录当前账户吗？打开的标签页和个人设置将会保存。',
        onOk: () => {
          self.logoutKefu({
            confirm: false,
            vm: self
          });
        },
        onCancel: () => {

        }
      });

    },
    // 搜索
    bindSearch(e) {
      this.$emit('search', e.target.value);
    },
    // inputChange
    inputChange(e) {
      console.log(e.target.value)
      this.bus.$emit('change', e.target.value)
    }
  }
}
</script>

<style lang="stylus" scoped>
.base-header {
  z-index: 99;
  height: 72px;
  padding: 0 22px;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
  color: #223451;
  background: #fff;
  border-bottom: 1px solid #e7edf5;
  box-shadow: 0 5px 18px rgba(37, 76, 128, .05);
}

.brand-block {
  min-width: 0;
  display: flex;
  align-items: center;
}

.brand-logo {
  width: 136px;
  height: auto;
  display: block;
  object-fit: contain;
}

.brand-divider {
  width: 1px;
  height: 26px;
  margin: 0 16px;
  background: #dfe7f2;
}

.brand-copy {
  display: flex;
  flex-direction: column;
}

.brand-copy strong {
  color: #263957;
  font-size: 14px;
  line-height: 1.2;
  font-weight: 600;
}

.brand-copy small {
  margin-top: 3px;
  color: #a0adbf;
  font-size: 9px;
  line-height: 1;
  letter-spacing: 1.2px;
  text-transform: uppercase;
}

.header-actions, .user_info {
  display: flex;
  align-items: center;
}

.header-actions {
  gap: 14px;
}

.user_info {
  padding-right: 14px;
  border-right: 1px solid #e8eef6;
}

.user-avatar {
  width: 38px;
  height: 38px;
  margin-right: 10px;
  display: block;
  border: 2px solid #fff;
  border-radius: 50%;
  object-fit: cover;
  box-shadow: 0 0 0 1px #dfe8f4;
}

.user-name {
  max-width: 130px;
  overflow: hidden;
  color: #344761;
  font-size: 14px;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.status-box {
  margin-left: 8px;
  position: relative;
}

.status {
  height: 30px;
  padding: 0 10px;
  display: flex;
  align-items: center;
  gap: 5px;
  border: 1px solid #dceee6;
  border-radius: 999px;
  color: #278765;
  background: #f1fbf7;
  cursor: pointer;
  font-size: 12px;
}

.status.off {
  border-color: #e3e8ef;
  color: #7f8da2;
  background: #f6f8fb;
}

.status .dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #32c787;
  box-shadow: 0 0 0 3px rgba(50, 199, 135, .12);
}

.status.off .dot {
  background: #a5b0c0;
  box-shadow: 0 0 0 3px rgba(165, 176, 192, .12);
}

.status > i {
  margin-left: 1px;
  color: currentColor;
  font-size: 12px;
}

.online-down {
  z-index: 50;
  width: 126px;
  padding: 6px;
  position: absolute;
  top: 38px;
  right: 0;
  border: 1px solid #e5ebf3;
  border-radius: 10px;
  color: #42536d;
  background: #fff;
  box-shadow: 0 14px 34px rgba(39, 68, 108, .14);
}

.online-down .item {
  height: 34px;
  padding: 0 10px;
  position: relative;
  display: flex;
  align-items: center;
  border-radius: 7px;
  cursor: pointer;
  font-size: 13px;
}

.online-down .item:hover {
  color: #287feb;
  background: #f2f7ff;
}

.online-down .item i {
  width: 8px;
  height: 8px;
  margin-right: 9px;
  border-radius: 50%;
  background: #a5b0c0;
}

.online-down .item i.green {
  background: #32c787;
}

.online-down .item .iconfont {
  margin-left: auto;
  order: 2;
  color: #287feb;
  font-size: 11px;
}

.logout-button {
  height: 38px;
  padding: 0 14px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  border: 1px solid #dfe7f2;
  border-radius: 9px;
  color: #66768e;
  background: #fff;
  cursor: pointer;
  font-size: 13px;
  transition: color .2s, border-color .2s, background .2s;
}

.logout-button:hover {
  color: #e24a4a;
  border-color: #f1caca;
  background: #fff7f7;
}

.logout-button > i {
  font-size: 18px;
}

@media (max-width: 720px) {
  .base-header {
    height: 60px;
    padding: 0 14px;
  }

  .brand-logo {
    width: 118px;
  }

  .brand-divider, .brand-copy, .user-name {
    display: none;
  }

  .header-actions {
    gap: 8px;
  }

  .user_info {
    padding-right: 8px;
  }

  .user-avatar {
    width: 34px;
    height: 34px;
    margin-right: 2px;
  }

  .status-box {
    margin-left: 4px;
  }

  .status {
    width: 30px;
    padding: 0;
    justify-content: center;
    font-size: 0;
  }

  .status > i {
    display: none;
  }

  .logout-button {
    width: 38px;
    padding: 0;
  }

  .logout-button span {
    display: none;
  }
}
</style>
