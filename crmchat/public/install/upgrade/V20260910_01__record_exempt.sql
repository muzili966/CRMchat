-- 版本：V20260910_01
-- 内容：租户聊天记录清理豁免
--
-- 保留期清理按套餐一刀切，但总有需要单独放行的租户：诉讼举证期、
-- 重点客户的历史资料、长周期 POC。之前只能临时改套餐或改代码。
-- 这里给运营一个可控开关，并要求写明原因，日后能查得清是谁放的行。
--
-- 用截止时间而不是布尔开关：开关一开就容易忘，数据无限堆积；
-- 给个截止日到期自动恢复清理，要永久豁免填远期日期即可。

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eb_tenant' AND COLUMN_NAME = 'record_exempt_until');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `eb_tenant` ADD COLUMN `record_exempt_until` int(11) NOT NULL DEFAULT 0 COMMENT ''聊天记录清理豁免截止时间，0=不豁免'' AFTER `expire_time`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eb_tenant' AND COLUMN_NAME = 'record_exempt_remark');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `eb_tenant` ADD COLUMN `record_exempt_remark` varchar(255) NOT NULL DEFAULT '''' COMMENT ''豁免原因，便于审计'' AFTER `record_exempt_until`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
