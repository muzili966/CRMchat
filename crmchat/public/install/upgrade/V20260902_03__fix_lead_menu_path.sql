-- 版本：V20260902_03
-- 内容：修正销售线索菜单的前端路径与所属头部
-- 依赖：V20260902_01
--
-- 菜单写的是 /admin/platform/lead，但前端路由注册在 tenant 模块下，
-- 实际地址是 /admin/tenant/lead，点菜单必然 404。
-- header 也漏了，空值会让它落不进「租户管理」这一组导航。

UPDATE `eb_system_menus` SET `menu_path` = '/admin/tenant/lead', `header` = 'tenant' WHERE `id` = 1310;
