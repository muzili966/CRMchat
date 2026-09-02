-- 版本：V20260902_02
-- 内容：悬浮挂件配置（客户端装修）
-- 依赖：eb_application_theme 需已有 mobile_icon 列
--
-- 悬浮按钮由嵌入脚本在接入方页面渲染，早于聊天窗口打开，拿不到走 websocket
-- 下发的装修数据，故新增公开接口 GET /api/mobile/widget?token=xxx。
-- 「PC悬浮图标」「移动端图标」两项此前虽在后台可配但从未生效，一并由该接口下发。
-- 生效优先级：接入方显式传参 > 后台装修 > 脚本内置默认值。
--
-- 该变更原记于 update.sql 末尾（2026-09-01），是冻结前的最后一批追加。
-- 停在改制之前的库不会有任何环节执行它，故在此纳入版本化管理。

-- information_schema 判断使脚本可重复执行；加列没有 IF NOT EXISTS
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eb_application_theme' AND COLUMN_NAME = 'show_tip');
SET @s := IF(@c = 0, 'ALTER TABLE `eb_application_theme` ADD COLUMN `show_tip` tinyint(1) NOT NULL DEFAULT 1 COMMENT ''是否显示悬浮客服按钮'' AFTER `mobile_icon`', 'DO 0');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

-- 依赖上一列，故分开判断：show_tip 已存在时本列仍可能缺失
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eb_application_theme' AND COLUMN_NAME = 'window_style');
SET @s := IF(@c = 0, 'ALTER TABLE `eb_application_theme` ADD COLUMN `window_style` varchar(20) NOT NULL DEFAULT ''float'' COMMENT ''窗口形态float悬浮/center居中'' AFTER `show_tip`', 'DO 0');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;
