-- 版本：V20260903_04
-- 内容：悬浮入口图标列扩容，容纳预设的内联 SVG data-URI
-- 依赖：无
--
-- 悬浮图标新增「主题色气泡」预设，存为内联 SVG data-URI（跟随主题色），
-- 原 varchar(255) 装不下，扩到 1000。自定义上传的 URL 仍远小于此。

ALTER TABLE `eb_application_theme` MODIFY COLUMN `pc_icon` varchar(1000) NOT NULL DEFAULT '' COMMENT 'PC悬浮图标,预设为SVG data-URI或自定义URL';
ALTER TABLE `eb_application_theme` MODIFY COLUMN `mobile_icon` varchar(1000) NOT NULL DEFAULT '' COMMENT '移动端悬浮图标';
