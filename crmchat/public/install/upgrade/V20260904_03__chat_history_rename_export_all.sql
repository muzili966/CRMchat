-- 版本：V20260904_03
-- 内容：菜单「历史会话」更名为「历史对话」，并新增全局导出接口权限
-- 依赖：V20260904_01（菜单 1320）、V20260904_02（导出权限 1325）
--
-- 更名只动 menu_name，unique_auth 保持 chat-history 不变——它是前端路由
-- 与角色权限的稳定键，改了会让已有角色的权限全部失配。

UPDATE `eb_system_menus` SET `menu_name` = '历史对话' WHERE `id` = 1320;
UPDATE `eb_system_menus` SET `menu_name` = '历史对话列表' WHERE `id` = 1321;

INSERT INTO `eb_system_menus`
(`id`,`pid`,`icon`,`menu_name`,`module`,`controller`,`action`,`api_url`,`methods`,`params`,`sort`,`is_show`,`is_show_path`,`is_tenant`,`is_platform`,`access`,`menu_path`,`path`,`auth_type`,`header`,`is_header`,`unique_auth`,`is_del`)
VALUES
(1326,1320,'','全局导出对话','admin','','','api/admin/chat/history/export_all','GET','[]',0,0,0,1,0,1,'','165/1320',2,'',0,'',0)
ON DUPLICATE KEY UPDATE
`pid`=VALUES(`pid`),`menu_name`=VALUES(`menu_name`),`api_url`=VALUES(`api_url`),`methods`=VALUES(`methods`),
`sort`=VALUES(`sort`),`is_show`=VALUES(`is_show`),`is_tenant`=VALUES(`is_tenant`),`is_platform`=VALUES(`is_platform`),
`menu_path`=VALUES(`menu_path`),`path`=VALUES(`path`),`auth_type`=VALUES(`auth_type`),`header`=VALUES(`header`),
`unique_auth`=VALUES(`unique_auth`),`is_del`=VALUES(`is_del`);

-- 已有历史对话权限的角色一并补上全局导出。
-- FIND_IN_SET 判重使脚本可重复执行。level=0 的超管不走角色校验，无需处理。
UPDATE `eb_system_role`
SET `rules` = CONCAT(`rules`, ',1326')
WHERE FIND_IN_SET('1320', `rules`) > 0 AND FIND_IN_SET('1326', `rules`) = 0;
