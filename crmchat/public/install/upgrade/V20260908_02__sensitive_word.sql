-- 版本：V20260908_02
-- 内容：敏感词管理——两级词库、命中留痕、套餐能力与菜单
-- 依赖：无
--
-- 此前 app/common.php 里的 sensitive_words_filter 无人调用、词库文件也不存在，
-- 等于全无内容过滤：访客发的、客服发的、AI 生成的都直接入库外发。
--
-- 分两级：tenant_id=0 为平台级合规词，所有租户强制生效且不可见不可改；
-- tenant_id>0 为租户自定义业务词（防飞单等），受套餐能力约束。
-- 合规是底线，故平台级词库不随套餐收费。

CREATE TABLE IF NOT EXISTS `eb_sensitive_word` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT '0' COMMENT '租户ID，0为平台级合规词',
  `word` varchar(64) NOT NULL DEFAULT '' COMMENT '词条',
  `category` varchar(32) NOT NULL DEFAULT '' COMMENT '分类，便于批量管理',
  `action` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1拦截 2替换 3仅告警',
  `scope` tinyint(1) NOT NULL DEFAULT '7' COMMENT '作用范围位掩码 1访客 2客服 4AI',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0停用 1启用',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_word` (`tenant_id`,`word`),
  KEY `idx_tenant_status` (`tenant_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='敏感词库';

CREATE TABLE IF NOT EXISTS `eb_sensitive_hit` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT '0' COMMENT '租户ID',
  `word_id` int(11) NOT NULL DEFAULT '0' COMMENT '命中词ID',
  `word` varchar(64) NOT NULL DEFAULT '' COMMENT '命中词，冗余保留以防词条被删',
  `is_platform` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否平台级词命中',
  `action` tinyint(1) NOT NULL DEFAULT '1' COMMENT '实际处置',
  `scope` tinyint(1) NOT NULL DEFAULT '1' COMMENT '来源 1访客 2客服 4AI',
  `appid` varchar(32) NOT NULL DEFAULT '' COMMENT '应用',
  `user_id` int(11) NOT NULL DEFAULT '0' COMMENT '发送方',
  `to_user_id` int(11) NOT NULL DEFAULT '0' COMMENT '接收方',
  `nickname` varchar(64) NOT NULL DEFAULT '' COMMENT '发送方昵称，冗余保留',
  `content` varchar(500) NOT NULL DEFAULT '' COMMENT '原文片段，留作证据链',
  `handled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0待处理 1已处理',
  `create_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_time` (`tenant_id`,`create_time`),
  KEY `idx_tenant_handled` (`tenant_id`,`handled`),
  KEY `idx_word` (`word_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='敏感词命中记录';

-- 租户自定义词库属付费能力；平台级合规词不受此开关约束
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eb_tenant_plan' AND COLUMN_NAME = 'sensitive_word');
SET @s := IF(@c = 0, 'ALTER TABLE `eb_tenant_plan` ADD COLUMN `sensitive_word` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''自定义敏感词'' AFTER `file_send`', 'DO 0');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

UPDATE `eb_tenant_plan` SET `sensitive_word` = 1 WHERE `price` > 0 AND `is_delete` = 0;

INSERT INTO `eb_system_menus`
(`id`,`pid`,`icon`,`menu_name`,`module`,`controller`,`action`,`api_url`,`methods`,`params`,`sort`,`is_show`,`is_show_path`,`is_tenant`,`is_platform`,`access`,`menu_path`,`path`,`auth_type`,`header`,`is_header`,`unique_auth`,`is_del`)
VALUES
(1340,12,'','敏感词管理','admin','','','','','[]',4,1,0,1,1,1,'/admin/sensitive/word','12',1,'setting',0,'sensitive-word',0),
(1341,1340,'','敏感词列表','admin','','','api/admin/sensitive/word','GET','[]',0,0,0,1,1,1,'','12/1340',2,'',0,'',0),
(1342,1340,'','新增敏感词','admin','','','api/admin/sensitive/word','POST','[]',0,0,0,1,1,1,'','12/1340',2,'',0,'',0),
(1343,1340,'','修改敏感词','admin','','','api/admin/sensitive/word/<id>','PUT','[]',0,0,0,1,1,1,'','12/1340',2,'',0,'',0),
(1344,1340,'','删除敏感词','admin','','','api/admin/sensitive/word/<id>','DELETE','[]',0,0,0,1,1,1,'','12/1340',2,'',0,'',0),
(1345,1340,'','批量导入敏感词','admin','','','api/admin/sensitive/word/import','POST','[]',0,0,0,1,1,1,'','12/1340',2,'',0,'',0),
(1346,12,'','敏感词命中','admin','','','','','[]',3,1,0,1,1,1,'/admin/sensitive/hit','12',1,'setting',0,'sensitive-hit',0),
(1347,1346,'','命中记录列表','admin','','','api/admin/sensitive/hit','GET','[]',0,0,0,1,1,1,'','12/1346',2,'',0,'',0),
(1348,1346,'','标记命中已处理','admin','','','api/admin/sensitive/hit/handle/<id>','PUT','[]',0,0,0,1,1,1,'','12/1346',2,'',0,'',0)
ON DUPLICATE KEY UPDATE
`pid`=VALUES(`pid`),`menu_name`=VALUES(`menu_name`),`api_url`=VALUES(`api_url`),`methods`=VALUES(`methods`),
`sort`=VALUES(`sort`),`is_show`=VALUES(`is_show`),`is_tenant`=VALUES(`is_tenant`),`is_platform`=VALUES(`is_platform`),
`menu_path`=VALUES(`menu_path`),`path`=VALUES(`path`),`auth_type`=VALUES(`auth_type`),`header`=VALUES(`header`),
`unique_auth`=VALUES(`unique_auth`),`is_del`=VALUES(`is_del`);

UPDATE `eb_system_role`
SET `rules` = CONCAT(`rules`, ',1340,1341,1342,1343,1344,1345,1346,1347,1348')
WHERE `rules` <> '' AND FIND_IN_SET('1340', `rules`) = 0;
