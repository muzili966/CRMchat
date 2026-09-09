-- 版本：V20260909_01
-- 内容：访客全量对话（跨客服合并）的两个权限点
-- 依赖：V20260904_01（历史对话菜单 1320）
--
-- 历史对话原本只能按「客服 ↔ 访客」一对一看。一个访客先后找过不同客服、
-- 或先由AI接待再转人工时，要还原他到底经历了什么就得逐个会话点开对照时间。
-- 新增合并视图后，访客视角可以一条时间线看完，并支持整体导出。
--
-- 菜单 ID 取 1370-1371：当前已用到 1365，这里留出间隔避开后续冲突
-- （1330-1335 曾因与下载中心撞号被整体迁走，见 V20260908_07）。

INSERT INTO `eb_system_menus`
(`id`,`pid`,`icon`,`menu_name`,`module`,`controller`,`action`,`api_url`,`methods`,`params`,`sort`,`is_show`,`is_show_path`,`is_tenant`,`is_platform`,`access`,`menu_path`,`path`,`auth_type`,`header`,`is_header`,`unique_auth`,`is_del`)
VALUES
(1370,1320,'','访客全量对话','admin','','','api/admin/chat/history/visitor_records','GET','[]',0,0,0,1,0,1,'','165/1320',2,'',0,'',0),
(1371,1320,'','导出访客对话','admin','','','api/admin/chat/history/visitor_export','GET','[]',0,0,0,1,0,1,'','165/1320',2,'',0,'',0)
ON DUPLICATE KEY UPDATE
`pid`=VALUES(`pid`),`menu_name`=VALUES(`menu_name`),`api_url`=VALUES(`api_url`),`methods`=VALUES(`methods`),
`sort`=VALUES(`sort`),`is_show`=VALUES(`is_show`),`is_tenant`=VALUES(`is_tenant`),`is_platform`=VALUES(`is_platform`),
`menu_path`=VALUES(`menu_path`),`path`=VALUES(`path`),`auth_type`=VALUES(`auth_type`),`header`=VALUES(`header`),
`unique_auth`=VALUES(`unique_auth`),`is_del`=VALUES(`is_del`);

-- 已有角色补权限，否则升级后老角色点不开新视图。
-- FIND_IN_SET 判重使脚本可重复执行；level=0 的超管不走角色校验，无需处理
UPDATE `eb_system_role`
SET `rules` = CONCAT(`rules`, ',1370,1371')
WHERE `rules` <> '' AND FIND_IN_SET('1370', `rules`) = 0;
