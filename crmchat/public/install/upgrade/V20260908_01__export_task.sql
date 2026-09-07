-- 版本：V20260908_01
-- 内容：下载中心——导出任务表与菜单
-- 依赖：V20260904_03（历史对话全局导出）
--
-- 同步导出受限于请求超时与单进程内存，数据一多就只能靠上限截断。
-- 改为落一条任务、由常驻进程异步跑，请求本身立即返回；文件带保留期，
-- 到期由 GC 连同记录一起清掉，不再无限堆在 public/uploads/export 下。

CREATE TABLE IF NOT EXISTS `eb_export_task` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT '0' COMMENT '租户ID',
  `admin_id` int(11) NOT NULL DEFAULT '0' COMMENT '发起人ID',
  `admin_name` varchar(64) NOT NULL DEFAULT '' COMMENT '发起人名称，冗余保留',
  `type` varchar(32) NOT NULL DEFAULT '' COMMENT '导出类型',
  `type_name` varchar(64) NOT NULL DEFAULT '' COMMENT '导出类型中文名',
  `params` varchar(2000) NOT NULL DEFAULT '' COMMENT '筛选条件JSON',
  `format` varchar(8) NOT NULL DEFAULT 'csv' COMMENT '文件格式 csv/xlsx',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0待处理 1处理中 2成功 3失败',
  `file_url` varchar(255) NOT NULL DEFAULT '' COMMENT '成功后的下载地址',
  `file_size` int(11) NOT NULL DEFAULT '0' COMMENT '文件字节数',
  `row_count` int(11) NOT NULL DEFAULT '0' COMMENT '数据行数，不含表头',
  `message` varchar(255) NOT NULL DEFAULT '' COMMENT '失败原因或截断说明',
  `expire_time` int(11) NOT NULL DEFAULT '0' COMMENT '文件过期时间',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `start_time` int(11) NOT NULL DEFAULT '0',
  `finish_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_status_id` (`status`,`id`),
  KEY `idx_expire` (`expire_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='导出任务';

INSERT INTO `eb_system_menus`
(`id`,`pid`,`icon`,`menu_name`,`module`,`controller`,`action`,`api_url`,`methods`,`params`,`sort`,`is_show`,`is_show_path`,`is_tenant`,`is_platform`,`access`,`menu_path`,`path`,`auth_type`,`header`,`is_header`,`unique_auth`,`is_del`)
VALUES
(1330,12,'','下载中心','admin','','','','','[]',5,1,0,1,1,1,'/admin/export/list','12',1,'setting',0,'export-center',0),
(1331,1330,'','导出任务列表','admin','','','api/admin/export/task','GET','[]',0,0,0,1,1,1,'','12/1330',2,'',0,'',0),
(1332,1330,'','删除导出任务','admin','','','api/admin/export/task/<id>','DELETE','[]',0,0,0,1,1,1,'','12/1330',2,'',0,'',0)
ON DUPLICATE KEY UPDATE
`pid`=VALUES(`pid`),`menu_name`=VALUES(`menu_name`),`api_url`=VALUES(`api_url`),`methods`=VALUES(`methods`),
`sort`=VALUES(`sort`),`is_show`=VALUES(`is_show`),`is_tenant`=VALUES(`is_tenant`),`is_platform`=VALUES(`is_platform`),
`menu_path`=VALUES(`menu_path`),`path`=VALUES(`path`),`auth_type`=VALUES(`auth_type`),`header`=VALUES(`header`),
`unique_auth`=VALUES(`unique_auth`),`is_del`=VALUES(`is_del`);

-- 全局导出改为异步落任务，接口语义从 GET 取文件变成 POST 建任务
UPDATE `eb_system_menus`
SET `menu_name` = '全局导出对话', `api_url` = 'api/admin/chat/history/export_all', `methods` = 'POST'
WHERE `id` = 1326;

-- 所有已有角色都补上下载中心：任何人发起的导出都要能取回文件，
-- 否则会出现"导出成功但没有入口下载"。
UPDATE `eb_system_role`
SET `rules` = CONCAT(`rules`, ',1330,1331,1332')
WHERE `rules` <> '' AND FIND_IN_SET('1330', `rules`) = 0;
