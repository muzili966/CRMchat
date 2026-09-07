-- 版本：V20260904_02
-- 内容：历史会话新增「导出对话」接口权限
-- 依赖：V20260904_01__chat_history_menu.sql（菜单 1320）
--
-- 导出本身还受套餐 data_export 能力约束，这里只补菜单权限位。

INSERT INTO `eb_system_menus`
(`id`,`pid`,`icon`,`menu_name`,`module`,`controller`,`action`,`api_url`,`methods`,`params`,`sort`,`is_show`,`is_show_path`,`is_tenant`,`is_platform`,`access`,`menu_path`,`path`,`auth_type`,`header`,`is_header`,`unique_auth`,`is_del`)
VALUES
(1325,1320,'','导出对话','admin','','','api/admin/chat/history/export','GET','[]',0,0,0,1,0,1,'','165/1320',2,'',0,'',0)
ON DUPLICATE KEY UPDATE
`pid`=VALUES(`pid`),`menu_name`=VALUES(`menu_name`),`api_url`=VALUES(`api_url`),`methods`=VALUES(`methods`),
`sort`=VALUES(`sort`),`is_show`=VALUES(`is_show`),`is_tenant`=VALUES(`is_tenant`),`is_platform`=VALUES(`is_platform`),
`menu_path`=VALUES(`menu_path`),`path`=VALUES(`path`),`auth_type`=VALUES(`auth_type`),`header`=VALUES(`header`),
`unique_auth`=VALUES(`unique_auth`),`is_del`=VALUES(`is_del`);

-- 已有历史会话权限的角色一并补上导出，否则升级后按钮点了就是没权限。
-- FIND_IN_SET 判重使脚本可重复执行。level=0 的超管不走角色校验，无需处理。
UPDATE `eb_system_role`
SET `rules` = CONCAT(`rules`, ',1325')
WHERE FIND_IN_SET('1320', `rules`) > 0 AND FIND_IN_SET('1325', `rules`) = 0;
