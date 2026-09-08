-- 版本：V20260908_07
-- 内容：修复常见问题菜单与下载中心的 ID 冲突
-- 依赖：V20260908_01（下载中心菜单）、V20260908_05（常见问题菜单）
--
-- V20260908_05 给常见问题分配了 1330-1335，但 1330-1332 早已被
-- V20260908_01 的「下载中心」占用，其 ON DUPLICATE KEY UPDATE 把三条
-- 下载中心菜单直接改写成了常见问题菜单——升级过的环境里下载中心整个消失，
-- 导出任务功能没有后台入口。
--
-- 处置：ID 是权限点的对外标识，不能靠改语义收场，故把常见问题整体迁到
-- 空闲段 1360-1365，把 1330-1332 还原成下载中心，并清掉只属于常见问题的
-- 1333-1335。菜单 ID 变了但 unique_auth 不变，前端路由按 unique_auth 取权限，
-- 无需同步改动。
--
-- 角色补权限用 1360 判重而非 1330：V20260908_05 用 1330 判重正是它没能给
-- 任何角色补上常见问题权限的原因——那时 1330 已被下载中心占着。

-- 1) 还原下载中心，定义与 V20260908_01 一致
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

-- 2) 常见问题迁到空闲段 1360-1365
INSERT INTO `eb_system_menus`
(`id`,`pid`,`icon`,`menu_name`,`module`,`controller`,`action`,`api_url`,`methods`,`params`,`sort`,`is_show`,`is_show_path`,`is_tenant`,`is_platform`,`access`,`menu_path`,`path`,`auth_type`,`header`,`is_header`,`unique_auth`,`is_del`)
VALUES
(1360,165,'','常见问题','admin','','','','','[]',24,1,0,1,0,1,'/admin/chat/faq','165',1,'kefu',0,'chat-faq',0),
(1361,1360,'','常见问题列表','admin','','','api/admin/chat/faq','GET','[]',0,0,0,1,0,1,'','165/1360',2,'',0,'',0),
(1362,1360,'','保存常见问题','admin','','','api/admin/chat/faq','POST','[]',0,0,0,1,0,1,'','165/1360',2,'',0,'',0),
(1363,1360,'','更新常见问题','admin','','','api/admin/chat/faq/<id>','PUT','[]',0,0,0,1,0,1,'','165/1360',2,'',0,'',0),
(1364,1360,'','删除常见问题','admin','','','api/admin/chat/faq/<id>','DELETE','[]',0,0,0,1,0,1,'','165/1360',2,'',0,'',0),
(1365,1360,'','常见问题排序','admin','','','api/admin/chat/faq/sort','POST','[]',0,0,0,1,0,1,'','165/1360',2,'',0,'',0)
ON DUPLICATE KEY UPDATE
`pid`=VALUES(`pid`),`menu_name`=VALUES(`menu_name`),`api_url`=VALUES(`api_url`),`methods`=VALUES(`methods`),
`sort`=VALUES(`sort`),`is_show`=VALUES(`is_show`),`is_tenant`=VALUES(`is_tenant`),`is_platform`=VALUES(`is_platform`),
`menu_path`=VALUES(`menu_path`),`path`=VALUES(`path`),`auth_type`=VALUES(`auth_type`),`header`=VALUES(`header`),
`unique_auth`=VALUES(`unique_auth`),`is_del`=VALUES(`is_del`);

-- 3) 清掉只属于常见问题的 1333-1335；带 menu_name 条件避免误删其他环境的同号菜单
DELETE FROM `eb_system_menus`
WHERE `id` IN (1333,1334,1335)
  AND `menu_name` IN ('更新常见问题','删除常见问题','常见问题排序');

-- 4) 角色规则：摘掉迁走的旧权限点。REPLACE 前后补逗号，避免 133 误伤 1333
UPDATE `eb_system_role`
SET `rules` = TRIM(BOTH ',' FROM REPLACE(CONCAT(',',`rules`,','), ',1333,', ','))
WHERE FIND_IN_SET('1333', `rules`) > 0;

UPDATE `eb_system_role`
SET `rules` = TRIM(BOTH ',' FROM REPLACE(CONCAT(',',`rules`,','), ',1334,', ','))
WHERE FIND_IN_SET('1334', `rules`) > 0;

UPDATE `eb_system_role`
SET `rules` = TRIM(BOTH ',' FROM REPLACE(CONCAT(',',`rules`,','), ',1335,', ','))
WHERE FIND_IN_SET('1335', `rules`) > 0;

-- 5) 补上常见问题权限。V20260908_05 因判重键选错从未真正补到任何角色，这里一并补齐
UPDATE `eb_system_role`
SET `rules` = CONCAT(`rules`, ',1360,1361,1362,1363,1364,1365')
WHERE `rules` <> '' AND FIND_IN_SET('1360', `rules`) = 0;
