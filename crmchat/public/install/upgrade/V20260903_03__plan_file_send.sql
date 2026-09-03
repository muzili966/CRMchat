-- 版本：V20260903_03
-- 内容：套餐新增「文件收发」能力开关
-- 依赖：无
--
-- 访客与客服互发办公文档/压缩包属付费能力，免费版仅图片与文字。
-- 存量套餐：有价格的（付费）默认开启，免费套餐保持关闭。

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eb_tenant_plan' AND COLUMN_NAME = 'file_send');
SET @s := IF(@c = 0, 'ALTER TABLE `eb_tenant_plan` ADD COLUMN `file_send` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''文件收发'' AFTER `ai_reply`', 'DO 0');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

UPDATE `eb_tenant_plan` SET `file_send` = 1 WHERE `price` > 0 AND `is_delete` = 0;
