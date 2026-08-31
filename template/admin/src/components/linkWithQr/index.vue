<template>
  <div class="link-qr">
    <Poptip trigger="hover" placement="left" transfer @on-popper-show="renderQr">
      <span class="link-qr-text" :title="link">{{ link }}</span>
      <div slot="content" class="link-qr-pop">
        <div class="link-qr-canvas" ref="qr"></div>
        <p class="link-qr-tip">扫码在手机上打开</p>
      </div>
    </Poptip>
    <a class="link-qr-copy" @click="copy">复制</a>
  </div>
</template>

<script>
  import QRCode from 'qrcodejs2'

  const QR_SIZE = 148

  export default {
    name: 'linkWithQr',
    props: {
      link: { type: String, required: true }
    },
    data () {
      return { qr: null }
    },
    watch: {
      // 同一个组件实例可能被复用于不同的链接（如切换分页），需重绘
      link () {
        if (this.qr) {
          this.qr.clear()
          this.qr.makeCode(this.link)
        }
      }
    },
    methods: {
      // 悬浮时才生成：列表有多行，一次性全画会明显拖慢渲染
      renderQr () {
        if (this.qr || !this.$refs.qr) return
        this.qr = new QRCode(this.$refs.qr, {
          text: this.link,
          width: QR_SIZE,
          height: QR_SIZE,
          correctLevel: QRCode.CorrectLevel.M
        })
      },
      copy () {
        const input = document.createElement('textarea')
        input.value = this.link
        //置于视口外而非 display:none，后者在部分浏览器上选不中内容
        input.style.cssText = 'position:fixed;top:-1000px;opacity:0'
        document.body.appendChild(input)
        input.select()
        let ok = false
        try { ok = document.execCommand('copy') } catch (e) { ok = false }
        document.body.removeChild(input)
        ok ? this.$Message.success('链接已复制') : this.$Message.error('复制失败，请手动选择链接复制')
      }
    }
  }
</script>

<style scoped>
  .link-qr {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .link-qr-text {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #2d8cf0;
    cursor: pointer;
  }

  .link-qr-copy {
    flex: none;
  }

  .link-qr-pop {
    text-align: center;
  }

  .link-qr-canvas {
    display: inline-block;
    line-height: 0;
  }

  .link-qr-tip {
    margin-top: 6px;
    font-size: 12px;
    color: #808695;
  }
</style>
