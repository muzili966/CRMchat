-- 版本：V20260910_02
-- 内容：平台客服入口的接口权限
--
-- 租户后台右侧挂一个悬浮入口，点开就是平台自营租户的会话窗口。
-- 平台自己在卖客服系统，接待租户没道理另造一套工单，复用现成的接待、
-- 转接、历史记录即可。这里只需一个接口权限点，页面本身不在菜单里。
--
-- 菜单 ID 取 1380：当前已用到 1371，留出间隔避开后续冲突
-- （1330-1335 曾因与下载中心撞号被整体迁走，见 V20260908_07）。

INSERT INTO `eb_system_menus`
(`id`,`pid`,`icon`,`menu_name`,`module`,`controller`,`action`,`api_url`,`methods`,`params`,`sort`,`is_show`,`is_show_path`,`is_tenant`,`is_platform`,`access`,`menu_path`,`path`,`auth_type`,`header`,`is_header`,`unique_auth`,`is_del`)
VALUES
(1380,1300,'','平台客服入口','admin','','','api/admin/platform/support','GET','[]',0,0,0,1,0,1,'','1300',2,'',0,'',0)
ON DUPLICATE KEY UPDATE
`pid`=VALUES(`pid`),`menu_name`=VALUES(`menu_name`),`api_url`=VALUES(`api_url`),`methods`=VALUES(`methods`),
`sort`=VALUES(`sort`),`is_show`=VALUES(`is_show`),`is_tenant`=VALUES(`is_tenant`),`is_platform`=VALUES(`is_platform`),
`menu_path`=VALUES(`menu_path`),`path`=VALUES(`path`),`auth_type`=VALUES(`auth_type`),`header`=VALUES(`header`),
`unique_auth`=VALUES(`unique_auth`),`is_del`=VALUES(`is_del`);

-- 已有角色补权限，否则升级后老角色的悬浮入口拉不到配置。
-- FIND_IN_SET 判重使脚本可重复执行；level=0 的超管不走角色校验，无需处理
UPDATE `eb_system_role`
SET `rules` = CONCAT(`rules`, ',1380')
WHERE `rules` <> '' AND FIND_IN_SET('1380', `rules`) = 0;
