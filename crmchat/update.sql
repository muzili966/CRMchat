
ALTER TABLE `eb_chat_service` ADD `is_app` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '是否为APP登陆' AFTER `client_id`;

ALTER TABLE `eb_chat_user` ADD `remark_nickname` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '备注昵称' AFTER `nickname`;

CREATE TABLE `eb_chat_auto_reply` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `keyword` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '关键词',
  `content` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT '内容',
  `user_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属用户',
  `appid` varchar(64) COLLATE utf8_unicode_ci NOT NULL COMMENT '所属appid',
  `sort` INT(10) NOT NULL DEFAULT '0' COMMENT '排序,越靠前,越是能被自会回复到',
  `add_time` int(10) NOT NULL DEFAULT '0' COMMENT '添加时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='自动回复';

ALTER TABLE `eb_chat_service` ADD `auto_reply` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '自动回复' AFTER `client_id`;
ALTER TABLE `eb_chat_service` ADD `is_backstage` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '1=前台运行;0=后台运行' AFTER `auto_reply`;
ALTER TABLE `eb_chat_service` ADD `welcome_words` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '欢迎语' AFTER `auto_reply`;
ALTER TABLE `eb_chat_service_dialogue_record` ADD `is_send` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '是否发送' AFTER `other`;

-- 2021/09/17新增
ALTER TABLE `eb_chat_service` ADD `update_time` int(10) NOT NULL DEFAULT '0' COMMENT '更新时间' AFTER `welcome_words`;
ALTER TABLE `eb_chat_service` ADD `ip` VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'ip' AFTER `welcome_words`;


-- 2021/09/23新增
ALTER TABLE `eb_auxiliary` ADD `appid` VARCHAR(35) NOT NULL AFTER `binding_id`;

-- 2021/09/29新增
ALTER TABLE `eb_chat_user` ADD `online` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '1=在线,0=离线' AFTER `sex`;
ALTER TABLE `eb_chat_user` ADD `version` varchar(30) NOT NULL DEFAULT '' COMMENT '版本号' AFTER `online`;

-- 2021/09/30新增
ALTER TABLE `eb_chat_service_record` ADD INDEX(`online`);
UPDATE `eb_chat_user` SET `online` = 1 WHERE `id` = (SELECT `user_id` FROM `eb_chat_service_record` WHERE `online` = 1);


-- 2021/10/21新增
ALTER TABLE `eb_chat_service_record` ADD `delete_time` INT(10) NULL DEFAULT NULL COMMENT '删除字段' AFTER `update_time`;

-- 2026/08/29 多租户改造·阶段一：租户实体与两级管理员
CREATE TABLE IF NOT EXISTS `eb_tenant` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '租户名称',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态0=禁用,1=正常',
  `plan` varchar(32) NOT NULL DEFAULT '' COMMENT '套餐标识(预留)',
  `expire_time` int(10) NOT NULL DEFAULT '0' COMMENT '到期时间0=永久(预留)',
  `domain` varchar(100) NOT NULL DEFAULT '' COMMENT '独立域名(预留)',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `is_delete` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否删除',
  `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(10) NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='租户表';

INSERT INTO `eb_tenant` (`id`, `name`, `status`, `create_time`, `update_time`) VALUES (1, '默认租户', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

ALTER TABLE `eb_application` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
UPDATE `eb_application` SET `tenant_id` = 1 WHERE `tenant_id` = 0;

ALTER TABLE `eb_system_admin` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID,0=平台' AFTER `id`, ADD `admin_type` tinyint(1) NOT NULL DEFAULT '2' COMMENT '管理员类型1=平台超管,2=租户管理员' AFTER `tenant_id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
UPDATE `eb_system_admin` SET `admin_type` = 1, `tenant_id` = 0 WHERE `level` = 0;
UPDATE `eb_system_admin` SET `admin_type` = 2, `tenant_id` = 1 WHERE `level` <> 0;

-- 2026/08/29 多租户改造·阶段三：业务表补充租户维度
ALTER TABLE `eb_chat_user` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_chat_service` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_chat_service_dialogue_record` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_chat_service_record` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_chat_service_feedback` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_chat_auto_reply` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_auxiliary` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_chat_service_speechcraft` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_chat_user_group` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_chat_user_label` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_chat_user_label_assist` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_chat_complain` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_category` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_system_role` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_system_attachment` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `att_id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_system_attachment_category` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_system_log` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID,0=平台' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_system_config` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID,0=平台默认' AFTER `id`, ADD INDEX `idx_tenant_menu` (`tenant_id`, `menu_name`);

-- 存量数据回填：带appid的表按应用归属租户，无法匹配及无appid的表归入默认租户；system_config/system_log 历史数据保留为平台层(0)
UPDATE `eb_chat_user` t INNER JOIN `eb_application` a ON t.appid = a.appid SET t.tenant_id = a.tenant_id WHERE t.tenant_id = 0;
UPDATE `eb_chat_service` t INNER JOIN `eb_application` a ON t.appid = a.appid SET t.tenant_id = a.tenant_id WHERE t.tenant_id = 0;
UPDATE `eb_chat_service_dialogue_record` t INNER JOIN `eb_application` a ON t.appid = a.appid SET t.tenant_id = a.tenant_id WHERE t.tenant_id = 0;
UPDATE `eb_chat_service_record` t INNER JOIN `eb_application` a ON t.appid = a.appid SET t.tenant_id = a.tenant_id WHERE t.tenant_id = 0;
UPDATE `eb_chat_service_feedback` t INNER JOIN `eb_application` a ON t.appid = a.appid SET t.tenant_id = a.tenant_id WHERE t.tenant_id = 0;
UPDATE `eb_chat_auto_reply` t INNER JOIN `eb_application` a ON t.appid = a.appid SET t.tenant_id = a.tenant_id WHERE t.tenant_id = 0;
UPDATE `eb_auxiliary` t INNER JOIN `eb_application` a ON t.appid = a.appid SET t.tenant_id = a.tenant_id WHERE t.tenant_id = 0;
UPDATE `eb_chat_user` SET `tenant_id` = 1 WHERE `tenant_id` = 0;
UPDATE `eb_chat_service` SET `tenant_id` = 1 WHERE `tenant_id` = 0;
UPDATE `eb_chat_service_dialogue_record` SET `tenant_id` = 1 WHERE `tenant_id` = 0;
UPDATE `eb_chat_service_record` SET `tenant_id` = 1 WHERE `tenant_id` = 0;
UPDATE `eb_chat_service_feedback` SET `tenant_id` = 1 WHERE `tenant_id` = 0;
UPDATE `eb_chat_auto_reply` SET `tenant_id` = 1 WHERE `tenant_id` = 0;
UPDATE `eb_auxiliary` SET `tenant_id` = 1 WHERE `tenant_id` = 0;
UPDATE `eb_chat_service_speechcraft` SET `tenant_id` = 1 WHERE `tenant_id` = 0;
UPDATE `eb_chat_user_group` SET `tenant_id` = 1 WHERE `tenant_id` = 0;
UPDATE `eb_chat_user_label` SET `tenant_id` = 1 WHERE `tenant_id` = 0;
UPDATE `eb_chat_user_label_assist` SET `tenant_id` = 1 WHERE `tenant_id` = 0;
UPDATE `eb_chat_complain` SET `tenant_id` = 1 WHERE `tenant_id` = 0;
UPDATE `eb_category` SET `tenant_id` = 1 WHERE `tenant_id` = 0;
UPDATE `eb_system_role` SET `tenant_id` = 1 WHERE `tenant_id` = 0;
UPDATE `eb_system_attachment` SET `tenant_id` = 1 WHERE `tenant_id` = 0;
UPDATE `eb_system_attachment_category` SET `tenant_id` = 1 WHERE `tenant_id` = 0;

-- 2026/08/29 多租户改造·阶段五（可选DDL，需DBA预检后手动执行）
-- 客服账号租户内唯一索引。应用层已在租户上下文内做唯一性校验，此索引为数据库级兜底。
-- 预检1（结果须为空）：SELECT tenant_id, account, COUNT(*) AS c FROM eb_chat_service WHERE account <> '' GROUP BY tenant_id, account HAVING c > 1;
-- 预检2（存在空账号行时不可加此索引）：SELECT COUNT(*) FROM eb_chat_service WHERE account = '';
-- 预检通过后执行：ALTER TABLE `eb_chat_service` ADD UNIQUE KEY `uk_tenant_account` (`tenant_id`, `account`);

-- 2026/08/30 多租户改造：租户管理菜单与接口权限（页面由前端仓库配合开发）
-- 显示菜单：平台超管(level=0)可见全部；租户管理员按角色授权可见（对账/发票/通知等自助菜单）
INSERT INTO `eb_system_menus` (`id`, `pid`, `icon`, `menu_name`, `module`, `controller`, `action`, `api_url`, `methods`, `params`, `sort`, `is_show`, `is_show_path`, `access`, `menu_path`, `path`, `auth_type`, `header`, `is_header`, `unique_auth`, `is_del`) VALUES
(1200, 0, 'md-albums', '租户管理', 'admin', '', '', '', '', '[]', 3, 1, 0, 1, '/admin/tenant', '', 1, 'tenant', 1, 'admin-tenant', 0),
(1201, 1200, '', '租户列表', 'admin', '', '', '', '', '[]', 10, 1, 0, 1, '/admin/tenant/list', '1200', 1, 'tenant', 1, 'tenant-list', 0),
(1202, 1200, '', '套餐管理', 'admin', '', '', '', '', '[]', 9, 1, 0, 1, '/admin/tenant/plan', '1200', 1, 'tenant', 1, 'tenant-plan', 0),
(1203, 1200, '', '订购对账', 'admin', '', '', '', '', '[]', 8, 1, 0, 1, '/admin/tenant/orders', '1200', 1, 'tenant', 1, 'tenant-orders', 0),
(1204, 1200, '', '发票管理', 'admin', '', '', '', '', '[]', 7, 1, 0, 1, '/admin/tenant/invoice', '1200', 1, 'tenant', 1, 'tenant-invoice', 0),
(1205, 1200, '', '租户通知', 'admin', '', '', '', '', '[]', 6, 1, 0, 1, '/admin/tenant/notice', '1200', 1, 'tenant', 1, 'tenant-notice', 0),
(1210, 1201, '', '租户列表接口', 'admin', '', '', 'api/admin/setting/tenant', 'GET', '[]', 0, 0, 0, 1, '', '1200/1201', 2, '', 0, '', 0),
(1211, 1201, '', '创建租户', 'admin', '', '', 'api/admin/setting/tenant', 'POST', '[]', 0, 0, 0, 1, '', '1200/1201', 2, '', 0, '', 0),
(1212, 1201, '', '修改租户', 'admin', '', '', 'api/admin/setting/tenant/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '1200/1201', 2, '', 0, '', 0),
(1213, 1201, '', '启停租户', 'admin', '', '', 'api/admin/setting/tenant/set_status/<id>/<status>', 'PUT', '[]', 0, 0, 0, 1, '', '1200/1201', 2, '', 0, '', 0),
(1214, 1201, '', '创建租户管理员', 'admin', '', '', 'api/admin/setting/tenant/admin', 'POST', '[]', 0, 0, 0, 1, '', '1200/1201', 2, '', 0, '', 0),
(1215, 1202, '', '开通续费套餐', 'admin', '', '', 'api/admin/setting/tenant/subscribe', 'POST', '[]', 0, 0, 0, 1, '', '1200/1202', 2, '', 0, '', 0),
(1216, 1202, '', '套餐列表接口', 'admin', '', '', 'api/admin/setting/tenant/plan', 'GET', '[]', 0, 0, 0, 1, '', '1200/1202', 2, '', 0, '', 0),
(1217, 1202, '', '在售套餐下拉', 'admin', '', '', 'api/admin/setting/tenant/plan/all', 'GET', '[]', 0, 0, 0, 1, '', '1200/1202', 2, '', 0, '', 0),
(1218, 1202, '', '创建套餐', 'admin', '', '', 'api/admin/setting/tenant/plan', 'POST', '[]', 0, 0, 0, 1, '', '1200/1202', 2, '', 0, '', 0),
(1219, 1202, '', '修改套餐', 'admin', '', '', 'api/admin/setting/tenant/plan/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '1200/1202', 2, '', 0, '', 0),
(1220, 1202, '', '上架停售套餐', 'admin', '', '', 'api/admin/setting/tenant/plan/set_status/<id>/<status>', 'PUT', '[]', 0, 0, 0, 1, '', '1200/1202', 2, '', 0, '', 0),
(1221, 1202, '', '删除套餐', 'admin', '', '', 'api/admin/setting/tenant/plan/<id>', 'DELETE', '[]', 0, 0, 0, 1, '', '1200/1202', 2, '', 0, '', 0),
(1222, 1203, '', '订购对账列表', 'admin', '', '', 'api/admin/setting/tenant/orders', 'GET', '[]', 0, 0, 0, 1, '', '1200/1203', 2, '', 0, '', 0),
(1223, 1203, '', '对账导出', 'admin', '', '', 'api/admin/setting/tenant/orders/export', 'GET', '[]', 0, 0, 0, 1, '', '1200/1203', 2, '', 0, '', 0),
(1224, 1204, '', '发票列表', 'admin', '', '', 'api/admin/setting/tenant/invoice', 'GET', '[]', 0, 0, 0, 1, '', '1200/1204', 2, '', 0, '', 0),
(1225, 1204, '', '申请开票', 'admin', '', '', 'api/admin/setting/tenant/invoice', 'POST', '[]', 0, 0, 0, 1, '', '1200/1204', 2, '', 0, '', 0),
(1226, 1204, '', '开具驳回发票', 'admin', '', '', 'api/admin/setting/tenant/invoice/audit/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '1200/1204', 2, '', 0, '', 0),
(1227, 1205, '', '租户通知列表', 'admin', '', '', 'api/admin/setting/tenant/notice', 'GET', '[]', 0, 0, 0, 1, '', '1200/1205', 2, '', 0, '', 0),
(1228, 1205, '', '通知已读', 'admin', '', '', 'api/admin/setting/tenant/notice/read/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '1200/1205', 2, '', 0, '', 0);

-- 2026/08/30 切换租户视角权限点：平台侧level>0人员需角色授予此权限才能切租户视角（同时是租户下拉接口）
INSERT INTO `eb_system_menus` (`id`, `pid`, `icon`, `menu_name`, `module`, `controller`, `action`, `api_url`, `methods`, `params`, `sort`, `is_show`, `is_show_path`, `access`, `menu_path`, `path`, `auth_type`, `header`, `is_header`, `unique_auth`, `is_del`) VALUES
(1229, 1200, '', '切换租户视角', 'admin', '', '', 'api/admin/setting/tenant/view_switch', 'GET', '[]', 0, 0, 0, 1, '', '1200', 2, '', 0, '', 0);

-- 2026/08/29 多租户改造·阶段六：套餐计费体系
CREATE TABLE IF NOT EXISTS `eb_tenant_plan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '套餐名称',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '月价格(元)',
  `app_limit` int(10) NOT NULL DEFAULT '0' COMMENT '接入应用数上限,0=不限',
  `seat_limit` int(10) NOT NULL DEFAULT '0' COMMENT '客服坐席数上限,0=不限',
  `daily_msg_limit` int(10) NOT NULL DEFAULT '0' COMMENT '每日消息条数上限,0=不限',
  `storage_limit_mb` int(10) NOT NULL DEFAULT '0' COMMENT '附件存储上限(MB),0=不限',
  `record_keep_days` int(10) NOT NULL DEFAULT '0' COMMENT '聊天记录保留天数,0=永久',
  `auto_reply` tinyint(1) NOT NULL DEFAULT '0' COMMENT '关键词自动回复',
  `brand_custom` tinyint(1) NOT NULL DEFAULT '0' COMMENT '品牌自定义(站点名/LOGO/头像)',
  `data_export` tinyint(1) NOT NULL DEFAULT '0' COMMENT '数据导出',
  `app_push` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'APP离线推送',
  `sort` int(10) NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0=停售,1=在售',
  `is_delete` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否删除',
  `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(10) NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='租户套餐表';

INSERT INTO `eb_tenant_plan` (`id`, `name`, `price`, `app_limit`, `seat_limit`, `daily_msg_limit`, `storage_limit_mb`, `record_keep_days`, `auto_reply`, `brand_custom`, `data_export`, `app_push`, `sort`, `status`, `create_time`, `update_time`) VALUES
(1, '免费版', 0.00, 1, 2, 500, 200, 7, 0, 0, 0, 0, 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '体验版', 500.00, 2, 5, 5000, 2048, 30, 1, 0, 0, 1, 2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(3, '标准版', 1000.00, 5, 20, 20000, 10240, 180, 1, 1, 1, 1, 3, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(4, '旗舰版', 2000.00, 0, 100, 0, 51200, 0, 1, 1, 1, 1, 4, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

CREATE TABLE IF NOT EXISTS `eb_tenant_plan_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID',
  `order_no` varchar(32) NOT NULL DEFAULT '' COMMENT '对账单号',
  `plan_id` int(10) NOT NULL DEFAULT '0' COMMENT '套餐ID',
  `plan_name` varchar(50) NOT NULL DEFAULT '' COMMENT '套餐名称快照',
  `plan_snapshot` text COMMENT '套餐配额快照json',
  `months` int(10) NOT NULL DEFAULT '1' COMMENT '订购月数',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '金额(元)',
  `pay_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=后台开通,2=线下转账',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=已生效,2=已作废',
  `expire_before` int(10) NOT NULL DEFAULT '0' COMMENT '订购前到期时间',
  `expire_after` int(10) NOT NULL DEFAULT '0' COMMENT '订购后到期时间',
  `admin_id` int(10) NOT NULL DEFAULT '0' COMMENT '操作管理员ID',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_order_no` (`order_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='租户套餐订购对账表';

CREATE TABLE IF NOT EXISTS `eb_tenant_invoice` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID',
  `order_id` int(10) NOT NULL DEFAULT '0' COMMENT '关联订购单ID',
  `order_no` varchar(32) NOT NULL DEFAULT '' COMMENT '关联对账单号',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '发票抬头',
  `tax_no` varchar(50) NOT NULL DEFAULT '' COMMENT '税号',
  `type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=普票,2=专票',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '开票金额(元)',
  `email` varchar(100) NOT NULL DEFAULT '' COMMENT '接收邮箱',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=待开具,1=已开具,2=已驳回',
  `invoice_no` varchar(50) NOT NULL DEFAULT '' COMMENT '发票号码',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注/驳回原因',
  `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '申请时间',
  `update_time` int(10) NOT NULL DEFAULT '0' COMMENT '处理时间',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='租户发票记录表';

CREATE TABLE IF NOT EXISTS `eb_tenant_notice` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID',
  `type` varchar(20) NOT NULL DEFAULT '' COMMENT '通知类型expire_warn=即将到期,expired=已到期,renew=续费成功',
  `content` varchar(255) NOT NULL DEFAULT '' COMMENT '通知内容',
  `is_read` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已读',
  `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='租户通知表';

ALTER TABLE `eb_tenant` ADD `plan_id` int(10) NOT NULL DEFAULT '0' COMMENT '当前套餐ID,0=未订购(不限制)' AFTER `plan`;
-- 存量租户按旗舰版兜底，避免升级后被配额限制影响现网
UPDATE `eb_tenant` SET `plan_id` = 4 WHERE `plan_id` = 0;

-- ============ RBAC多租户适配 + 通知管理通用化（2026-08-30） ============
-- 菜单增加租户侧可用标记：租户默认角色的权限全集 = is_tenant=1 的菜单
ALTER TABLE `eb_system_menus` ADD `is_tenant` tinyint(1) NOT NULL DEFAULT '1' COMMENT '租户侧可用1=可用,0=平台专属' AFTER `is_show_path`;
-- 维护管理树与租户管理树为平台专属
UPDATE `eb_system_menus` SET `is_tenant` = 0 WHERE `id` IN (25, 1200) OR `path` = '25' OR `path` LIKE '25/%' OR `path` = '1200' OR `path` LIKE '1200/%';
-- 通知管理独立为顶级通用菜单（平台看全部/发送公告，租户看自己的）
UPDATE `eb_system_menus` SET `pid` = 0, `icon` = 'md-notifications', `menu_name` = '通知管理', `path` = '', `header` = 'notice', `is_header` = 1, `is_tenant` = 1, `sort` = 1 WHERE `id` = 1205;
UPDATE `eb_system_menus` SET `path` = '1205', `is_tenant` = 1, `menu_name` = '通知列表' WHERE `id` = 1227;
UPDATE `eb_system_menus` SET `path` = '1205', `is_tenant` = 1 WHERE `id` = 1228;
-- 发送通知接口权限（平台专属）
INSERT INTO `eb_system_menus` (`id`, `pid`, `icon`, `menu_name`, `module`, `controller`, `action`, `api_url`, `methods`, `params`, `sort`, `is_show`, `is_show_path`, `access`, `menu_path`, `path`, `auth_type`, `header`, `is_header`, `unique_auth`, `is_del`, `is_tenant`) VALUES
(1230, 1205, '', '发送通知', 'admin', '', '', 'api/admin/setting/tenant/notice', 'POST', '[]', 0, 0, 0, 1, '', '1205', 2, '', 0, '', 0, 0);

-- ============ 访客接入安全加固 + 应用管理菜单可见性（2026-08-30） ============
-- 应用接入模式：新应用默认签名模式（携带uid接入须验签sign=md5(appid.uid.timestamp.app_secret)），存量应用保持兼容不掉线
ALTER TABLE `eb_application` ADD `auth_mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '接入模式0=标准(默认),1=签名(需服务端下发签名)' AFTER `token_md5`;
UPDATE `eb_application` SET `auth_mode` = 0;
-- "代码获取"实为应用管理入口，改名使其可发现
UPDATE `eb_system_menus` SET `menu_name` = '应用管理' WHERE `id` = 1011;

-- ============ 租户端我的订阅（2026-08-30） ============
-- 顶级菜单：我的订阅（租户查看套餐/订单/发票；平台超管经租户视角亦可查看）
-- header 原定义 varchar(10) 装不下 'subscription'（12字符）：
-- 严格模式下报 1406 中断导入，非严格模式则静默截断成 'subscripti'，顶部菜单匹配不上
ALTER TABLE `eb_system_menus` MODIFY `header` varchar(32) NOT NULL DEFAULT '' COMMENT '顶部菜单标示';
INSERT INTO `eb_system_menus` (`id`, `pid`, `icon`, `menu_name`, `module`, `controller`, `action`, `api_url`, `methods`, `params`, `sort`, `is_show`, `is_show_path`, `access`, `menu_path`, `path`, `auth_type`, `header`, `is_header`, `unique_auth`, `is_del`, `is_tenant`) VALUES
(1240, 0, 'md-card', '我的订阅', 'admin', '', '', '', '', '[]', 0, 1, 0, 1, '/admin/tenant/subscription', '', 1, 'subscription', 1, 'tenant-subscription', 0, 1),
(1241, 1240, '', '我的订阅概览', 'admin', '', '', 'api/admin/setting/tenant/my', 'GET', '[]', 0, 0, 0, 1, '', '1240', 2, '', 0, '', 0, 1),
(1242, 1240, '', '我的订阅订单', 'admin', '', '', 'api/admin/setting/tenant/orders', 'GET', '[]', 0, 0, 0, 1, '', '1240', 2, '', 0, '', 0, 1),
(1243, 1240, '', '我的发票列表', 'admin', '', '', 'api/admin/setting/tenant/invoice', 'GET', '[]', 0, 0, 0, 1, '', '1240', 2, '', 0, '', 0, 1),
(1244, 1240, '', '申请开票', 'admin', '', '', 'api/admin/setting/tenant/invoice', 'POST', '[]', 0, 0, 0, 1, '', '1240', 2, '', 0, '', 0, 1);
-- 存量租户默认角色补充我的订阅权限（新建租户角色由代码自动包含）
UPDATE `eb_system_role` SET `rules` = CONCAT(`rules`, ',1240,1241,1242,1243,1244') WHERE `role_name` = '租户管理员' AND `tenant_id` > 0 AND `rules` NOT LIKE '%1240%';

-- ============ 租户端套餐升级与续订（2026-08-30） ============
INSERT INTO `eb_system_menus` (`id`, `pid`, `icon`, `menu_name`, `module`, `controller`, `action`, `api_url`, `methods`, `params`, `sort`, `is_show`, `is_show_path`, `access`, `menu_path`, `path`, `auth_type`, `header`, `is_header`, `unique_auth`, `is_del`, `is_tenant`) VALUES
(1245, 1240, '', '在售套餐展示', 'admin', '', '', 'api/admin/setting/tenant/plans', 'GET', '[]', 0, 0, 0, 1, '', '1240', 2, '', 0, '', 0, 1);
UPDATE `eb_system_role` SET `rules` = CONCAT(`rules`, ',1245') WHERE `role_name` = '租户管理员' AND `tenant_id` > 0 AND `rules` NOT LIKE '%1245%';

-- ============ AI 智能客服（2026-08-30） ============
-- AI配置：租户级一套（多应用共用）
CREATE TABLE IF NOT EXISTS `eb_ai_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID',
  `enable` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否启用0=否,1=是',
  `mode` varchar(20) NOT NULL DEFAULT 'standby' COMMENT '接待模式standby=值守,ai_first=AI优先',
  `greeting` varchar(500) NOT NULL DEFAULT '' COMMENT 'AI欢迎语',
  `system_prompt` text COMMENT '身份设定与业务介绍',
  `faq` text COMMENT 'FAQ问答对json',
  `transfer_keywords` varchar(500) NOT NULL DEFAULT '人工,转人工,客服,投诉' COMMENT '转人工关键词,逗号分隔',
  `model` varchar(64) NOT NULL DEFAULT '' COMMENT '模型覆盖,空=用平台默认',
  `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(10) NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI客服配置表';

-- AI调用用量流水：平台级（不受租户隔离），供成本归集与对账
CREATE TABLE IF NOT EXISTS `eb_ai_usage_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '租户ID',
  `appid` varchar(50) NOT NULL DEFAULT '' COMMENT '应用ID',
  `user_id` int(10) NOT NULL DEFAULT '0' COMMENT '访客chat_user id',
  `model` varchar(64) NOT NULL DEFAULT '' COMMENT '实际调用模型',
  `prompt_tokens` int(10) NOT NULL DEFAULT '0' COMMENT '输入token',
  `completion_tokens` int(10) NOT NULL DEFAULT '0' COMMENT '输出token',
  `duration_ms` int(10) NOT NULL DEFAULT '0' COMMENT '耗时毫秒',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=成功,2=失败,3=超时',
  `error` varchar(255) NOT NULL DEFAULT '' COMMENT '失败原因',
  `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '调用时间',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_time` (`tenant_id`, `create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI调用用量流水表';

-- 虚拟AI坐席标记：分配/配额/转接/登录/后台管理五处据此排除
ALTER TABLE `eb_chat_service` ADD `is_ai` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否AI虚拟坐席0=否,1=是' AFTER `auto_reply`;
-- 消息来源：为质检与统计提供依据
ALTER TABLE `eb_chat_service_dialogue_record` ADD `source` tinyint(1) NOT NULL DEFAULT '0' COMMENT '来源0=人工,1=关键词,2=AI正文,3=AI兜底,4=超限降级' AFTER `msn_type`;
-- 套餐AI能力：功能开关+日回复配额
ALTER TABLE `eb_tenant_plan` ADD `ai_reply` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'AI智能客服0=关闭,1=开启' AFTER `app_push`, ADD `daily_ai_limit` int(10) NOT NULL DEFAULT '0' COMMENT 'AI日回复上限,0=不限' AFTER `daily_msg_limit`;
UPDATE `eb_tenant_plan` SET `ai_reply` = 1, `daily_ai_limit` = 200 WHERE `name` = '标准版';
UPDATE `eb_tenant_plan` SET `ai_reply` = 1, `daily_ai_limit` = 2000 WHERE `name` = '旗舰版';

-- 菜单：租户端AI客服设置（is_tenant=1）
INSERT INTO `eb_system_menus` (`id`, `pid`, `icon`, `menu_name`, `module`, `controller`, `action`, `api_url`, `methods`, `params`, `sort`, `is_show`, `is_show_path`, `access`, `menu_path`, `path`, `auth_type`, `header`, `is_header`, `unique_auth`, `is_del`, `is_tenant`) VALUES
(1250, 165, '', 'AI客服设置', 'admin', '', '', '', '', '[]', 5, 1, 0, 1, '/admin/chat/ai', '165', 1, 'kefu', 0, 'chat-ai-config', 0, 1),
(1251, 1250, '', 'AI配置详情', 'admin', '', '', 'api/admin/chat/ai', 'GET', '[]', 0, 0, 0, 1, '', '165/1250', 2, '', 0, '', 0, 1),
(1252, 1250, '', '保存AI配置', 'admin', '', '', 'api/admin/chat/ai', 'POST', '[]', 0, 0, 0, 1, '', '165/1250', 2, '', 0, '', 0, 1),
(1253, 1250, '', 'AI用量统计', 'admin', '', '', 'api/admin/chat/ai/usage', 'GET', '[]', 0, 0, 0, 1, '', '165/1250', 2, '', 0, '', 0, 1);
UPDATE `eb_system_role` SET `rules` = CONCAT(`rules`, ',1250,1251,1252,1253') WHERE `role_name` = '租户管理员' AND `tenant_id` > 0 AND `rules` NOT LIKE '%1250%';

-- ============ 客户端装修（2026-08-30） ============
-- 按应用维度：接入代码按应用发放，同租户不同应用可以是不同品牌与站点
CREATE TABLE IF NOT EXISTS `eb_application_theme` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID',
  `appid` varchar(50) NOT NULL DEFAULT '' COMMENT '所属应用',
  `title` varchar(50) NOT NULL DEFAULT '' COMMENT '窗口标题,空=用应用名',
  `logo` varchar(255) NOT NULL DEFAULT '' COMMENT '窗口LOGO',
  `theme_color` varchar(20) NOT NULL DEFAULT '#2d8cf0' COMMENT '主题色',
  `pc_icon` varchar(255) NOT NULL DEFAULT '' COMMENT 'PC悬浮按钮图标',
  `mobile_icon` varchar(255) NOT NULL DEFAULT '' COMMENT '移动端悬浮按钮图标',
  `banners` text COMMENT '轮播广告json[{image,link,sort}]',
  `show_platform_brand` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否显示平台标识1=显示,0=白标(需套餐支持)',
  `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(10) NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_appid` (`appid`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客户端装修配置表';

-- 存量安装增加多主题风格字段；全新安装已包含在public/install/crmeb.sql
ALTER TABLE `eb_application_theme` ADD `theme_style` varchar(20) NOT NULL DEFAULT 'modern' COMMENT '布局风格modern/minimal/soft/midnight' AFTER `theme_color`;
ALTER TABLE `eb_application_theme` ADD `bubble_style` varchar(20) NOT NULL DEFAULT 'soft' COMMENT '气泡风格soft/clean/pill/outline/card' AFTER `theme_style`;

-- 白标能力：去除平台标识，仅旗舰版
ALTER TABLE `eb_tenant_plan` ADD `white_label` tinyint(1) NOT NULL DEFAULT '0' COMMENT '去平台标识0=否,1=是' AFTER `brand_custom`;
UPDATE `eb_tenant_plan` SET `white_label` = 1 WHERE `name` = '旗舰版';

-- 修复存量漏洞：客服广告原以固定key存于缓存表导致所有租户串台，改为按租户拆分key（见CacheServices::kfAdvKey），无需改表结构

-- 菜单：租户端客户端装修
INSERT INTO `eb_system_menus` (`id`, `pid`, `icon`, `menu_name`, `module`, `controller`, `action`, `api_url`, `methods`, `params`, `sort`, `is_show`, `is_show_path`, `access`, `menu_path`, `path`, `auth_type`, `header`, `is_header`, `unique_auth`, `is_del`, `is_tenant`) VALUES
(1260, 165, '', '客户端装修', 'admin', '', '', '', '', '[]', 4, 1, 0, 1, '/admin/chat/theme', '165', 1, 'kefu', 0, 'chat-theme', 0, 1),
(1261, 1260, '', '装修配置详情', 'admin', '', '', 'api/admin/chat/theme', 'GET', '[]', 0, 0, 0, 1, '', '165/1260', 2, '', 0, '', 0, 1),
(1262, 1260, '', '保存装修配置', 'admin', '', '', 'api/admin/chat/theme', 'POST', '[]', 0, 0, 0, 1, '', '165/1260', 2, '', 0, '', 0, 1);
UPDATE `eb_system_role` SET `rules` = CONCAT(`rules`, ',1260,1261,1262') WHERE `role_name` = '租户管理员' AND `tenant_id` > 0 AND `rules` NOT LIKE '%1260%';

-- ============ 权限收口修复（2026-08-30） ============
-- 此前用path列做子树级联失效（存量path大面积为空），改用pid递归重新标注；
-- 平台专属：维护管理(25)/租户管理(1200)/权限规则(21) 整棵子树，但排除管理员自助与附件两棵
UPDATE `eb_system_menus` SET `is_tenant` = 0 WHERE `id` IN (21,47,56,65,111,112,125,126,338,339,340,341,342,343,344,345,346,462,464,465,466,467,468,469,470,471,472,473,474,475,476,477,478,479,480,481,482,483,484,485,486,487,488,489,619,641,1079,1088,1090);
-- 反向误伤修正：附件管理与管理员中心是每个管理员的自助功能，租户必须可用
UPDATE `eb_system_menus` SET `is_tenant` = 1 WHERE `id` IN (1063,1064,1065,1066,1067,1068,1069,1070,1071,1072,1073,1074,1075,1076,1077,1078,1082,1083,1084);
-- 重算存量租户默认角色（ensureDefaultRole仅在角色不存在时创建，不会自动纠正旧角色）
UPDATE `eb_system_role` SET `rules` = (SELECT GROUP_CONCAT(`id`) FROM (SELECT `id` FROM `eb_system_menus` WHERE `is_tenant` = 1 AND `is_del` = 0) t) WHERE `role_name` = '租户管理员' AND `tenant_id` > 0;

-- ============ 替换失效的外链资源（2026-08-30） ============
-- 官方演示站已下线导致全站破图，改用仓库自带的本地资源
UPDATE `eb_system_config` SET `value` = '["\/statics\/avatar\/tourist-1.svg","\/statics\/avatar\/tourist-2.svg","\/statics\/avatar\/tourist-3.svg","\/statics\/avatar\/tourist-4.svg"]' WHERE `menu_name` = 'tourist_avatar';
UPDATE `eb_system_config` SET `value` = '""' WHERE `menu_name` IN ('site_logo','site_logo_square','login_logo') AND `value` LIKE '%crmeb.net%';
UPDATE `eb_application` SET `icon` = '/statics/avatar/tourist-1.svg' WHERE `icon` LIKE '%crmeb.net%';
UPDATE `eb_chat_service` SET `avatar` = '/statics/avatar/tourist-1.svg' WHERE `avatar` LIKE '%crmeb.net%';
UPDATE `eb_chat_user` SET `avatar` = '/statics/avatar/tourist-1.svg' WHERE `avatar` LIKE '%crmeb.net%';

-- ============ 客服页面广告并入客户端装修（2026-08-30） ============
-- 旧「客服页面广告」(菜单913)只有富文本一条能力，装修页已覆盖轮播图，这里补齐富文本以便完全替代
ALTER TABLE `eb_application_theme` ADD `custom_html` text COMMENT '自定义广告HTML' AFTER `banners`;

-- ============ AI密钥改为数据库管理（2026-08-30） ============
-- 密钥加密存储（APP_KEY派生），后台可自助更换；留空时回落环境变量 AI_API_KEY
INSERT INTO `eb_system_config_tab` (`id`, `pid`, `title`, `eng_title`, `status`, `info`, `icon`, `type`, `sort`) VALUES
(90, 0, 'AI客服配置', 'ai_config', 1, 0, 'md-bulb', 0, 0);
INSERT INTO `eb_system_config` (`tenant_id`, `menu_name`, `type`, `input_type`, `config_tab_id`, `parameter`, `upload_type`, `required`, `width`, `high`, `value`, `info`, `desc`, `sort`, `status`) VALUES
(0, 'ai_base_url', 'text', 'input', 90, '', 0, '', 100, 0, '"https://api.deepseek.com"', '接口地址', '大模型服务地址，需兼容OpenAI协议。DeepSeek填 https://api.deepseek.com', 40, 1),
(0, 'ai_api_key', 'text', 'input', 90, '', 0, '', 100, 0, '""', 'API密钥', '加密存储；留空则回落环境变量 AI_API_KEY。保存后仅显示掩码', 30, 1),
(0, 'ai_model', 'text', 'input', 90, '', 0, '', 100, 0, '"deepseek-chat"', '模型名称', '如 deepseek-chat、qwen-plus、glm-4', 20, 1),
(0, 'ai_timeout', 'text', 'input', 90, '', 0, '', 100, 0, '"30"', '超时秒数', '单次调用超时时间，建议20-60秒', 10, 1);

-- ============ 菜单信息架构重排 + 平台默认广告（2026-08-31） ============
SET SESSION group_concat_max_len = 1000000;
-- 客服管理提至一级第二位（原header为空属存量脏数据）；客服列表含"进入工作台"入口，排组内第一
UPDATE `eb_system_menus` SET `sort` = 120, `header` = 'kefu', `is_header` = 1 WHERE `id` = 165;
UPDATE `eb_system_menus` SET `sort` = 50 WHERE `id` = 678;
UPDATE `eb_system_menus` SET `sort` = 40 WHERE `id` = 679;
UPDATE `eb_system_menus` SET `sort` = 30 WHERE `id` = 738;
UPDATE `eb_system_menus` SET `sort` = 20 WHERE `id` = 1250;
UPDATE `eb_system_menus` SET `sort` = 10 WHERE `id` = 1260;
-- eb_chat_user 是访客档案，与管理员账号并列时叫"用户"有歧义
UPDATE `eb_system_menus` SET `menu_name` = '客户管理', `sort` = 100 WHERE `id` = 9;
UPDATE `eb_system_menus` SET `menu_name` = '客户列表' WHERE `id` = 10;
UPDATE `eb_system_menus` SET `menu_name` = '客户分组' WHERE `id` = 227;
UPDATE `eb_system_menus` SET `menu_name` = '客户标签' WHERE `id` = 1008;
-- 应用管理与权限管理自立门户：前者是租户开通后第一件事，后者是独立管理域
UPDATE `eb_system_menus` SET `pid` = 0, `icon` = 'md-apps', `header` = 'app', `is_header` = 1, `sort` = 90, `path` = '' WHERE `id` = 1011;
UPDATE `eb_system_menus` SET `path` = '1011' WHERE `pid` = 1011;
UPDATE `eb_system_menus` SET `pid` = 0, `icon` = 'md-key', `header` = 'auth', `is_header` = 1, `sort` = 70, `menu_name` = '权限管理', `path` = '' WHERE `id` = 14;
UPDATE `eb_system_menus` SET `path` = '14', `header` = 'auth' WHERE `pid` = 14;
-- 账户中心收拢两个单页低频菜单，腾出一级位
INSERT INTO `eb_system_menus` (`id`, `pid`, `icon`, `menu_name`, `module`, `controller`, `action`, `api_url`, `methods`, `params`, `sort`, `is_show`, `is_show_path`, `access`, `menu_path`, `path`, `auth_type`, `header`, `is_header`, `unique_auth`, `is_del`, `is_tenant`) VALUES
(1300, 0, 'md-briefcase', '账户中心', 'admin', '', '', '', '', '[]', 60, 1, 0, 1, '', '', 1, 'account', 1, 'admin-account', 0, 1);
UPDATE `eb_system_menus` SET `pid` = 1300, `header` = 'account', `is_header` = 0, `sort` = 20, `path` = '1300' WHERE `id` = 1240;
UPDATE `eb_system_menus` SET `pid` = 1300, `header` = 'account', `is_header` = 0, `sort` = 10, `path` = '1300', `menu_name` = '消息通知' WHERE `id` = 1205;
-- 附件管理/个人中心迁出平台专属子树：建树从根递归，父级不在权限内会导致整棵丢弃，租户因此传不了图
UPDATE `eb_system_menus` SET `pid` = 12, `header` = 'setting', `path` = '12' WHERE `id` IN (1063, 1082);
UPDATE `eb_system_menus` SET `menu_name` = '个人中心' WHERE `id` = 1082;
-- 客服页面广告能力已并入客户端装修，先对租户隐藏保留平台侧编辑入口以迁移存量内容
UPDATE `eb_system_menus` SET `is_tenant` = 0 WHERE `id` IN (656, 913, 915, 916);
UPDATE `eb_system_menus` SET `sort` = 50 WHERE `id` = 12;
UPDATE `eb_system_menus` SET `sort` = 20 WHERE `id` = 1200;
UPDATE `eb_system_menus` SET `sort` = 10 WHERE `id` = 25;

-- 广告分层：平台配默认广告，付费套餐方可自定义
-- custom_html 已在「客服页面广告并入客户端装修」批次添加，此处不再重复 ADD（否则报 1060）
ALTER TABLE `eb_tenant_plan` ADD `custom_ad` tinyint(1) NOT NULL DEFAULT '0' COMMENT '自定义广告位0=用平台默认,1=可自定义' AFTER `white_label`;
UPDATE `eb_tenant_plan` SET `custom_ad` = 1 WHERE `name` IN ('标准版', '旗舰版');
INSERT INTO `eb_system_config` (`tenant_id`, `menu_name`, `type`, `input_type`, `config_tab_id`, `parameter`, `upload_type`, `required`, `width`, `high`, `value`, `info`, `desc`, `sort`, `status`) VALUES
(0, 'platform_ad_banners', 'upload', 'upload', 69, '', 2, '', 100, 0, '[]', '平台默认广告图', '未开通自定义广告位的租户，其客服窗口展示这些图片（可多张轮播）', 8, 1),
(0, 'platform_ad_html', 'textarea', 'textarea', 69, '', 0, '', 100, 6, '""', '平台默认广告HTML', '选填。未配置广告图时展示此内容，支持HTML（会做安全清洗）', 7, 1);

UPDATE `eb_system_role` SET `rules` = (SELECT GROUP_CONCAT(`id`) FROM (SELECT `id` FROM `eb_system_menus` WHERE `is_tenant` = 1 AND `is_del` = 0) t) WHERE `role_name` = '租户管理员' AND `tenant_id` > 0;

-- ============ 配置分类隔离 + 独立域名（2026-08-31） ============
-- 独立域名：访客入口寻址依据，属高阶套餐能力
ALTER TABLE `eb_tenant_plan` ADD `custom_domain` tinyint(1) NOT NULL DEFAULT '0' COMMENT '独立域名0=否,1=是' AFTER `custom_ad`;
UPDATE `eb_tenant_plan` SET `custom_domain` = 1 WHERE `name` = '旗舰版';
-- 说明：系统设置的分类可见性不再依赖硬编码，改为按"分类下是否含租户可覆盖配置项"动态判定
-- （见 SystemConfigServices::filterTenantTabs），新增配置项时可见性自动跟随，无需再改数据

-- ============ 客服端配置改为「全局默认+应用级覆盖」（2026-08-31） ============
-- 游客头像与客服反馈原为租户级单份，现支持按应用差异化：
-- 应用未单独配置时留空，读取时回落「系统设置-客服端配置」里的租户全局值，
-- 与"平台默认+租户覆盖"是同一套两层模型，单应用租户只需配一次
ALTER TABLE `eb_application_theme`
  ADD `tourist_avatar` text COMMENT '游客头像池,空=继承租户全局' AFTER `custom_html`,
  ADD `service_feedback` varchar(255) NOT NULL DEFAULT '' COMMENT '客服反馈文案,空=继承租户全局' AFTER `tourist_avatar`;

-- ============ 移除失效的「客服页面广告」（2026-08-31） ============
-- 该功能存取的缓存key按租户隔离（kf_adv:{tenant_id}），但菜单是平台专属，
-- 平台管理员的租户上下文为0、只会写入全局key kf_adv，而访客读的是 kf_adv:{自己租户}，
-- 两边永远对不上，等于死配置。广告能力已由「客服端配置-平台默认广告」与「客户端装修」承接。
DELETE FROM `eb_system_menus` WHERE `id` IN (656, 913, 915, 916);
DELETE FROM `eb_cache` WHERE `key` LIKE 'kf_adv%';

-- 配置分类补图标：顶级tab渲染icon，缺失时与其它分类视觉不齐
UPDATE `eb_system_config_tab` SET `icon` = 'md-chatbubbles' WHERE `id` = 69;
UPDATE `eb_system_config_tab` SET `icon` = 'md-bulb' WHERE `id` = 90;

-- ============ QiaLink 洽联品牌默认配置（2026-08-31） ============
-- 仅更新平台默认层，保留其他租户已经设置的独立品牌覆盖
UPDATE `eb_system_config` SET `value` = '"QiaLink 洽联"' WHERE `tenant_id` = 0 AND `menu_name` = 'site_name';
UPDATE `eb_system_config` SET `value` = '"QiaLink 洽联 · 智能客户联络平台"' WHERE `tenant_id` = 0 AND `menu_name` = 'seo_title';
UPDATE `eb_system_config` SET `value` = '"/statics/brand/qialink-logo-horizontal.png"' WHERE `tenant_id` = 0 AND `menu_name` IN ('site_logo', 'login_logo');
UPDATE `eb_system_config` SET `value` = '"/statics/brand/qialink-logo-icon.png"' WHERE `tenant_id` = 0 AND `menu_name` = 'site_logo_square';

-- ============ 补执行：附件管理/个人中心迁出平台专属子树（2026-08-31） ============
-- 该语句原记录于上方"菜单信息架构重排"批次，但当时未在环境库执行，
-- 导致1063/1082仍挂在平台专属的维护管理(25)下。此处补记，确保各环境一致。
UPDATE `eb_system_menus` SET `pid` = 12, `header` = 'setting', `path` = '12' WHERE `id` IN (1063, 1082);

-- ============ 菜单按端隔离 + 订阅能力门禁（2026-08-31） ============
-- 平台账号的租户上下文为0，进「我的订阅」「AI客服设置」「客户端装修」只会得到
-- "请切换到租户视角"的报错，这类页面不应出现在平台端菜单里。
-- 原有 is_tenant 只表达"租户可见"，无法表达"仅租户可见"，故补一列。
ALTER TABLE `eb_system_menus`
  ADD `is_platform` tinyint(1) NOT NULL DEFAULT 1 COMMENT '平台端是否可见,0=仅租户端' AFTER `is_tenant`;

UPDATE `eb_system_menus` SET `is_platform` = 0
  WHERE `id` IN (1240,1241,1242,1243,1244,1245,1250,1251,1252,1253,1260,1261,1262);

-- 订阅能力门禁接口：功能页据此展示内容或升级提示
INSERT INTO `eb_system_menus`
  (`id`,`pid`,`menu_name`,`api_url`,`methods`,`is_show`,`is_tenant`,`is_platform`,`auth_type`,`is_del`,`is_show_path`,`sort`,`params`,`header`,`path`,`unique_auth`,`icon`,`module`,`controller`,`action`,`access`,`menu_path`)
VALUES
  (1301,1240,'订阅能力门禁','api/admin/setting/tenant/features','GET',0,1,0,2,0,0,0,'[]','','12','','','admin','','',0,'');

UPDATE `eb_system_role` SET `rules` = CONCAT(`rules`, ',1301')
  WHERE `id` = 2 AND CONCAT(',',`rules`,',') NOT LIKE '%,1301,%';

-- ============ 租户端订阅订单导出（2026-08-31） ============
-- 租户端原本只有列表接口的权限菜单，没有导出接口的，调用会被鉴权拦下
INSERT INTO `eb_system_menus`
  (`id`,`pid`,`menu_name`,`api_url`,`methods`,`is_show`,`is_tenant`,`is_platform`,`auth_type`,`is_del`,`is_show_path`,`sort`,`params`,`header`,`path`,`unique_auth`,`icon`,`module`,`controller`,`action`,`access`,`menu_path`)
VALUES
  (1302,1240,'我的订阅订单导出','api/admin/setting/tenant/orders/export','GET',0,1,0,2,0,0,0,'[]','','12','','','admin','','',0,'');

UPDATE `eb_system_role` SET `rules` = CONCAT(`rules`, ',1302')
  WHERE `tenant_id` > 0 AND CONCAT(',',`rules`,',') NOT LIKE '%,1302,%';

-- ============ 头像绝对地址修正（2026-08-31） ============
-- 游客头像原先经 link_url() 拼成绝对地址后入库，把当时的站点域名固化了下来；
-- 站点地址或端口一变，历史头像全部失效（浏览器报连接被拒）。代码已改为存相对路径，
-- 存量数据在此归一化。各环境执行前请把域名替换成本环境实际的 site_url。
-- UPDATE `eb_chat_user` SET `avatar` = REPLACE(`avatar`, 'http://你的站点域名/', '/') WHERE `avatar` LIKE 'http://你的站点域名/%';
-- UPDATE `eb_chat_service_record` SET `avatar` = REPLACE(`avatar`, 'http://你的站点域名/', '/') WHERE `avatar` LIKE 'http://你的站点域名/%';

-- ============ 租户视角权限接口（2026-08-31） ============
-- 平台账号切换租户视角后，可用菜单与权限随之变化，前端需重新拉取。
-- 注意：SystemRoleServices 里的短名白名单（menuslist等）实际匹配不上——
-- 比对用的是 api/admin/xxx 完整形式，那是上游遗留的死分支，新接口须走菜单授权。
INSERT INTO `eb_system_menus`
  (`id`,`pid`,`menu_name`,`api_url`,`methods`,`is_show`,`is_tenant`,`is_platform`,`auth_type`,`is_del`,`is_show_path`,`sort`,`params`,`header`,`path`,`unique_auth`,`icon`,`module`,`controller`,`action`,`access`,`menu_path`)
SELECT 1303,`pid`,'获取当前视角权限','api/admin/viewAuth','GET',0,1,1,2,0,0,0,'[]','',`path`,'','','admin','','',0,''
  FROM `eb_system_menus` WHERE `id` = 1042;

UPDATE `eb_system_role` SET `rules` = CONCAT(`rules`, ',1303')
  WHERE CONCAT(',',`rules`,',') NOT LIKE '%,1303,%';

-- ============ 悬浮挂件配置（2026-09-01） ============
-- 悬浮按钮由嵌入脚本在接入方页面渲染，早于聊天窗口打开，
-- 拿不到走websocket下发的装修数据，故新增公开接口 GET /api/mobile/widget?token=xxx。
-- 「PC悬浮图标」「移动端图标」两项此前虽在后台可配但从未生效，一并由该接口下发。
-- 生效优先级：接入方显式传参 > 后台装修 > 脚本内置默认值。
ALTER TABLE `eb_application_theme`
  ADD `show_tip` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否显示悬浮客服按钮' AFTER `mobile_icon`,
  ADD `window_style` varchar(20) NOT NULL DEFAULT 'float' COMMENT '窗口形态float悬浮/center居中' AFTER `show_tip`;

-- ================== 本文件已冻结 ==================
-- 2026-09-02 起改用版本化增量脚本，新变更请加到 public/install/upgrade/，
-- 规则见该目录 README。此处保留历史记录，不再追加。
-- 平台销售线索（CRM）的建表已迁至 V20260902_01__platform_crm.sql。
