-- 版本：V20260908_05
-- 内容：常见问题卡片（访客端新会话弹出）
-- 依赖：V20260908_03（会话实体，卡片按会话判断是否已展示）
--
-- 复用既有的 eb_chat_auto_reply：它本就是「问题→答案」存储，且已接入
-- WebSocket 收消息链路（BaseHandler:280），只是后台一直没有配置页面，
-- 表里 0 行、等于没这功能。这里补齐字段，让同一份数据既供访客点卡片，
-- 也供访客打字命中关键词，避免两处维护、答案打架。
--
-- 三处调整的理由：
-- 1. content varchar(255) -> text：退款政策一类答案很容易超 255。
-- 2. 新增 title：卡片展示的完整问句（"如何申请退款？"），
--    区别于匹配用的 keyword（"退款"）。
-- 3. 新增 is_faq + user_id=0 约定：既有自动回复按客服各配一套，
--    而 FAQ 是站点级的，不该因分配到哪个客服而不同。user_id=0
--    表示全站通用，卡片只取这类。

ALTER TABLE `eb_chat_auto_reply`
    MODIFY COLUMN `content` text NOT NULL COMMENT '回复内容';

-- 分两条 ALTER，便于失败时定位；MySQL DDL 非事务，脚本必须可重复执行，
-- 故先判断列是否存在。
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eb_chat_auto_reply' AND COLUMN_NAME = 'title');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `eb_chat_auto_reply` ADD COLUMN `title` varchar(255) NOT NULL DEFAULT '''' COMMENT ''卡片展示的问题'' AFTER `keyword`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eb_chat_auto_reply' AND COLUMN_NAME = 'is_faq');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `eb_chat_auto_reply` ADD COLUMN `is_faq` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''是否展示在常见问题卡片'' AFTER `title`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eb_chat_auto_reply' AND INDEX_NAME = 'idx_faq');
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `eb_chat_auto_reply` ADD INDEX `idx_faq` (`tenant_id`, `appid`, `is_faq`, `sort`)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 后台「常见问题」菜单。挂在客服管理(165)下，与历史对话同级，租户专属。
-- 既有自动回复的控制器/路由早就存在却无菜单，这里一并给出入口。
INSERT INTO `eb_system_menus`
(`id`,`pid`,`icon`,`menu_name`,`module`,`controller`,`action`,`api_url`,`methods`,`params`,`sort`,`is_show`,`is_show_path`,`is_tenant`,`is_platform`,`access`,`menu_path`,`path`,`auth_type`,`header`,`is_header`,`unique_auth`,`is_del`)
VALUES
(1330,165,'','常见问题','admin','','','','','[]',24,1,0,1,0,1,'/admin/chat/faq','165',1,'kefu',0,'chat-faq',0),
(1331,1330,'','常见问题列表','admin','','','api/admin/chat/faq','GET','[]',0,0,0,1,0,1,'','165/1330',2,'',0,'',0),
(1332,1330,'','保存常见问题','admin','','','api/admin/chat/faq','POST','[]',0,0,0,1,0,1,'','165/1330',2,'',0,'',0),
(1333,1330,'','更新常见问题','admin','','','api/admin/chat/faq/<id>','PUT','[]',0,0,0,1,0,1,'','165/1330',2,'',0,'',0),
(1334,1330,'','删除常见问题','admin','','','api/admin/chat/faq/<id>','DELETE','[]',0,0,0,1,0,1,'','165/1330',2,'',0,'',0),
(1335,1330,'','常见问题排序','admin','','','api/admin/chat/faq/sort','POST','[]',0,0,0,1,0,1,'','165/1330',2,'',0,'',0)
ON DUPLICATE KEY UPDATE
`pid`=VALUES(`pid`),`menu_name`=VALUES(`menu_name`),`api_url`=VALUES(`api_url`),`methods`=VALUES(`methods`),
`sort`=VALUES(`sort`),`is_show`=VALUES(`is_show`),`is_tenant`=VALUES(`is_tenant`),`is_platform`=VALUES(`is_platform`),
`menu_path`=VALUES(`menu_path`),`path`=VALUES(`path`),`auth_type`=VALUES(`auth_type`),`header`=VALUES(`header`),
`unique_auth`=VALUES(`unique_auth`),`is_del`=VALUES(`is_del`);

-- 已有角色补权限，否则升级后老角色看不到新菜单。
-- FIND_IN_SET 判重使脚本可重复执行；level=0 的超管不走角色校验，无需处理。
UPDATE `eb_system_role`
SET `rules` = CONCAT(`rules`, ',1330,1331,1332,1333,1334,1335')
WHERE `rules` <> '' AND FIND_IN_SET('1330', `rules`) = 0;
