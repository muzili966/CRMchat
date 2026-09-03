-- 版本：V20260903_01
-- 内容：移除后台的「官网地址」配置项
-- 依赖：V20260902_04（本脚本撤销它）
--
-- 官网地址、控制台地址、客服服务地址都属于部署拓扑：随环境走、不随租户走，
-- 一处填错整批接入代码就失效，跟数据库凭据同一层级，故改由 .env 提供
-- （WEBSITE_URL / CONSOLE_URL / SERVICE_URL）。
-- 后台留着一个改了不生效的输入框只会误导人，故删除。

DELETE FROM `eb_system_config` WHERE `menu_name` = 'website_url';
