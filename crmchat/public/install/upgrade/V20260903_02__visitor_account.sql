-- 版本：V20260903_02
-- 内容：访客账号（手机号绑定，支持换设备接续会话）
-- 依赖：无
--
-- 访客可绑定手机号，换设备时凭手机号+验证码或密码接续上次会话。
-- 手机号在公网可枚举，故它本身不是凭据：绑定/登录都要过验证码或密码，
-- 且绑定维度是 (tenant_id, appid, phone)——多租户下同号会落到不同租户，
-- 全局唯一会串号。
--
-- password_hash 空串表示未设密码，此时只能用验证码登录。
-- token_version 用于吊销：改密码或注销全部设备时自增，旧续接令牌立即失效。

CREATE TABLE IF NOT EXISTS `eb_chat_visitor_account` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL DEFAULT 0 COMMENT '所属租户ID',
  `appid` varchar(32) NOT NULL DEFAULT '' COMMENT '所属应用',
  `phone` varchar(20) NOT NULL DEFAULT '' COMMENT '绑定手机号',
  `password_hash` varchar(255) NOT NULL DEFAULT '' COMMENT '密码哈希,空串=未设密码,只能验证码登录',
  `user_id` int NOT NULL DEFAULT 0 COMMENT '关联的访客ID(eb_chat_user.id)',
  `token_version` int NOT NULL DEFAULT 1 COMMENT '续接令牌版本,改密码或注销时自增以吊销旧令牌',
  `failed_attempts` int NOT NULL DEFAULT 0 COMMENT '连续失败次数',
  `locked_until` int NOT NULL DEFAULT 0 COMMENT '锁定到期时间戳',
  `last_login_time` int NOT NULL DEFAULT 0 COMMENT '最近登录时间',
  `create_time` int NOT NULL DEFAULT 0,
  `update_time` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tenant_app_phone` (`tenant_id`, `appid`, `phone`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='访客账号';
