import moment from 'moment'

/**
 * 聊天消息的时间戳格式化
 *
 * 跨年的消息不带年份就分不清是哪一年——历史记录往前翻很容易翻到去年，
 * 只显示「3月5日」会让人误以为是今年。本年则省略年份，免得每条都很长。
 * 一律用 24 小时制：12 小时制不带上下午标识，下午两点会显示成 2:00。
 * @param {Number} timestamp 秒级时间戳
 * @returns {String}
 */
export function formatChatTime (timestamp) {
  const m = moment(timestamp * 1000)
  return m.year() === moment().year() ? m.format('MMMDo H:mm') : m.format('YYYY年MMMDo H:mm')
}
