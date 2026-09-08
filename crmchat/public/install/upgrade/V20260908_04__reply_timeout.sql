-- 版本：V20260908_04
-- 内容：无人应答提醒——等待时长留痕、阈值配置与超时菜单权限
-- 依赖：V20260908_03（会话表）
--
-- 访客发问后客服迟迟不回是真实的服务事故，而原料已经躺在会话行的
-- pending_since 上，此前只用来算响应时长，没人拿它做告警。
--
-- max_pending_cost 记录本次接待中访客最长的一次等待：
-- 客服回复时结算、超时扫描时更新、会话结束时收尾，故它始终反映真实最长等待。

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eb_chat_session' AND COLUMN_NAME = 'max_pending_cost');
SET @s := IF(@c = 0, 'ALTER TABLE `eb_chat_session` ADD COLUMN `max_pending_cost` int(11) NOT NULL DEFAULT 0 COMMENT ''本次接待中访客最长等待秒数'' AFTER `pending_since`', 'DO 0');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

SET @c2 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eb_chat_session' AND COLUMN_NAME = 'alerted_at');
SET @s2 := IF(@c2 = 0, 'ALTER TABLE `eb_chat_session` ADD COLUMN `alerted_at` int(11) NOT NULL DEFAULT 0 COMMENT ''最近一次超时告警时间，用于告警去重'' AFTER `max_pending_cost`', 'DO 0');
PREPARE st2 FROM @s2;
EXECUTE st2;
DEALLOCATE PREPARE st2;

-- 阈值配置：0=不启用；挂在客服配置分类下，租户可覆盖（见 SystemConfigService::TENANT_OVERRIDABLE）
INSERT INTO `eb_system_config`
(`id`,`tenant_id`,`menu_name`,`type`,`input_type`,`config_tab_id`,`parameter`,`upload_type`,`required`,`width`,`high`,`value`,`info`,`desc`,`sort`,`status`)
VALUES
(390,0,'reply_timeout','text','number',69,'',0,'',100,0,'180','无人应答提醒阈值(秒)','访客发问后超过该秒数仍无客服回复即触发提醒；填 0 关闭。AI 接待的会话不计入',0,1)
ON DUPLICATE KEY UPDATE
`menu_name`=VALUES(`menu_name`),`type`=VALUES(`type`),`input_type`=VALUES(`input_type`),
`config_tab_id`=VALUES(`config_tab_id`),`info`=VALUES(`info`),`desc`=VALUES(`desc`),`status`=VALUES(`status`);

INSERT INTO `eb_system_menus`
(`id`,`pid`,`icon`,`menu_name`,`module`,`controller`,`action`,`api_url`,`methods`,`params`,`sort`,`is_show`,`is_show_path`,`is_tenant`,`is_platform`,`access`,`menu_path`,`path`,`auth_type`,`header`,`is_header`,`unique_auth`,`is_del`)
VALUES
(1355,1350,'','超时未应答会话','admin','','','api/admin/chat/performance/pending','GET','[]',0,0,0,1,0,1,'','165/1350',2,'',0,'',0)
ON DUPLICATE KEY UPDATE
`pid`=VALUES(`pid`),`menu_name`=VALUES(`menu_name`),`api_url`=VALUES(`api_url`),`methods`=VALUES(`methods`),
`sort`=VALUES(`sort`),`is_show`=VALUES(`is_show`),`is_tenant`=VALUES(`is_tenant`),`is_platform`=VALUES(`is_platform`),
`menu_path`=VALUES(`menu_path`),`path`=VALUES(`path`),`auth_type`=VALUES(`auth_type`),`header`=VALUES(`header`),
`unique_auth`=VALUES(`unique_auth`),`is_del`=VALUES(`is_del`);

UPDATE `eb_system_role`
SET `rules` = CONCAT(`rules`, ',1355')
WHERE FIND_IN_SET('1350', `rules`) > 0 AND FIND_IN_SET('1355', `rules`) = 0;
