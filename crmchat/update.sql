
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
