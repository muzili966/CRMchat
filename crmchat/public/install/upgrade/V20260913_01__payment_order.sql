-- 版本：V20260913_01
-- 内容：支付单
--
-- 平台向租户收续费款，原先是客服发一张收款码图片、到账后人工在后台开通，
-- 付款人、金额、开通三件事全靠人对。支付单把「应付多少、付了没有、开通了没有」
-- 落成一条可追溯的记录：链接、二维码、聊天卡片都指向同一张单，到账后自动开通。
--
-- 支付单是平台级数据（平台要跨租户查看与处理），不按租户隔离，付款方记在 tenant_id。

CREATE TABLE IF NOT EXISTS `eb_payment_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '付款租户ID',
  `pay_no` varchar(32) NOT NULL DEFAULT '' COMMENT '支付单号，即渠道侧商户订单号',
  `biz_type` varchar(32) NOT NULL DEFAULT '' COMMENT '业务类型，如 tenant_plan',
  `payload` text COMMENT '下单时锁定的业务参数json',
  `subject` varchar(128) NOT NULL DEFAULT '' COMMENT '支付标题',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '应付金额(元)',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=待支付,1=已支付,2=已关闭,3=已退款',
  `channel` varchar(20) NOT NULL DEFAULT '' COMMENT '支付渠道，付款人选定后写入',
  `scene` varchar(20) NOT NULL DEFAULT '' COMMENT '支付场景',
  `trade_no` varchar(64) NOT NULL DEFAULT '' COMMENT '渠道交易号',
  `paid_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '实收金额(元)',
  `paid_time` int(10) NOT NULL DEFAULT '0' COMMENT '支付时间',
  `expire_time` int(10) NOT NULL DEFAULT '0' COMMENT '过期时间',
  `fulfilled_time` int(10) NOT NULL DEFAULT '0' COMMENT '业务开通时间,0=未开通',
  `abnormal` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1=异常待平台处理',
  `abnormal_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '异常原因',
  `notify_raw` text COMMENT '到账通知原文，对账留痕',
  `source` varchar(10) NOT NULL DEFAULT '' COMMENT '发起方 kefu/admin',
  `creator_id` int(10) NOT NULL DEFAULT '0' COMMENT '发起人ID（客服用户ID或管理员ID）',
  `creator_name` varchar(50) NOT NULL DEFAULT '' COMMENT '发起人名称快照',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` int(10) NOT NULL DEFAULT '0',
  `update_time` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pay_no` (`pay_no`),
  KEY `idx_tenant_status` (`tenant_id`, `status`),
  KEY `idx_status_expire` (`status`, `expire_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='支付单';

-- 订购记录关联支付单：到账开通的幂等靠它判重，对账时也能从订购记录追到交易
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eb_tenant_plan_order' AND COLUMN_NAME = 'pay_no');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `eb_tenant_plan_order` ADD COLUMN `pay_no` varchar(32) NOT NULL DEFAULT '''' COMMENT ''关联支付单号，在线支付开通时写入'' AFTER `order_no`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eb_tenant_plan_order' AND INDEX_NAME = 'idx_pay_no');
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `eb_tenant_plan_order` ADD KEY `idx_pay_no` (`pay_no`)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE `eb_tenant_plan_order` MODIFY COLUMN `pay_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=后台开通,2=线下转账,3=在线支付';

-- 支付单页面挂在租户管理下，仅平台端可见；菜单 ID 从 1390 起，与 1380 留出间隔
INSERT INTO `eb_system_menus`
(`id`,`pid`,`icon`,`menu_name`,`module`,`controller`,`action`,`api_url`,`methods`,`params`,`sort`,`is_show`,`is_show_path`,`is_tenant`,`is_platform`,`access`,`menu_path`,`path`,`auth_type`,`header`,`is_header`,`unique_auth`,`is_del`)
VALUES
(1390,1200,'','支付单','admin','','','','','[]',8,1,0,0,1,1,'/admin/tenant/payment','1200',1,'tenant',1,'tenant-payment',0),
(1391,1390,'','支付单列表','admin','','','api/admin/setting/tenant/payment','GET','[]',0,0,0,0,1,1,'','1200/1390',2,'',0,'',0),
(1392,1390,'','支付单选项','admin','','','api/admin/setting/tenant/payment/options','GET','[]',0,0,0,0,1,1,'','1200/1390',2,'',0,'',0),
(1393,1390,'','创建支付单','admin','','','api/admin/setting/tenant/payment','POST','[]',0,0,0,0,1,1,'','1200/1390',2,'',0,'',0),
(1394,1390,'','获取支付链接','admin','','','api/admin/setting/tenant/payment/deliver/<id>','GET','[]',0,0,0,0,1,1,'','1200/1390',2,'',0,'',0),
(1395,1390,'','确认到账','admin','','','api/admin/setting/tenant/payment/confirm/<id>','POST','[]',0,0,0,0,1,1,'','1200/1390',2,'',0,'',0),
(1396,1390,'','关闭支付单','admin','','','api/admin/setting/tenant/payment/close/<id>','POST','[]',0,0,0,0,1,1,'','1200/1390',2,'',0,'',0),
(1397,1390,'','同步支付状态','admin','','','api/admin/setting/tenant/payment/sync/<id>','POST','[]',0,0,0,0,1,1,'','1200/1390',2,'',0,'',0),
(1398,1390,'','补开通','admin','','','api/admin/setting/tenant/payment/fulfill/<id>','POST','[]',0,0,0,0,1,1,'','1200/1390',2,'',0,'',0)
ON DUPLICATE KEY UPDATE
`pid`=VALUES(`pid`),`menu_name`=VALUES(`menu_name`),`api_url`=VALUES(`api_url`),`methods`=VALUES(`methods`),
`sort`=VALUES(`sort`),`is_show`=VALUES(`is_show`),`is_tenant`=VALUES(`is_tenant`),`is_platform`=VALUES(`is_platform`),
`menu_path`=VALUES(`menu_path`),`path`=VALUES(`path`),`auth_type`=VALUES(`auth_type`),`header`=VALUES(`header`),
`unique_auth`=VALUES(`unique_auth`),`is_del`=VALUES(`is_del`);

-- 能看订购对账的角色就是管收款的人，顺带授予支付单权限；FIND_IN_SET 判重使脚本可重复执行
UPDATE `eb_system_role`
SET `rules` = CONCAT(`rules`, ',1390,1391,1392,1393,1394,1395,1396,1397,1398')
WHERE FIND_IN_SET('1203', `rules`) > 0 AND FIND_IN_SET('1390', `rules`) = 0;
