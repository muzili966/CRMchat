-- 版本：V20260908_03
-- 内容：会话（一次接待）实体与绩效指标采集
-- 依赖：无
--
-- eb_chat_service_record 是「客服×访客」的持久索引行，同一对聊三个月也只有一行，
-- 它表达不了「一次接待」。绩效与满意度都需要这个粒度，故新建会话表。
--
-- 指标采用增量累计而非事后扫描消息表：每条消息 O(1) 更新，
-- 报表直接聚合会话行；事后扫描千万级消息表算首响是不可行的。

CREATE TABLE IF NOT EXISTS `eb_chat_session` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT '0' COMMENT '租户ID',
  `appid` varchar(32) NOT NULL DEFAULT '' COMMENT '应用',
  `kefu_user_id` int(11) NOT NULL DEFAULT '0' COMMENT '接待客服',
  `visitor_user_id` int(11) NOT NULL DEFAULT '0' COMMENT '访客',
  `is_ai` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否AI坐席接待',
  `transferred` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否发生过转人工',
  `start_time` int(11) NOT NULL DEFAULT '0' COMMENT '首条消息时间',
  `last_time` int(11) NOT NULL DEFAULT '0' COMMENT '最后一条消息时间',
  `end_time` int(11) NOT NULL DEFAULT '0' COMMENT '结束时间',
  `end_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0进行中 1超时自动 2客服结束',
  `visitor_msg_num` int(11) NOT NULL DEFAULT '0' COMMENT '访客消息数',
  `kefu_msg_num` int(11) NOT NULL DEFAULT '0' COMMENT '客服消息数',
  `first_reply_cost` int(11) NOT NULL DEFAULT '0' COMMENT '首次响应秒数，0=未回复',
  `reply_cost_sum` int(11) NOT NULL DEFAULT '0' COMMENT '响应耗时累计，用于算平均',
  `reply_count` int(11) NOT NULL DEFAULT '0' COMMENT '有效响应次数',
  `pending_since` int(11) NOT NULL DEFAULT '0' COMMENT '访客最新一条待回复消息的时间，0=无待回复',
  `rate` tinyint(1) NOT NULL DEFAULT '0' COMMENT '满意度1-5，0=未评价',
  `rate_remark` varchar(255) NOT NULL DEFAULT '' COMMENT '评价留言',
  `rate_time` int(11) NOT NULL DEFAULT '0' COMMENT '评价时间',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1进行中 2已结束',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_tenant_start` (`tenant_id`,`start_time`),
  KEY `idx_kefu_start` (`kefu_user_id`,`start_time`),
  KEY `idx_pair_status` (`tenant_id`,`kefu_user_id`,`visitor_user_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客服会话（一次接待）';

INSERT INTO `eb_system_menus`
(`id`,`pid`,`icon`,`menu_name`,`module`,`controller`,`action`,`api_url`,`methods`,`params`,`sort`,`is_show`,`is_show_path`,`is_tenant`,`is_platform`,`access`,`menu_path`,`path`,`auth_type`,`header`,`is_header`,`unique_auth`,`is_del`)
VALUES
(1350,165,'','客服绩效','admin','','','','','[]',26,1,0,1,0,1,'/admin/chat/performance','165',1,'kefu',0,'chat-performance',0),
(1351,1350,'','绩效概览','admin','','','api/admin/chat/performance/overview','GET','[]',0,0,0,1,0,1,'','165/1350',2,'',0,'',0),
(1352,1350,'','客服绩效明细','admin','','','api/admin/chat/performance/agents','GET','[]',0,0,0,1,0,1,'','165/1350',2,'',0,'',0),
(1353,1350,'','绩效趋势','admin','','','api/admin/chat/performance/trend','GET','[]',0,0,0,1,0,1,'','165/1350',2,'',0,'',0),
(1354,1350,'','会话明细','admin','','','api/admin/chat/performance/sessions','GET','[]',0,0,0,1,0,1,'','165/1350',2,'',0,'',0)
ON DUPLICATE KEY UPDATE
`pid`=VALUES(`pid`),`menu_name`=VALUES(`menu_name`),`api_url`=VALUES(`api_url`),`methods`=VALUES(`methods`),
`sort`=VALUES(`sort`),`is_show`=VALUES(`is_show`),`is_tenant`=VALUES(`is_tenant`),`is_platform`=VALUES(`is_platform`),
`menu_path`=VALUES(`menu_path`),`path`=VALUES(`path`),`auth_type`=VALUES(`auth_type`),`header`=VALUES(`header`),
`unique_auth`=VALUES(`unique_auth`),`is_del`=VALUES(`is_del`);

UPDATE `eb_system_role`
SET `rules` = CONCAT(`rules`, ',1350,1351,1352,1353,1354')
WHERE `rules` <> '' AND FIND_IN_SET('1350', `rules`) = 0;
