-- 版本：V20260903_05
-- 内容：悬浮入口图标列再扩容至 2000
-- 依赖：V20260903_04
--
-- 悬浮图标新增形状与文案：胶囊形带中文文案时，内联 SVG data-URI 中每个中文字
-- 百分号编码后约 9 字符，6 字文案的整值约 1080，超过原 1000。扩到 2000 留足余量。

ALTER TABLE `eb_application_theme` MODIFY COLUMN `pc_icon` varchar(2000) NOT NULL DEFAULT '' COMMENT 'PC悬浮图标,预设为SVG data-URI或自定义URL';
ALTER TABLE `eb_application_theme` MODIFY COLUMN `mobile_icon` varchar(2000) NOT NULL DEFAULT '' COMMENT '移动端悬浮图标';
