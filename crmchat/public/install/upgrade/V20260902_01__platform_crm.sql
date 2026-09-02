-- 版本：V20260902_01
-- 内容：平台销售线索（CRM）与官网合作意向表单
-- 依赖：eb_system_menus 需已有 is_platform 列（V20260829 多租户改造）
--
-- 官网表单、客服会话、手工录入的潜在客户在此沉淀，按阶段推进直至开通租户。
-- 线索属平台自身数据，不归任何租户，模型中 tenantScoped=false。

CREATE TABLE IF NOT EXISTS `eb_platform_lead` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '联系人',
  `company` varchar(100) NOT NULL DEFAULT '' COMMENT '公司名称',
  `phone` varchar(30) NOT NULL DEFAULT '' COMMENT '联系电话',
  `email` varchar(100) NOT NULL DEFAULT '' COMMENT '邮箱',
  `scale` varchar(30) NOT NULL DEFAULT '' COMMENT '团队规模',
  `intent_plan` varchar(50) NOT NULL DEFAULT '' COMMENT '意向套餐',
  `content` varchar(1000) NOT NULL DEFAULT '' COMMENT '需求描述',
  `source` varchar(20) NOT NULL DEFAULT 'website' COMMENT '来源website官网表单/chat客服会话/manual手工录入',
  `stage` tinyint(1) NOT NULL DEFAULT 1 COMMENT '阶段1新线索2已联系3意向确认4已成交5已关闭',
  `owner_id` int NOT NULL DEFAULT 0 COMMENT '跟进人管理员ID',
  `tenant_id` int NOT NULL DEFAULT 0 COMMENT '成交后关联的租户ID',
  `next_follow_time` int NOT NULL DEFAULT 0 COMMENT '下次跟进时间',
  `last_follow_time` int NOT NULL DEFAULT 0 COMMENT '最近跟进时间',
  `chat_user_id` int NOT NULL DEFAULT 0 COMMENT '来源会话的访客ID,用于去重',
  `from_kefu` varchar(50) NOT NULL DEFAULT '' COMMENT '转线索的客服名称,客服表与管理员表无关联故只存名称',
  `is_delete` tinyint(1) NOT NULL DEFAULT 0,
  `create_time` int NOT NULL DEFAULT 0,
  `update_time` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_stage` (`stage`),
  KEY `idx_owner` (`owner_id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_chat_user` (`chat_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='平台销售线索';

CREATE TABLE IF NOT EXISTS `eb_platform_lead_follow` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lead_id` int NOT NULL DEFAULT 0 COMMENT '线索ID',
  `admin_id` int NOT NULL DEFAULT 0 COMMENT '操作人',
  `admin_name` varchar(50) NOT NULL DEFAULT '' COMMENT '操作人名称,冗余避免列表联查',
  `content` varchar(1000) NOT NULL DEFAULT '' COMMENT '跟进内容',
  `stage_from` tinyint(1) NOT NULL DEFAULT 0 COMMENT '变更前阶段,0表示本次未变更阶段',
  `stage_to` tinyint(1) NOT NULL DEFAULT 0 COMMENT '变更后阶段',
  `create_time` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_lead` (`lead_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='线索跟进记录';

-- 灰度期间已建过表的环境（dev）缺后两列，补齐；information_schema 判断使脚本可重复执行
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eb_platform_lead' AND COLUMN_NAME = 'chat_user_id');
SET @s := IF(@c = 0, 'ALTER TABLE `eb_platform_lead` ADD COLUMN `chat_user_id` int NOT NULL DEFAULT 0 COMMENT ''来源会话的访客ID,用于去重'' AFTER `last_follow_time`, ADD INDEX `idx_chat_user` (`chat_user_id`)', 'DO 0');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eb_platform_lead' AND COLUMN_NAME = 'from_kefu');
SET @s := IF(@c = 0, 'ALTER TABLE `eb_platform_lead` ADD COLUMN `from_kefu` varchar(50) NOT NULL DEFAULT '''' COMMENT ''转线索的客服名称'' AFTER `chat_user_id`', 'DO 0');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

-- 菜单挂在租户管理下，仅平台端可见；ON DUPLICATE 使重复执行只覆盖不报错
INSERT INTO `eb_system_menus` (`id`,`pid`,`menu_name`,`menu_path`,`api_url`,`methods`,`is_show`,`is_tenant`,`is_platform`,`auth_type`,`is_del`,`is_show_path`,`sort`,`params`,`header`,`path`,`unique_auth`,`icon`,`module`,`controller`,`action`,`access`) VALUES
(1310,1200,'销售线索','/admin/platform/lead','','',1,0,1,1,0,0,90,'[]','','1200','platform-lead','','admin','','',0),
(1311,1310,'线索列表','','api/admin/setting/lead','GET',0,0,1,2,0,0,0,'[]','','1200/1310','','','admin','','',0),
(1312,1310,'线索选项','','api/admin/setting/lead/options','GET',0,0,1,2,0,0,0,'[]','','1200/1310','','','admin','','',0),
(1313,1310,'线索详情','','api/admin/setting/lead/<id>','GET',0,0,1,2,0,0,0,'[]','','1200/1310','','','admin','','',0),
(1314,1310,'录入线索','','api/admin/setting/lead','POST',0,0,1,2,0,0,0,'[]','','1200/1310','','','admin','','',0),
(1315,1310,'记录跟进','','api/admin/setting/lead/follow/<id>','POST',0,0,1,2,0,0,0,'[]','','1200/1310','','','admin','','',0),
(1316,1310,'转派线索','','api/admin/setting/lead/assign/<id>','POST',0,0,1,2,0,0,0,'[]','','1200/1310','','','admin','','',0),
(1317,1310,'关联租户','','api/admin/setting/lead/link/<id>','POST',0,0,1,2,0,0,0,'[]','','1200/1310','','','admin','','',0),
(1318,1310,'删除线索','','api/admin/setting/lead/<id>','DELETE',0,0,1,2,0,0,0,'[]','','1200/1310','','','admin','','',0)
ON DUPLICATE KEY UPDATE `pid`=VALUES(`pid`),`menu_name`=VALUES(`menu_name`),`menu_path`=VALUES(`menu_path`),`api_url`=VALUES(`api_url`),`methods`=VALUES(`methods`),`is_show`=VALUES(`is_show`),`is_tenant`=VALUES(`is_tenant`),`is_platform`=VALUES(`is_platform`),`auth_type`=VALUES(`auth_type`),`is_show_path`=VALUES(`is_show_path`),`sort`=VALUES(`sort`),`header`=VALUES(`header`),`path`=VALUES(`path`),`unique_auth`=VALUES(`unique_auth`);
