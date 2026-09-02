-- 版本：V20260902_04
-- 内容：新增「官网地址」配置项
-- 依赖：无
--
-- 官网可以被解析到独立域名（如 www），此时后台登录页需要一条回官网的链接；
-- 反过来官网上的控制台与客服接入则用既有的 site_url 指回应用本身。
-- 留空表示未启用，相关入口不展示。

INSERT INTO `eb_system_config`
(`id`,`tenant_id`,`menu_name`,`type`,`input_type`,`config_tab_id`,`parameter`,`upload_type`,`required`,`width`,`high`,`value`,`info`,`desc`,`sort`,`status`)
VALUES
(385,0,'website_url','text','input',1,'',0,'',100,0,'""','官网地址','官网被解析到独立域名时填写，例如 https://www.example.com；后台登录页据此展示返回官网的入口，留空则不展示',6,1)
ON DUPLICATE KEY UPDATE `info` = VALUES(`info`), `desc` = VALUES(`desc`), `sort` = VALUES(`sort`), `status` = VALUES(`status`);
