-- 版本：V20260904_01
-- 内容：后台新增「历史会话」页面与接口权限
-- 依赖：无
--
-- 后台此前只有聊天记录接口、没有页面，管理者看不到任何历史对话；
-- 而套餐里在卖「记录保留天数」，卖了保留却不给看，逻辑上说不通。
-- 菜单挂在「客服管理」下，与客户端装修/AI客服设置同级，租户专属
-- （平台视角无租户上下文，不展示）。

INSERT INTO `eb_system_menus`
(`id`,`pid`,`icon`,`menu_name`,`module`,`controller`,`action`,`api_url`,`methods`,`params`,`sort`,`is_show`,`is_show_path`,`is_tenant`,`is_platform`,`access`,`menu_path`,`path`,`auth_type`,`header`,`is_header`,`unique_auth`,`is_del`)
VALUES
(1320,165,'','历史会话','admin','','','','','[]',25,1,0,1,0,1,'/admin/chat/history','165',1,'kefu',0,'chat-history',0),
(1321,1320,'','历史会话列表','admin','','','api/admin/chat/history/sessions','GET','[]',0,0,0,1,0,1,'','165/1320',2,'',0,'',0),
(1322,1320,'','历史访客列表','admin','','','api/admin/chat/history/visitors','GET','[]',0,0,0,1,0,1,'','165/1320',2,'',0,'',0),
(1323,1320,'','访客会话列表','admin','','','api/admin/chat/history/visitor/<id>','GET','[]',0,0,0,1,0,1,'','165/1320',2,'',0,'',0),
(1324,1320,'','历史对话内容','admin','','','api/admin/chat/history/records','GET','[]',0,0,0,1,0,1,'','165/1320',2,'',0,'',0)
ON DUPLICATE KEY UPDATE
`pid`=VALUES(`pid`),`menu_name`=VALUES(`menu_name`),`api_url`=VALUES(`api_url`),`methods`=VALUES(`methods`),
`sort`=VALUES(`sort`),`is_show`=VALUES(`is_show`),`is_tenant`=VALUES(`is_tenant`),`is_platform`=VALUES(`is_platform`),
`menu_path`=VALUES(`menu_path`),`path`=VALUES(`path`),`auth_type`=VALUES(`auth_type`),`header`=VALUES(`header`),
`unique_auth`=VALUES(`unique_auth`),`is_del`=VALUES(`is_del`);

-- 已有角色补上这几个权限，否则升级后老角色看不到新菜单。
-- FIND_IN_SET 判重使脚本可重复执行。level=0 的超管不走角色校验，无需处理。
UPDATE `eb_system_role`
SET `rules` = CONCAT(`rules`, ',1320,1321,1322,1323,1324')
WHERE `rules` <> '' AND FIND_IN_SET('1320', `rules`) = 0;
