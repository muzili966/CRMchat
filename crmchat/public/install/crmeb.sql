


-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `eb_chat_auto_reply` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `keyword` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '关键词',
  `title` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '卡片展示的问题',
  `is_faq` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否展示在常见问题卡片',
  `content` text COLLATE utf8_unicode_ci NOT NULL COMMENT '回复内容',
  `user_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属用户',
  `appid` varchar(64) COLLATE utf8_unicode_ci NOT NULL COMMENT '所属appid',
  `sort` INT(10) NOT NULL DEFAULT '0' COMMENT '排序,越靠前,越是能被自会回复到',
  `add_time` int(10) NOT NULL DEFAULT '0' COMMENT '添加时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='自动回复';


CREATE TABLE IF NOT EXISTS `eb_chat_complain` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content` varchar(100) NOT NULL DEFAULT '' COMMENT '投诉内容',
  `user_id` int(10) NOT NULL DEFAULT '0' COMMENT '用户表ID',
  `cate_id` int(10) NOT NULL DEFAULT '0' COMMENT '分类',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `cate_id` (`cate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户投诉';

--
-- 表的结构 `eb_tenant`
--

CREATE TABLE IF NOT EXISTS `eb_tenant` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '租户名称',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态0=禁用,1=正常',
  `plan` varchar(32) NOT NULL DEFAULT '' COMMENT '套餐标识(预留)',
  `expire_time` int(10) NOT NULL DEFAULT '0' COMMENT '到期时间0=永久(预留)',
  `record_exempt_until` int(11) NOT NULL DEFAULT '0' COMMENT '聊天记录清理豁免截止时间，0=不豁免',
  `record_exempt_remark` varchar(255) NOT NULL DEFAULT '' COMMENT '豁免原因，便于审计',
  `domain` varchar(100) NOT NULL DEFAULT '' COMMENT '独立域名(预留)',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `is_delete` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否删除',
  `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(10) NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='租户表';

--
-- 转存表中的数据 `eb_tenant`
--

INSERT INTO `eb_tenant` (`id`, `name`, `status`, `create_time`, `update_time`) VALUES
(1, '默认租户', 1, 1625735898, 1625735898);

-- --------------------------------------------------------

--
-- 表的结构 `eb_application`
--

CREATE TABLE IF NOT EXISTS `eb_application` (
  `id` int(11) NOT NULL,
  `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '应用名称',
  `appid` varchar(32) NOT NULL DEFAULT '' COMMENT '应用ID',
  `app_secret` varchar(255) NOT NULL DEFAULT '' COMMENT '应用KEY',
  `icon` varchar(255) NOT NULL DEFAULT '' COMMENT '应用图标',
  `introduce` varchar(255) NOT NULL DEFAULT '' COMMENT '应用介绍',
  `timestamp` int(10) NOT NULL DEFAULT '0' COMMENT 'TOKEN生成时间戳',
  `rand` int(4) NOT NULL DEFAULT '0' COMMENT 'TOKEN携带随机数',
  `token` varchar(500) NOT NULL DEFAULT '' COMMENT 'TOKEN',
  `token_md5` varchar(32) NOT NULL DEFAULT '' COMMENT '短TOKEN',
  `is_delete` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否删除',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COMMENT='应用';

--
-- 转存表中的数据 `eb_application`
--

INSERT INTO `eb_application` (`id`, `tenant_id`, `name`, `appid`, `app_secret`, `icon`, `introduce`, `timestamp`, `rand`, `token`, `token_md5`, `is_delete`, `create_time`, `update_time`) VALUES
(3, 1, '客服', '202116257358989495', 'da52ac13388dbbfe45f34315f580e31e', 'https://qiniu.crmeb.net/attach/2021/07/069e7202107011810578311.png', '', 1625735898, 9495, 'eyJpdiI6Im1oNThXdWZSY250QkhuTm4wdXJkeFE9PSIsInZhbHVlIjoiM2lDMEFNdERZYWlLZmJhRnBMVVE4NG1IbTIwRlBEU3MxajdVSEplUHNYWDlEbHdCdHJsUWFSY0pIRlpIMjN4NHhneXpGaXJ4ZzYxTDRSdVJWVWJVdWxWcndmaGNnRWd1L1l2NmJ3U0VQQ0V2Ry96ZmNLeDNKRWtjVVFLZkVSbzgzd21pWVlCcjAxaUhmNEpSUC9aUGkzMm1VR3I2ZCtUc2pLamcrNGpVL29RPSIsIm1hYyI6IjlmMWFhZDlhY2UxYjRjYzFhMTAwODE5MzJjNDM3MWMxNGJiZjJjZjhhZTI5ODc3OWMxMDZlODRiYjFkZTI3M2EifQ==','2f9eac61b216208cac9c1f0859070a8b',0, '2021-07-08 09:18:18', '2021-07-08 09:18:18');

-- --------------------------------------------------------

--
-- 表的结构 `eb_cache`
--

CREATE TABLE IF NOT EXISTS `eb_cache` (
  `key` varchar(32) NOT NULL DEFAULT '' COMMENT '身份管理名称',
  `result` text NOT NULL COMMENT '缓存数据',
  `expire_time` int(11) NOT NULL DEFAULT '0' COMMENT '失效时间0=永久',
  `add_time` int(11) NOT NULL DEFAULT '0' COMMENT '缓存时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='数据缓存表';

--
-- 转存表中的数据 `eb_cache`
--

INSERT INTO `eb_cache` (`key`, `result`, `expire_time`, `add_time`) VALUES
('kf_adv', '"<p><strong>\\u8fd9\\u5c31\\u662f\\u4e00\\u4e2a\\u6d4b\\u8bd5\\u754c\\u9762\\uff0c\\u60f3\\u4e86\\u89e3\\u66f4\\u591a\\u8bf7\\u5173\\u6ce8\\u6211\\u4eec<\\/strong><br\\/><\\/p>"', 0, 1626662767);

-- --------------------------------------------------------

--
-- 表的结构 `eb_category`
--

CREATE TABLE IF NOT EXISTS `eb_category` (
  `id` int(11) NOT NULL,
  `pid` int(10) NOT NULL DEFAULT '0' COMMENT '上级id',
  `owner_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属人，0为全部',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '分类名称',
  `sort` int(10) NOT NULL DEFAULT '0' COMMENT '排序',
  `type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '分类类型0=标签分类，1=快捷短语分类',
  `other` text NOT NULL COMMENT '其他参数',
  `add_time` int(10) NOT NULL DEFAULT '0' COMMENT '添加时间'
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COMMENT='分类';

-- --------------------------------------------------------

--
-- 表的结构 `eb_chat_service`
--

CREATE TABLE IF NOT EXISTS `eb_chat_service` (
  `id` int(11) NOT NULL,
  `appid` varchar(32) NOT NULL DEFAULT '' COMMENT 'APPID',
  `mer_id` int(10) NOT NULL DEFAULT '0' COMMENT '商户id',
  `user_id` int(10) NOT NULL DEFAULT '0' COMMENT '客服uid',
  `online` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否在线',
  `account` varchar(50) NOT NULL DEFAULT '' COMMENT '账号',
  `password` varchar(255) NOT NULL DEFAULT '' COMMENT '密码',
  `avatar` varchar(255) NOT NULL DEFAULT '' COMMENT '客服头像',
  `nickname` varchar(50) NOT NULL DEFAULT '' COMMENT '代理名称',
  `phone` varchar(32) NOT NULL DEFAULT '' COMMENT '客服电话',
  `add_time` int(10) NOT NULL DEFAULT '0' COMMENT '添加时间',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '客服状态，0隐藏1显示',
  `notify` tinyint(1) NOT NULL DEFAULT '0' COMMENT '订单通知1开启0关闭',
  `customer` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否展示统计管理',
  `uniqid` varchar(35) NOT NULL DEFAULT '' COMMENT '扫码登录唯一值',
  `is_app` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否为APP登陆',
  `is_backstage` TINYINT(1) NOT NULL DEFAULT '1' COMMENT '1=前台运行;0=后台运行',
  `auto_reply` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '自动回复',
  `welcome_words` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '欢迎语',
  `update_time` int(10) NOT NULL DEFAULT '0' COMMENT '更新时间',
  `ip` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '访问IP',
  `client_id` varchar(50) NOT NULL DEFAULT '' COMMENT 'client_id'
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COMMENT='客服表';

--
-- 转存表中的数据 `eb_chat_service`
--

INSERT INTO `eb_chat_service` (`id`, `appid`, `mer_id`, `user_id`, `online`, `account`, `password`, `avatar`, `nickname`, `phone`, `add_time`, `status`, `notify`, `customer`, `uniqid`) VALUES
(10, '202116257358989495', 0, 1, 1, 'kefu', '$2y$10$Iv0RLY8c/X06Qr3q740z7eftEWn1PixEixvKO.wtjklk6P1KwoKIK', 'https://chat.crmeb.net/uploads/attach/2021/09/20210906/c79d19dbfda66026ec891d188386cbb7.png', 'CRM 客服', '15594500000', 1626777835, 1, 0, 0, '');

-- --------------------------------------------------------

--
-- 表的结构 `eb_chat_service_dialogue_record`
--

CREATE TABLE IF NOT EXISTS `eb_chat_service_dialogue_record` (
  `id` int(11) NOT NULL,
  `appid` varchar(32) NOT NULL DEFAULT '' COMMENT 'APPID',
  `mer_id` int(32) NOT NULL DEFAULT '0' COMMENT '商户id',
  `msn` text NOT NULL COMMENT '消息内容',
  `user_id` int(10) NOT NULL DEFAULT '0' COMMENT '发送人uid',
  `to_user_id` int(10) NOT NULL DEFAULT '0' COMMENT '接收人uid',
  `is_tourist` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1=游客模式，0=非游客',
  `add_time` int(10) NOT NULL DEFAULT '0' COMMENT '发送时间',
  `type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已读（0：否；1：是；）',
  `remind` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否提醒过',
  `msn_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '消息类型 1=文字 2=表情 3=图片 4=语音',
  `is_send` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '是否发送',
  `other` varchar(2000) NOT NULL DEFAULT '' COMMENT '其他参数',
  `guid` varchar(100) NOT NULL DEFAULT '' COMMENT 'guid相当于唯一值'
) ENGINE=InnoDB AUTO_INCREMENT=466 DEFAULT CHARSET=utf8mb4 COMMENT='用户和客服对话记录';

-- --------------------------------------------------------

--
-- 表的结构 `eb_chat_service_feedback`
--

CREATE TABLE IF NOT EXISTS `eb_chat_service_feedback` (
  `id` int(11) NOT NULL,
  `user_id` int(10) NOT NULL DEFAULT '0' COMMENT '用户uid',
  `rela_name` varchar(50) NOT NULL DEFAULT '0' COMMENT '姓名',
  `phone` varchar(11) NOT NULL DEFAULT '0' COMMENT '电话',
  `content` varchar(500) NOT NULL DEFAULT '0' COMMENT '反馈内容',
  `make` varchar(500) NOT NULL DEFAULT '0' COMMENT '备注',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态0=未查看，1=已查看',
  `add_time` int(10) NOT NULL DEFAULT '0' COMMENT '添加时间',
  `appid` varchar(32) NOT NULL DEFAULT '' COMMENT 'APPID'
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COMMENT='客服反馈表';

-- --------------------------------------------------------

--
-- 表的结构 `eb_chat_service_record`
--

CREATE TABLE IF NOT EXISTS `eb_chat_service_record` (
  `id` int(11) NOT NULL,
  `appid` varchar(32) NOT NULL DEFAULT '' COMMENT 'APPID',
  `user_id` int(10) NOT NULL DEFAULT '0' COMMENT '发送人的uid',
  `to_user_id` int(10) NOT NULL DEFAULT '0' COMMENT '送达人的uid',
  `nickname` varchar(50) NOT NULL DEFAULT '' COMMENT '用户昵称',
  `avatar` varchar(255) NOT NULL DEFAULT '' COMMENT '用户头像',
  `is_tourist` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否是游客',
  `online` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否在线',
  `type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0 = pc,1=微信，2=小程序，3=H5',
  `add_time` int(10) NOT NULL DEFAULT '0' COMMENT '添加时间',
  `update_time` int(10) NOT NULL DEFAULT '0' COMMENT '更新时间',
  `delete_time` INT(10) NULL DEFAULT NULL COMMENT '删除字段',
  `mssage_num` int(10) NOT NULL DEFAULT '0' COMMENT '消息条数',
  `message` text NOT NULL COMMENT '消息内容',
  `message_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '消息类型'
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COMMENT='聊天记录';

-- --------------------------------------------------------

--
-- 表的结构 `eb_chat_service_speechcraft`
--

CREATE TABLE IF NOT EXISTS `eb_chat_service_speechcraft` (
  `id` int(11) NOT NULL,
  `kefu_id` int(10) NOT NULL DEFAULT '0' COMMENT '0为全局话术',
  `cate_id` int(10) NOT NULL DEFAULT '0' COMMENT '0为不分类全局话术',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '话术标题',
  `message` varchar(255) NOT NULL DEFAULT '' COMMENT '话术内容',
  `sort` int(10) NOT NULL DEFAULT '0' COMMENT '排序',
  `add_time` int(10) NOT NULL DEFAULT '0' COMMENT '添加时间'
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COMMENT='客服话术';

-- --------------------------------------------------------

--
-- 表的结构 `eb_chat_user`
--

CREATE TABLE IF NOT EXISTS `eb_chat_user` (
  `id` int(11) NOT NULL,
  `uid` int(10) NOT NULL DEFAULT '0' COMMENT '用户UID',
  `group_id` int(10) NOT NULL DEFAULT '0' COMMENT '分组',
  `nickname` varchar(50) NOT NULL DEFAULT '' COMMENT '用户昵称',
  `remark_nickname` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '备注昵称',
  `openid` varchar(50) NOT NULL DEFAULT '' COMMENT 'openid',
  `avatar` varchar(255) NOT NULL DEFAULT '' COMMENT '头像',
  `phone` varchar(11) NOT NULL DEFAULT '' COMMENT '手机号',
  `last_ip` varchar(16) NOT NULL DEFAULT '' COMMENT '访问ip',
  `appid` varchar(32) NOT NULL DEFAULT '' COMMENT 'appid',
  `remarks` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `is_delete` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否删除',
  `is_kefu` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否客服',
  `is_tourist` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否游客',
  `type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '用户类型 0 = pc , 1 = 微信 ，2 = 小程序 ，3 = H5, 4 = APP',
  `sex` tinyint(1) NOT NULL DEFAULT '0' COMMENT '性别',
  `online` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '1=在线,0=离线',
  `version` varchar(50) NOT NULL DEFAULT '0' COMMENT '版本号',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='客服用户';

--
-- 转存表中的数据 `eb_chat_user`
--

INSERT INTO `eb_chat_user` (`id`, `uid`, `group_id`, `nickname`, `avatar`, `phone`, `last_ip`, `appid`, `remarks`, `is_delete`, `is_kefu`, `is_tourist`, `type`, `sex`, `create_time`, `update_time`) VALUES
(1, 1, 0, 'CRM 客服', 'https://chat.crmeb.net/uploads/attach/2021/09/20210906/c79d19dbfda66026ec891d188386cbb7.png', '15594500000', '', '202116257358989495', '', 0, 0, 0, 0, 0, '2021-07-20 10:43:56', '2021-07-20 10:43:56');

-- --------------------------------------------------------

--
-- 表的结构 `eb_chat_user_group`
--

CREATE TABLE IF NOT EXISTS `eb_chat_user_group` (
  `id` int(11) NOT NULL,
  `group_name` varchar(100) NOT NULL DEFAULT '' COMMENT '分组名称'
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COMMENT='用户分组';

-- --------------------------------------------------------

--
-- 表的结构 `eb_chat_user_label`
--

CREATE TABLE IF NOT EXISTS `eb_chat_user_label` (
  `id` int(11) NOT NULL,
  `label` varchar(100) NOT NULL DEFAULT '' COMMENT '标签名称',
  `user_id` int(10) NOT NULL DEFAULT '0' COMMENT '用户表自增ID',
  `cate_id` int(10) NOT NULL DEFAULT '0' COMMENT '标签分类',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COMMENT='用户标签';

-- --------------------------------------------------------

--
-- 表的结构 `eb_chat_user_label_assist`
--

CREATE TABLE IF NOT EXISTS `eb_chat_user_label_assist` (
  `id` int(11) NOT NULL,
  `label_id` int(10) NOT NULL DEFAULT '0' COMMENT '标签ID',
  `user_id` int(10) NOT NULL DEFAULT '0' COMMENT '用户表自增ID'
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COMMENT='用户标签辅助表';

-- --------------------------------------------------------

--
-- 表的结构 `eb_system_admin`
--

CREATE TABLE IF NOT EXISTS `eb_system_admin` (
  `id` int(11) NOT NULL,
  `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID,0=平台',
  `admin_type` tinyint(1) NOT NULL DEFAULT '2' COMMENT '管理员类型1=平台超管,2=租户管理员',
  `account` varchar(32) NOT NULL DEFAULT '' COMMENT '后台管理员账号',
  `head_pic` varchar(255) NOT NULL DEFAULT '' COMMENT '后台管理员头像',
  `pwd` varchar(100) NOT NULL DEFAULT '' COMMENT '后台管理员密码',
  `real_name` varchar(16) NOT NULL DEFAULT '' COMMENT '后台管理员姓名',
  `roles` varchar(128) NOT NULL DEFAULT '' COMMENT '后台管理员权限(menus_id)',
  `last_ip` varchar(16) NOT NULL DEFAULT '' COMMENT '后台管理员最后一次登录ip',
  `last_time` int(10) NOT NULL DEFAULT '0' COMMENT '后台管理员最后一次登录时间',
  `add_time` int(10) NOT NULL DEFAULT '0' COMMENT '后台管理员添加时间',
  `login_count` int(10) NOT NULL DEFAULT '0' COMMENT '登录次数',
  `level` int(3) NOT NULL DEFAULT '0' COMMENT '后台管理员级别',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '后台管理员状态 1有效0无效',
  `is_del` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否删除 1有效0无效'
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COMMENT='后台管理员表';

--
-- 转存表中的数据 `eb_system_admin`
--

INSERT INTO `eb_system_admin` (`id`, `tenant_id`, `admin_type`, `account`, `head_pic`, `pwd`, `real_name`, `roles`, `last_ip`, `last_time`, `add_time`, `login_count`, `level`, `status`, `is_del`) VALUES
(1, 0, 1, 'admin', '', '$2y$10$/BM3hGVZN2wq2gPXYIJZB.9YGwaTO/xM2NVz/k71dfWkmJpQCOGuS', '', '', '1.80.112.217', 1626775956, 0, 74, 0, 1, 0);

-- --------------------------------------------------------

--
-- 表的结构 `eb_system_attachment`
--

CREATE TABLE IF NOT EXISTS `eb_system_attachment` (
  `att_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '附件名称',
  `att_dir` varchar(200) NOT NULL DEFAULT '' COMMENT '附件路径',
  `satt_dir` varchar(200) NOT NULL DEFAULT '' COMMENT '压缩图片路径',
  `att_size` varchar(30) NOT NULL DEFAULT '' COMMENT '附件大小',
  `att_type` varchar(30) NOT NULL DEFAULT '' COMMENT '附件类型',
  `pid` int(10) NOT NULL DEFAULT '0' COMMENT '分类ID',
  `time` int(11) NOT NULL DEFAULT '0' COMMENT '上传时间',
  `image_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '图片上传类型 1本地 2七牛云 3OSS 4COS ',
  `module_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '图片上传模块类型 1 后台上传 2 用户生成',
  `real_name` varchar(255) NOT NULL DEFAULT '' COMMENT '原始文件名'
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COMMENT='附件管理表';

--
-- 转存表中的数据 `eb_system_attachment`
--

INSERT INTO `eb_system_attachment` (`att_id`, `name`, `att_dir`, `satt_dir`, `att_size`, `att_type`, `pid`, `time`, `image_type`, `module_type`, `real_name`) VALUES
(49, '客服头像1', 'https://demo40.crmeb.net/uploads/attach/2020/11/20201110/fcc758713087632dc785fff3d37db928.png', 'https://demo40.crmeb.net/uploads/attach/2020/11/20201110/fcc758713087632dc785fff3d37db928.png', '', '', 0, 0, 1, 1, '4'),
(50, '客服头像二', 'https://demo40.crmeb.net/uploads/attach/2020/11/20201110/d4398c5d36757c1b1ed1f21202bea1c0.png', 'https://demo40.crmeb.net/uploads/attach/2020/11/20201110/d4398c5d36757c1b1ed1f21202bea1c0.png', '', '', 0, 0, 1, 1, '3'),
(51, '客服头像三', 'https://demo40.crmeb.net/uploads/attach/2020/11/20201110/1b244797f8b86b4cc0665d75d160aa30.png', 'https://demo40.crmeb.net/uploads/attach/2020/11/20201110/1b244797f8b86b4cc0665d75d160aa30.png', '', '', 0, 0, 1, 1, '2'),
(52, '客服头像四', 'https://demo40.crmeb.net/uploads/attach/2020/11/20201110/1f05bd27a6af2da438dc2bb689995fc5.png', 'https://demo40.crmeb.net/uploads/attach/2020/11/20201110/1f05bd27a6af2da438dc2bb689995fc5.png', '', '', 0, 0, 1, 1, '1'),
(102, '1a00ec6542246a5828ad89df1b216275.png', '/uploads/attach/2021/09/20210906/1a00ec6542246a5828ad89df1b216275.png', '/uploads/attach/2021/09/20210906/1a00ec6542246a5828ad89df1b216275.png', '0', 'image/jpeg', 0, 1630891764, 1, 1, '客服图标.png'),
(103, '32645ce20cd8b945598d06bd2a31dd2a.png', '/uploads/attach/2021/09/20210906/32645ce20cd8b945598d06bd2a31dd2a.png', '/uploads/attach/2021/09/20210906/32645ce20cd8b945598d06bd2a31dd2a.png', '0', 'image/jpeg', 0, 1630891772, 1, 1, '白底图标.png'),
(104, 'c79d19dbfda66026ec891d188386cbb7.png', '/uploads/attach/2021/09/20210906/c79d19dbfda66026ec891d188386cbb7.png', '/uploads/attach/2021/09/20210906/c79d19dbfda66026ec891d188386cbb7.png', '0', 'image/jpeg', 0, 1630891871, 1, 1, '客服图标.png'),
(105, '6972cb96c04079eb1952ef43a04c6fbf.png', '/uploads/attach/2021/09/20210906/6972cb96c04079eb1952ef43a04c6fbf.png', '/uploads/attach/2021/09/20210906/6972cb96c04079eb1952ef43a04c6fbf.png', '0', 'image/jpeg', 0, 1630891891, 1, 1, '客服logo.png');

-- --------------------------------------------------------

--
-- 表的结构 `eb_system_attachment_category`
--

CREATE TABLE IF NOT EXISTS `eb_system_attachment_category` (
  `id` int(11) NOT NULL,
  `pid` int(10) NOT NULL DEFAULT '0' COMMENT '父级ID',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '分类名称',
  `enname` varchar(50) NOT NULL DEFAULT '' COMMENT '分类目录'
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COMMENT='附件分类表';

-- --------------------------------------------------------

--
-- 表的结构 `eb_system_config`
--

CREATE TABLE IF NOT EXISTS `eb_system_config` (
  `id` int(11) NOT NULL,
  `menu_name` varchar(255) NOT NULL DEFAULT '' COMMENT '字段名称',
  `type` varchar(255) NOT NULL DEFAULT '' COMMENT '类型(文本框,单选按钮...)',
  `input_type` varchar(20) NOT NULL DEFAULT 'input' COMMENT '表单类型',
  `config_tab_id` int(10) NOT NULL DEFAULT '0' COMMENT '配置分类id',
  `parameter` varchar(255) NOT NULL DEFAULT '' COMMENT '规则 单选框和多选框',
  `upload_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '上传文件格式1单图2多图3文件',
  `required` varchar(255) NOT NULL DEFAULT '' COMMENT '规则',
  `width` int(10) NOT NULL DEFAULT '0' COMMENT '多行文本框的宽度',
  `high` int(10) NOT NULL DEFAULT '0' COMMENT '多行文框的高度',
  `value` varchar(5000) NOT NULL DEFAULT '' COMMENT '默认值',
  `info` varchar(255) NOT NULL DEFAULT '' COMMENT '配置名称',
  `desc` varchar(255) NOT NULL DEFAULT '' COMMENT '配置简介',
  `sort` int(10) NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否隐藏'
) ENGINE=InnoDB AUTO_INCREMENT=375 DEFAULT CHARSET=utf8mb4 COMMENT='配置表';

--
-- 转存表中的数据 `eb_system_config`
--

INSERT INTO `eb_system_config` (`id`, `menu_name`, `type`, `input_type`, `config_tab_id`, `parameter`, `upload_type`, `required`, `width`, `high`, `value`, `info`, `desc`, `sort`, `status`) VALUES
(1, 'site_name', 'text', 'input', 1, '', 0, 'required:true', 100, 0, '"QiaLink 洽联"', '网站名称', '网站名称很多地方会显示的，建议认真填写', 10, 1),
(2, 'site_url', 'text', 'input', 1, '', 0, 'required:true,url:true', 100, 0, '""', '网站地址', '安装自动配置，不要轻易修改，更换会影响网站访问、接口请求、本地文件储存、支付回调、微信授权、支付、小程序图片访问、部分二维码、官方授权等', 5, 1),
(3, 'site_logo', 'upload', '', 1, '', 1, '', 0, 0, '"/statics/brand/qialink-logo-horizontal.png"', '后台大LOGO', '菜单展开左上角logo,建议尺寸[170*50]', 3, 1),
(5, 'seo_title', 'text', 'input', 1, '', 0, 'required:true', 100, 0, '"QiaLink 洽联 · 智能客户联络平台"', 'SEO标题', 'SEO标题', 0, 0),
(108, 'upload_type', 'radio', '', 31, '1=>本地存储\n2=>七牛云存储\n3=>阿里云OSS\n4=>腾讯COS', 1, '', 0, 0, '1', '上传类型', '文件储存配置，注意：一旦配置就不要轻易修改，会导致文件不能使用', 40, 1),
(109, 'uploadUrl', 'text', 'input', 32, '', 0, 'url:true', 100, 0, '""', '空间域名 Domain', '空间域名 Domain', 0, 1),
(110, 'accessKey', 'text', 'input', 32, '', 0, '', 100, 0, '""', 'AccessKey ID', 'AccessKey ID', 0, 1),
(111, 'secretKey', 'text', 'input', 32, '', 0, '', 100, 0, '""', 'AccessKey Secret', 'AccessKey Secret', 0, 1),
(112, 'storage_name', 'text', 'input', 32, '', 0, '', 100, 0, '""', 'Bucket', '存储空间名称', 0, 1),
(118, 'storage_region', 'text', 'input', 32, '', 0, '', 100, 0, '""', 'Endpoint', '所属地域', 0, 1),
(142, 'tengxun_map_key', 'text', 'input', 68, '', 0, '', 100, 0, '', '腾讯地图KEY', '腾讯地图KEY，申请地址：https://lbs.qq.com', 0, 1),
(144, 'cache_config', 'text', 'input', 1, '', 0, '', 100, 0, '"86400"', '网站缓存时间', '配置全局缓存时间（秒），默认留空为永久缓存', 0, 1),
(168, 'site_logo_square', 'upload', '', 1, '', 1, '', 0, 0, '"/statics/brand/qialink-logo-icon.png"', '后台小LOGO', '后台菜单缩进小LOGO，尺寸180*180', 1, 1),
(171, 'login_logo', 'upload', '', 1, '', 1, '', 0, 0, '"/statics/brand/qialink-logo-horizontal.png"', '后台登录页LOGO', '后台登录页LOGO，建议尺寸270x75', 4, 1),
(172, 'qiniu_uploadUrl', 'text', 'input', 33, '', 0, '', 100, 0, '""', '空间域名 Domain', '空间域名 Domain', 0, 1),
(173, 'qiniu_accessKey', 'text', 'input', 33, '', 0, '', 100, 0, '""', 'accessKey', 'accessKey', 0, 1),
(174, 'qiniu_secretKey', 'text', 'input', 33, '', 0, '', 100, 0, '""', 'secretKey', 'secretKey', 0, 1),
(175, 'qiniu_storage_name', 'text', 'input', 33, '', 0, '', 100, 0, '""', '空间名称', '存储空间名称', 0, 1),
(176, 'qiniu_storage_region', 'text', 'input', 33, '', 0, '', 100, 0, '""', '存储区域', '所属地域', 0, 1),
(177, 'tengxun_uploadUrl', 'text', 'input', 34, '', 0, '', 100, 0, '""', '空间域名 Domain', '空间域名 Domain', 0, 1),
(178, 'tengxun_accessKey', 'text', 'input', 34, '', 0, '', 100, 0, '""', 'SecretId', 'SecretId', 0, 1),
(179, 'tengxun_secretKey', 'text', '', 34, '', 0, '', 100, 0, '""', 'SecretKey', 'SecretKey', 0, 1),
(180, 'tengxun_storage_name', 'text', 'input', 34, '', 0, '', 100, 0, '""', '存储桶名称', '存储桶名称', 0, 1),
(181, 'tengxun_storage_region', 'text', 'input', 34, '', 0, '', 100, 0, '""', '所属地域', '所属地域', 0, 1),
(305, 'service_feedback', 'textarea', '', 69, '', 0, '', 100, 7, '"\\u5c0a\\u656c\\u7684\\u7528\\u6237\\uff0c\\u5ba2\\u670d\\u5f53\\u524d\\u4e0d\\u5728\\u7ebf\\uff0c\\u6709\\u95ee\\u9898\\u8bf7\\u7559\\u8a00\\uff0c\\u6211\\u4eec\\u4f1a\\u7b2c\\u4e00\\u65f6\\u95f4\\u8fdb\\u884c\\u5904\\u7406\\uff01\\uff01\\uff01"', '客服反馈', '客服反馈头部文字', 0, 1),
(306, 'tourist_avatar', 'upload', '', '69', '', '2', '', '0', '0', '[\"https:\\/\\/demo40.crmeb.net\\/uploads\\/attach\\/2020\\/11\\/20201110\\/1b244797f8b86b4cc0665d75d160aa30.png\",\"https:\\/\\/demo40.crmeb.net\\/uploads\\/attach\\/2020\\/11\\/20201110\\/d4398c5d36757c1b1ed1f21202bea1c0.png\",\"https:\\/\\/demo40.crmeb.net\\/uploads\\/attach\\/2020\\/11\\/20201110\\/fcc758713087632dc785fff3d37db928.png\",\"https:\\/\\/chat.crmeb.net\\/uploads\\/attach\\/2021\\/08\\/20210811\\/6ba99e3765d19fb35c23792b4143bb49.png\"]', '客服游客头像', '客服游客头像', '0', '1');

-- --------------------------------------------------------

--
-- 表的结构 `eb_system_config_tab`
--

CREATE TABLE IF NOT EXISTS `eb_system_config_tab` (
  `id` int(11) NOT NULL,
  `pid` int(10) NOT NULL DEFAULT '0' COMMENT '上级分类id',
  `title` varchar(255) NOT NULL DEFAULT '' COMMENT '配置分类名称',
  `eng_title` varchar(255) NOT NULL DEFAULT '' COMMENT '配置分类英文名称',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '配置分类状态',
  `info` tinyint(1) NOT NULL DEFAULT '0' COMMENT '配置分类是否显示',
  `icon` varchar(30) NOT NULL DEFAULT '' COMMENT '图标',
  `type` int(2) NOT NULL DEFAULT '0' COMMENT '配置类型',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序'
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COMMENT='配置分类表';

--
-- 转存表中的数据 `eb_system_config_tab`
--

INSERT INTO `eb_system_config_tab` (`id`, `pid`, `title`, `eng_title`, `status`, `info`, `icon`, `type`, `sort`) VALUES
(1, 0, '基础配置', 'basics', 1, 0, 'ios-settings', 0, 100),
(17, 0, '文件上传配置', 'upload_set', 1, 0, 'md-cloud-upload', 0, 0),
(31, 17, '基础配置', 'base_config', 1, 0, '', 0, 0),
(32, 17, '阿里云配置', 'aliyun_uploads', 1, 0, '', 0, 0),
(33, 17, '七牛云配置', 'qiniu_uplaods', 1, 0, '', 0, 0),
(34, 17, '腾讯云配置', 'tengxun_uploads', 1, 0, '', 0, 0),
(69, 22, '客服端配置', 'kefu_config', 1, 0, '', 0, 0);

-- --------------------------------------------------------

--
-- 表的结构 `eb_system_group`
--

CREATE TABLE IF NOT EXISTS `eb_system_group` (
  `id` int(11) NOT NULL,
  `cate_id` int(10) NOT NULL DEFAULT '0' COMMENT '分类id',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '数据组名称',
  `info` varchar(255) NOT NULL DEFAULT '' COMMENT '数据提示',
  `config_name` varchar(50) NOT NULL DEFAULT '' COMMENT '数据字段',
  `fields` text NOT NULL COMMENT '数据组字段以及类型（json数据）'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='配置分类表';

-- --------------------------------------------------------

--
-- 表的结构 `eb_system_group_data`
--

CREATE TABLE IF NOT EXISTS `eb_system_group_data` (
  `id` int(11) NOT NULL,
  `gid` int(10) NOT NULL DEFAULT '0' COMMENT '对应的数据组id',
  `value` text NOT NULL COMMENT '数据组对应的数据值（json数据）',
  `add_time` int(10) NOT NULL DEFAULT '0' COMMENT '添加数据时间',
  `sort` int(10) NOT NULL DEFAULT '0' COMMENT '数据排序',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态（1：开启；2：关闭；）'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='组合数据详情表';

-- --------------------------------------------------------

--
-- 表的结构 `eb_system_log`
--

CREATE TABLE IF NOT EXISTS `eb_system_log` (
  `id` int(11) NOT NULL,
  `admin_id` int(10) NOT NULL DEFAULT '0' COMMENT '管理员id',
  `admin_name` varchar(64) NOT NULL DEFAULT '' COMMENT '管理员姓名',
  `path` varchar(128) NOT NULL DEFAULT '' COMMENT '链接',
  `page` varchar(64) NOT NULL DEFAULT '' COMMENT '行为',
  `method` varchar(12) NOT NULL DEFAULT '' COMMENT '访问类型',
  `ip` varchar(16) NOT NULL DEFAULT '' COMMENT '登录IP',
  `type` varchar(32) NOT NULL DEFAULT '' COMMENT '类型',
  `add_time` int(10) NOT NULL DEFAULT '0' COMMENT '操作时间',
  `merchant_id` int(10) NOT NULL DEFAULT '0' COMMENT '商户id'
) ENGINE=InnoDB AUTO_INCREMENT=7947 DEFAULT CHARSET=utf8mb4 COMMENT='管理员操作记录表';

--
-- 转存表中的数据 `eb_system_log`
--
-- --------------------------------------------------------

--
-- 表的结构 `eb_system_menus`
--

CREATE TABLE IF NOT EXISTS `eb_system_menus` (
  `id` int(10) NOT NULL,
  `pid` int(10) NOT NULL DEFAULT '0' COMMENT '父级id',
  `icon` varchar(16) NOT NULL DEFAULT '' COMMENT '图标',
  `menu_name` varchar(32) NOT NULL DEFAULT '' COMMENT '按钮名',
  `module` varchar(32) NOT NULL DEFAULT '' COMMENT '模块名',
  `controller` varchar(64) NOT NULL DEFAULT '' COMMENT '控制器',
  `action` varchar(32) NOT NULL DEFAULT '' COMMENT '方法名',
  `api_url` varchar(100) NOT NULL DEFAULT '' COMMENT 'api接口地址',
  `methods` varchar(255) NOT NULL DEFAULT '' COMMENT '提交方式POST GET PUT DELETE',
  `params` varchar(128) NOT NULL DEFAULT '' COMMENT '参数',
  `sort` int(10) NOT NULL DEFAULT '0' COMMENT '排序',
  `is_show` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否为隐藏菜单0=隐藏菜单,1=显示菜单',
  `is_show_path` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否为隐藏菜单供前台使用',
  `access` tinyint(1) NOT NULL DEFAULT '0' COMMENT '子管理员是否可用',
  `menu_path` varchar(255) NOT NULL DEFAULT '' COMMENT '路由名称 前端使用',
  `path` varchar(255) NOT NULL DEFAULT '' COMMENT '路径',
  `auth_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否为菜单 1菜单 2功能',
  `header` varchar(32) NOT NULL DEFAULT '' COMMENT '顶部菜单标示',
  `is_header` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否顶部菜单1是0否',
  `unique_auth` varchar(255) NOT NULL DEFAULT '' COMMENT '前台唯一标识',
  `is_del` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否删除',
  `is_tenant` tinyint(1) NOT NULL DEFAULT '1' COMMENT '租户侧可用1=可用,0=平台专属'
) ENGINE=InnoDB AUTO_INCREMENT=1094 DEFAULT CHARSET=utf8mb4 COMMENT='菜单表';

--
-- 转存表中的数据 `eb_system_menus`
--
INSERT INTO `eb_system_menus` (`id`, `pid`, `icon`, `menu_name`, `module`, `controller`, `action`, `api_url`, `methods`, `params`, `sort`, `is_show`, `is_show_path`, `access`, `menu_path`, `path`, `auth_type`, `header`, `is_header`, `unique_auth`, `is_del`) VALUES
(7, 0, 'md-home', '统计', 'admin', 'index', '', '', '', '[]', 127, 1, 0, 1, '/admin/home/', '', 1, 'home', 1, 'admin-index-index', 0),
(9, 0, 'md-person', '用户管理', 'admin', 'user.user', '', '', '', '[]', 100, 1, 0, 1, '/admin/user', '', 1, 'user', 1, 'admin-user', 0),
(10, 9, '', '用户列表', 'admin', 'user.user', 'index', '', '', '[]', 10, 1, 0, 1, '/admin/user/list', '9', 1, 'user', 1, 'admin-user-user-index', 0),
(12, 0, 'md-settings', '设置管理', 'admin', 'setting.system_config', 'index', '', '', '[]', 0, 1, 0, 1, '/admin/setting', '', 1, 'setting', 1, 'admin-setting', 0),
(14, 12, '', '管理权限', 'admin', 'setting.system_admin', '', '', '', '[]', 0, 1, 0, 1, '/admin/setting/auth/list', '', 1, 'setting', 1, 'setting-system-admin', 0),
(19, 14, '', '角色管理', 'admin', 'setting.system_role', 'index', '', '', '[]', 1, 1, 0, 1, '/admin/setting/system_role/index', '', 1, 'setting', 1, 'setting-system-role', 0),
(20, 14, '', '管理员列表', 'admin', 'setting.system_admin', 'index', '', '', '[]', 1, 1, 0, 1, '/admin/setting/system_admin/index', '', 1, 'setting', 0, 'setting-system-list', 0),
(21, 14, '', '权限规则', 'admin', 'setting.system_menus', 'index', '', '', '[]', 1, 1, 0, 1, '/admin/setting/system_menus/index', '', 1, 'setting', 0, 'setting-system-menus', 0),
(23, 12, '', '系统设置', 'admin', 'setting.system_config', 'index', '', '', '[]', 10, 1, 0, 1, '/admin/setting/system_config', '', 1, 'setting', 1, 'setting-system-config', 0),
(25, 0, 'md-hammer', '维护管理', 'admin', 'system', '', '', '', '[]', -1, 1, 0, 1, '/admin/system', '', 1, 'setting', 1, 'admin-system', 0),
(47, 65, '', '系统日志', 'admin', 'system.system_log', 'index', '', '', '[]', 0, 1, 0, 1, '/admin/system/maintain/system_log/index', '', 1, 'system', 0, 'system-maintain-system-log', 0),
(48, 7, '', '控制台', 'admin', 'index', 'index', '', '', '[]', 127, 1, 0, 1, '/admin/home/index', '', 1, 'home', 0, '', 1),
(56, 25, '', '开发配置', 'admin', 'system', '', '', '', '[]', 10, 1, 0, 1, '/admin/system/config', '', 1, 'system', 1, 'system-config-index', 0),
(65, 25, '', '安全维护', 'admin', 'system', '', '', '', '[]', 7, 1, 0, 1, '/admin/system/maintain', '', 1, 'system', 1, 'system-maintain-index', 0),
(111, 56, '', '配置分类', 'admin', 'setting.system_config_tab', 'index', '', '', '[]', 0, 1, 0, 1, '/admin/system/config/system_config_tab/index', '', 1, 'system', 0, 'system-config-system_config-tab', 0),
(112, 56, '', '组合数据', 'admin', 'setting.system_group', 'index', '', '', '[]', 0, 1, 0, 1, '/admin/system/config/system_group/index', '', 1, 'system', 0, 'system-config-system_config-group', 0),
(125, 56, '', '配置列表', 'admin', 'system.config', 'index', '', '', '[]', 0, 1, 1, 1, '/admin/system/config/system_config_tab/list', '', 1, 'system', 1, 'system-config-system_config_tab-list', 0),
(126, 56, '', '组合数据列表', 'admin', 'system.system_group', 'list', '', '', '[]', 0, 1, 1, 1, '/admin/system/config/system_group/list', '', 1, 'system', 1, 'system-config-system_config-list', 0),
(165, 0, 'md-chatboxes', '客服管理', 'admin', 'setting.storeService', 'index', '', '', '[]', 2, 1, 0, 1, '/admin/kefu', '', 1, '', 0, 'setting-store-service', 0),
(227, 9, '', '用户分组', 'admin', 'user.user_group', 'index', '', '', '[]', 9, 1, 0, 1, '/admin/user/group', '9', 1, 'user', 1, 'user-user-group', 0),
(313, 23, '', '基本配置编辑头部数据', 'admin', '', '', 'api/admin/setting/config/header_basics', 'GET', '[]', 0, 0, 0, 1, '', '12/23', 2, '', 0, '', 0),
(314, 23, '', '基本配置编辑表单', 'admin', '', '', 'api/admin/setting/config/edit_basics', 'GET', '[]', 0, 0, 0, 1, '', '12/23', 2, '', 0, '', 0),
(315, 23, '', '基本配置保存数据', 'admin', '', '', 'api/admin/setting/config/save_basics', 'POST', '[]', 0, 0, 0, 1, '', '12/23', 2, '', 0, '', 0),
(325, 19, '', '添加身份', 'admin', '', '', '', '', '[]', 0, 0, 0, 1, '/admin/setting/system_role/add', '', 1, '', 0, 'setting-system_role-add', 0),
(326, 325, '', '管理员身份权限列表', 'admin', '', '', 'api/admin/setting/role/create', 'GET', '[]', 0, 0, 0, 1, '', '12/14/19/325', 2, '', 0, '', 0),
(327, 325, '', '新建或编辑管理员', 'admin', '', '', 'api/admin/setting/role/<id>', 'POST', '[]', 0, 0, 0, 1, '', '12/14/19/325', 2, '', 0, '', 0),
(328, 325, '', '编辑管理员详情', 'admin', '', '', 'api/admin/setting/role/<id>/edit', 'GET', '[]', 0, 0, 0, 1, '', '12/14/19/325', 2, '', 0, '', 0),
(329, 19, '', '修改管理员身份状态', 'admin', '', '', 'api/admin/setting/role/set_status/<id>/<status>', 'PUT', '[]', 0, 0, 0, 1, '', '12/14/19', 2, '', 0, '', 0),
(330, 19, '', '删除管理员身份', 'admin', '', '', 'api/admin/setting/role/<id>', 'DELETE', '[]', 0, 0, 0, 1, '', '12/14/19', 2, '', 0, '', 0),
(331, 20, '', '添加管理员', 'admin', '', '', '', '', '[]', 0, 0, 0, 1, '/admin/setting/system_admin/add', '', 1, '', 0, 'setting-system_admin-add', 0),
(332, 331, '', '添加管理员表单', 'admin', '', '', 'api/admin/setting/admin/create', 'GET', '[]', 0, 0, 0, 1, '', '12/14/20/331', 2, '', 0, '', 0),
(333, 331, '', '添加管理员', 'admin', '', '', 'api/admin/setting/admin', 'POST', '[]', 0, 0, 0, 1, '', '12/14/20/331', 2, '', 0, '', 0),
(334, 20, '', '编辑管理员', 'admin', '', '', '', '', '[]', 0, 0, 0, 1, '/admin /setting/system_admin/edit', '', 1, '', 0, ' setting-system_admin-edit', 0),
(335, 334, '', '编辑管理员表单', 'admin', '', '', 'api/admin/setting/admin/<id>/edit', 'GET', '[]', 0, 0, 0, 1, '', '12/14/20/334', 2, '', 0, '', 0),
(336, 334, '', '修改管理员', 'admin', '', '', 'api/admin/setting/admin/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '12/14/20/334', 2, '', 0, '', 0),
(337, 20, '', '修改管理员接口', 'admin', '', '', 'api/admin/setting/admin/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '12/14/20', 2, '', 0, '', 0),
(338, 21, '', '添加规则', 'admin', '', '', '', '', '[]', 0, 0, 0, 1, '/admin/setting/system_menus/add', '', 1, '', 0, 'setting-system_menus-add', 0),
(339, 338, '', '添加权限表单', 'admin', '', '', 'api/admin/setting/menus/create', 'GET', '[]', 0, 0, 0, 1, '', '', 2, '', 0, '', 0),
(340, 338, '', '添加权限', 'admin', '', '', 'api/admin/setting/menus', 'POST', '[]', 0, 0, 0, 1, '', '12/14/21/338', 2, '', 0, '', 0),
(341, 21, '', '修改权限', 'admin', 'setting.system_menus', 'edit', '', '', '[]', 0, 0, 0, 1, '/admin/setting/system_menus/edit', '', 1, '', 0, '/setting-system_menus-edit', 0),
(342, 341, '', '编辑权限表单', 'admin', '', '', 'api/admin/setting/menus/<id>', 'GET', '[]', 0, 0, 0, 1, '', '12/14/21/341', 2, '', 0, '', 0),
(343, 341, '', '修改权限', 'admin', '', '', 'api/admin/setting/menus/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '12/14/21/341', 2, '', 0, '', 0),
(344, 21, '', '修改权限状态', 'admin', '', '', 'api/admin/setting/menus/show/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '12/14/21', 2, '', 0, '', 0),
(345, 21, '', '删除权限菜单', 'admin', '', '', 'api/admin/setting/menus/<id>', 'DELETE', '[]', 0, 0, 0, 1, '', '12/14/21', 2, '', 0, '', 0),
(346, 338, '', '添加子菜单', 'admin', 'setting.system_menus', 'add', '', '', '[]', 0, 0, 0, 1, '/admin/setting/system_menus/add_sub', '', 1, '', 0, 'setting-system_menus-add_sub', 0),
(423, 678, '', '附加权限', 'admin', '', '', '', '', '[]', 0, 0, 0, 1, '/admin*', '', 1, '', 0, '', 0),
(461, 111, '', '配置分类列表', 'admin', '', '', 'api/admin/setting/config_class', 'GET', '[]', 0, 0, 0, 1, '', '25/56/111', 2, '', 0, '', 0),
(462, 111, '', '附加权限', 'admin', '', '', '', '', '[]', 0, 0, 0, 1, '/admin*', '', 1, '', 0, '', 0),
(463, 462, '', '配置分类添加表单', 'admin', '', '', 'api/admin/setting/config_class/create', 'GET', '[]', 0, 0, 0, 1, '', '25/56/111/462', 2, '', 0, '', 0),
(464, 462, '', '保存配置分类', 'admin', '', '', 'api/admin/setting/config_class', 'POST', '[]', 0, 0, 0, 1, '', '', 2, '', 0, '', 0),
(465, 641, '', '编辑配置分类', 'admin', '', '', 'api/admin/setting/config_class/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '', 2, '', 0, '', 0),
(466, 462, '', '删除配置分类', 'admin', '', '', 'api/admin/setting/config_class/<id>', 'DELETE', '[]', 0, 0, 0, 1, '', '', 2, '', 0, '', 0),
(467, 125, '', '配置列表展示', 'admin', '', '', 'api/admin/setting/config', 'GET', '[]', 0, 0, 0, 1, '', '', 2, '', 0, '', 0),
(468, 125, '', '附加权限', 'admin', '', '', '', '', '[]', 0, 0, 0, 1, '/admin*', '', 1, '', 0, '', 0),
(469, 468, '', '添加配置字段表单', 'admin', '', '', 'api/admin/setting/config/create', 'GET', '[]', 0, 0, 0, 1, '', '', 2, '', 0, '', 0),
(470, 468, '', '保存配置字段', 'admin', '', '', 'api/admin/setting/config', 'POST', '[]', 0, 0, 0, 1, '', '', 2, '', 0, '', 0),
(471, 468, '', '编辑配置字段表单', 'admin', '', '', 'api/admin/setting/config/<id>/edit', '', '[]', 0, 0, 0, 1, '', '', 2, '', 0, '', 0),
(472, 468, '', '编辑配置分类', 'admin', '', '', 'api/admin/setting/config/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '', 2, '', 0, '', 0),
(473, 468, '', '删除配置', 'admin', '', '', 'api/admin/setting/config/<id>', 'DELETE', '[]', 0, 0, 0, 1, '', '', 2, '', 0, '', 0),
(474, 468, '', '修改配置状态', 'admin', '', '', 'api/admin/setting/config/set_status/<id>/<status>', 'PUT', '[]', 0, 0, 0, 1, '', '', 2, '', 0, '', 0),
(475, 112, '', '组合数据列表', 'admin', '', '', 'api/admin/setting/group', 'GET', '[]', 0, 1, 0, 1, '', '', 2, '', 0, '', 0),
(476, 112, '', '附加权限', 'admin', '', '', '', '', '[]', 0, 0, 0, 1, '/admin*', '', 1, '', 0, '', 0),
(477, 476, '', '新增组合数据', 'admin', '', '', 'api/admin/setting/group', 'POST', '[]', 0, 0, 0, 1, '', '', 2, '', 0, '', 0),
(478, 476, '', '获取组合数据', 'admin', '', '', 'api/admin/setting/group/<id>', 'GET', '[]', 0, 0, 0, 1, '', '', 2, '', 0, '', 0),
(479, 476, '', '修改组合数据', 'admin', '', '', 'api/admin/setting/group/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '', 2, '', 0, '', 0),
(480, 476, '', '删除组合数据', 'admin', '', '', 'api/admin/setting/group/<id>', 'DELETE', '[]', 0, 0, 0, 1, '', '', 2, '', 0, '', 0),
(481, 126, '', '组合数据列表表头', 'admin', '', '', 'api/admin/setting/group_data/header', 'GET', '[]', 0, 0, 0, 1, '', '', 2, '', 0, '', 0),
(482, 126, '', '组合数据列表', 'admin', '', '', 'api/admin/setting/group_data', 'GET', '[]', 0, 0, 0, 1, '', '', 2, '', 0, '', 0),
(483, 126, '', '附加权限', 'admin', '', '', '', '', '[]', 0, 0, 0, 1, '/admin*', '', 1, '', 0, '', 0),
(484, 483, '', '获取组合数据添加表单', 'admin', '', '', 'api/admin/setting/group_data/create', 'GET', '[]', 0, 0, 0, 1, '', '', 2, '', 0, '', 0),
(485, 483, '', '保存组合数据', 'admin', '', '', 'api/admin/setting/group_data', 'POST', '[]', 0, 0, 0, 1, '', '', 2, '', 0, '', 0),
(486, 483, '', '获取组合数据信息', 'admin', '', '', 'api/admin/setting/group_data/<id>/edit', 'GET', '[]', 0, 0, 0, 1, '', '', 2, '', 0, '', 0),
(487, 483, '', '修改组合数据信息', 'admin', '', '', 'api/admin/setting/group_data/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '', 2, '', 0, '', 0),
(488, 483, '', '删除组合数据', 'admin', '', '', 'api/admin/setting/group_data/<id>', 'DELETE', '[]', 0, 0, 0, 1, '', '', 2, '', 0, '', 0),
(489, 483, '', '修改组合数据状态', 'admin', '', '', 'api/admin/setting/group_data/set_status/<id>/<status>', 'PUT', '[]', 0, 0, 0, 1, '', '', 2, '', 0, '', 0),
(492, 47, '', '系统日志管理员搜索条件', 'admin', '', '', 'api/admin/system/log/search_admin', 'GET', '[]', 0, 0, 0, 1, '', '25/65/47', 2, '', 0, '', 0),
(493, 47, '', '系统日志', 'admin', '', '', 'api/admin/system/log', 'GET', '[]', 0, 0, 0, 1, '', '25/65/47', 2, '', 0, '', 0),
(585, 10, '', '附加权限', 'admin', '', '', '', '', '[]', 0, 0, 0, 1, '/admin*', '', 1, '', 0, '', 0),
(610, 20, '', '管理员列表', 'admin', '', '', 'api/admin/setting/admin', 'GET', '[]', 0, 0, 0, 1, '', '12/14/20', 2, '', 0, '', 0),
(611, 19, '', '管理员身份列表', 'admin', '', '', 'api/admin/setting/role', 'GET', '[]', 0, 0, 0, 1, '', '12/14/19', 2, '', 0, '', 0),
(619, 21, '', '权限列表', 'admin', '', '', 'api/admin/setting/menus', 'GET', '[]', 0, 0, 0, 1, '', '12/14/21', 2, '', 0, '', 0),
(635, 20, '', '修改管理员状态', 'admin', '', '', 'api/admin/setting/set_status/<id>/<status>', 'PUT', '[]', 0, 0, 0, 1, '', '12/14/20', 2, '', 0, '', 0),
(641, 462, '', '编辑配置分类', 'admin', '', '', '', '', '[]', 0, 0, 0, 1, 'system/config/system_config_tab/edit', '', 1, '', 0, '', 0),
(642, 641, '', '获取修改配置分类接口', 'admin', '', '', 'api/admin/setting/config_class/<id>/edit', 'GET', '[]', 0, 0, 0, 1, '', '25/56/111/462/641', 2, '', 0, '', 0),
(656, 12, '', '页面管理', 'admin', '', '', '', '', '[]', 1, 1, 0, 1, '/admin/setting/pages', '', 1, '', 0, 'admin-setting-pages', 0),
(678, 165, '', '客服列表', 'admin', '', '', '', '', '[]', 0, 1, 0, 1, '/admin/setting/store_service/index', '', 1, '', 0, 'admin-setting-store_service-index', 0),
(679, 165, '', '客服话术', 'admin', '', '', '', '', '[]', 0, 1, 0, 1, '/admin/setting/store_service/speechcraft', '', 1, '', 0, 'admin-setting-store_service-speechcraft', 0),
(738, 165, '', '用户留言', 'admin', '', '', '', '', '[]', 0, 1, 0, 1, '/admin/setting/store_service/feedback', '', 1, '', 0, 'admin-setting-store_service-feedback', 0),
(739, 738, '', '获取用户反馈列表接口', 'admin', '', '', 'api/admin/chat/feedback', 'GET', '[]', 0, 0, 0, 1, '', '165/738', 2, '', 0, '', 0),
(740, 738, '', '附件权限', 'admin', '', '', '', '', '[]', 0, 0, 0, 1, '*', '', 1, '', 0, '', 0),
(741, 740, '', '删除用户反馈接口', 'admin', '', '', 'api/admin/chat/feedback/<id>', 'DELETE', '[]', 0, 0, 0, 1, '', '165/738/740', 2, '', 0, '', 0),
(742, 679, '', '获取话术列表接口', 'admin', '', '', 'api/admin/chat/speechcraft', 'GET', '[]', 0, 0, 0, 1, '', '165/679', 2, '', 0, '', 0),
(743, 679, '', '附件权限', 'admin', '', '', '', '', '[]', 0, 0, 0, 1, '*', '', 1, '', 0, '', 0),
(745, 743, '', '编辑话术', 'admin', '', '', '', '', '[]', 0, 0, 0, 1, '/admin/setting/store_service/speechcraft/edit', '', 1, '', 0, 'admin-setting-store_service-speechcraft-edit', 0),
(748, 745, '', '获取话术创建接口', 'admin', '', '', 'api/admin/chat/speechcraft/create', 'GET', '[]', 0, 0, 0, 1, '', '165/679/743/745', 2, '', 0, '', 0),
(749, 745, '', '修改话术接口', 'admin', '', '', 'api/admin/chat/speechcraft/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '165/679/743/745', 2, '', 0, '', 0),
(750, 743, '', '删除话术接口', 'admin', '', '', 'api/admin/chat/speechcraft/<id>', 'DELETE', '[]', 0, 0, 0, 1, '', '165/679/743', 2, '', 0, '', 0),
(778, 740, '', '修改用户反馈接口', 'admin', '', '', 'api/admin/chat/feedback/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '165/738/740', 2, '', 0, '', 0),
(779, 740, '', '获取修改用户反馈接口', 'admin', '', '', 'api/admin/chat/feedback/<id>/edit', 'GET', '[]', 0, 0, 0, 1, '', '165/738/740', 2, '', 0, '', 0),
(789, 743, '', '话术分类', 'admin', '', '', '', '', '[]', 0, 0, 0, 1, '/admin/setting/store_service/speechcraft/cate', '', 1, '', 0, 'admin-setting-store_service-speechcraft-cate', 0),
(790, 789, '', '获取话术分类列表接口', 'admin', '', '', 'api/admin/chat/speechcraftcate', 'GET', '[]', 0, 0, 0, 1, '', '165/679/743/789', 2, '', 0, '', 0),
(791, 789, '', '添加话术分类', 'admin', '', '', '', '', '[]', 0, 0, 0, 1, '/admin/setting/store_service/speechcraft/cate/create', '', 1, '', 0, '', 0),
(792, 791, '', '获取话术分类创建接口', 'admin', '', '', 'api/admin/chat/speechcraftcate/create', 'GET', '[]', 0, 0, 0, 1, '', '165/679/743/789/791', 2, '', 0, '', 0),
(793, 791, '', '保存话术分类接口', 'admin', '', '', 'api/admin/chat/speechcraftcate', 'POST', '[]', 0, 0, 0, 1, '', '165/679/743/789/791', 2, '', 0, '', 0),
(794, 795, '', '获取修改话术分类接口', 'admin', '', '', 'api/admin/chat/speechcraftcate/<id>/edit', 'GET', '[]', 0, 0, 0, 1, 'app/wechat/speechcraftcate/<id>/edit', '165/679/743/789/795', 2, '', 0, '', 0),
(795, 789, '', '修改话术分类', 'admin', '', '', '', '', '[]', 0, 0, 0, 1, '/admin/setting/store_service/speechcraft/cate/edit', '', 1, '', 0, '', 0),
(796, 795, '', '修改话术分类接口', 'admin', '', '', 'api/admin/chat/speechcraftcate/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '165/679/743/789/795', 2, '', 0, '', 0),
(797, 789, '', '删除话术分类接口', 'admin', '', '', 'api/admin/chat/speechcraftcate/<id>', 'DELETE', '[]', 0, 0, 0, 1, '', '165/679/743/789', 2, '', 0, '', 0),
(913, 656, '', '客服页面广告', 'admin', '', '', '', '', '[]', 0, 1, 0, 1, '/admin/setting/system_group_data/kf_adv', '', 1, '', 0, 'setting-system-group_data-kf_adv', 0),
(915, 913, '', '设置客服广告', 'admin', '', '', 'api/admin/setting/set_kf_adv', 'POST', '[]', 0, 0, 0, 1, '', '12/656/913', 2, '', 0, 'adminapi-setting-set_kf_adv', 0),
(916, 913, '', '获取客服广告', 'admin', '', '', 'api/admin/setting/get_kf_adv', 'GET', '[]', 0, 0, 0, 1, '', '12/656/913', 2, '', 0, 'adminapi-setting-get_kf_adv', 0),
(1008, 9, '', '用户标签', 'admin', '', '', '', '', '[]', 0, 1, 0, 1, '/admin/user/label', '9', 1, '', 0, 'user-user-label', 0),
(1009, 1008, '', '获取添加标签分类表单', 'admin', '', '', '/api/admin/user/label/cate/create', 'GET', '[]', 0, 0, 0, 1, '', '9/1008', 2, '', 0, 'admin-user-label_add', 0),
(1011, 12, '', '代码获取', 'admin', '', '', '', '', '[]', 0, 1, 0, 1, '/admin/system/code', '12', 1, '', 0, 'admin-system-code', 0),
(1012, 7, '', '客户统计', 'admin', '', '', 'api/admin/chart/sum', 'GET', '[]', 0, 1, 0, 1, '', '7', 2, '', 0, '', 0),
(1013, 7, '', '客户首页统计', 'admin', '', '', 'api/admin/chart', 'GET', '[]', 0, 1, 0, 1, '', '7', 2, '', 0, '', 0),
(1014, 585, '', '获取修改用户表单', 'admin', '', '', 'api/admin/user/edit/<id>', 'GET', '[]', 0, 0, 0, 1, '', '9/10/585', 2, '', 0, '', 0),
(1015, 585, '', '修改用户', 'admin', '', '', 'api/admin/user/update/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '9/10/585', 2, '', 0, '', 0),
(1016, 585, '', '用户列表', 'admin', '', '', 'api/admin/user/index', 'GET', '[]', 0, 0, 0, 1, '', '9/10/585', 2, '', 0, '', 0),
(1018, 585, '', '批量修改用户分组', 'admin', '', '', 'api/admin/user/batch/group', 'PUT', '[]', 0, 0, 0, 1, '', '9/10/585', 2, '', 0, 'admin-user-group_set', 0),
(1019, 585, '', '获取全部分组', 'admin', '', '', 'api/admin/user/group/all', 'GET', '[]', 0, 0, 0, 1, '', '9/10/585', 2, '', 0, '', 0),
(1020, 227, '', '获取分组列表接口', 'admin', '', '', 'api/admin/user/group', 'GET', '[]', 0, 0, 0, 1, '', '9/227', 2, '', 0, 'admin-user-group', 0),
(1021, 227, '', '保存分组接口', 'admin', '', '', 'api/admin/user/group', 'POST', '[]', 0, 0, 0, 1, '', '9/227', 2, '', 0, '', 0),
(1022, 227, '', '获取分组创建接口', 'admin', '', '', 'api/admin/user/group/create', 'GET', '[]', 0, 0, 0, 1, '', '9/227', 2, '', 0, '', 0),
(1023, 227, '', '获取修分组签接口', 'admin', '', '', 'api/admin/user/group/<id>/edit', 'GET', '[]', 0, 0, 0, 1, '', '9/227', 2, '', 0, '', 0),
(1024, 227, '', '修改分组接口', 'admin', '', '', 'api/admin/user/group/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '9/227', 2, '', 0, '', 0),
(1025, 227, '', '删除分组接口', 'admin', '', '', 'api/admin/user/group/<id>', 'DELETE', '[]', 0, 0, 0, 1, '', '9/227', 2, '', 0, '', 0),
(1026, 1008, '', '删除标签分类接口', 'admin', '', '', 'api/admin/user/label/cate/<id>', 'DELETE', '[]', 0, 0, 0, 1, '', '9/1008', 2, '', 0, '', 0),
(1027, 1008, '', '获取标签分类列表接口', 'admin', '', '', 'api/admin/user/label/cate', 'GET', '[]', 0, 0, 0, 1, '', '9/1008', 2, '', 0, '', 0),
(1028, 1008, '', '获取修改标签分类接口', 'admin', '', '', 'api/admin/user/label/cate/<id>/edit', 'GET', '[]', 0, 0, 0, 1, '', '9/1008', 2, '', 0, '', 0),
(1029, 1008, '', '保存标签分类接口', 'admin', '', '', 'api/admin/user/label/cate', 'POST', '[]', 0, 0, 0, 1, '', '9/1008', 2, '', 0, '', 0),
(1030, 1008, '', '获取标签创建接口', 'admin', '', '', 'api/admin/user/label/create', 'GET', '[]', 0, 0, 0, 1, '', '9/1008', 2, '', 0, '', 0),
(1031, 1008, '', '获取标签分类创建接口', 'admin', '', '', 'api/admin/user/label/cate/create', 'GET', '[]', 0, 0, 0, 1, '', '9/1008', 2, '', 0, '', 0),
(1032, 1008, '', '删除标签接口', 'admin', '', '', 'api/admin/user/label/<id>', 'DELETE', '[]', 0, 0, 0, 1, '', '9/1008', 2, '', 0, '', 0),
(1033, 1008, '', '获取修改标签接口', 'admin', '', '', 'api/admin/user/label/<id>/edit', 'GET', '[]', 0, 0, 0, 1, '', '9/1008', 2, '', 0, '', 0),
(1034, 1008, '', '修改标签分类接口', 'admin', '', '', 'api/admin/user/label/cate/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '9/1008', 2, '', 0, '', 0),
(1035, 1008, '', '修改标签接口', 'admin', '', '', 'api/admin/user/label/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '9/1008', 2, '', 0, '', 0),
(1036, 1008, '', '保存标签接口', 'admin', '', '', 'api/admin/user/label', 'POST', '[]', 0, 0, 0, 1, '', '9/1008', 2, '', 0, '', 0),
(1037, 1008, '', '获取标签列表接口', 'admin', '', '', 'api/admin/user/label', 'GET', '[]', 0, 0, 0, 1, '', '9/1008', 2, '', 0, '', 0),
(1038, 585, '', '获取全部标签', 'admin', '', '', 'api/admin/user/label/all', 'GET', '[]', 0, 0, 0, 1, '', '9/10/585', 2, '', 0, 'admin-user-set_label', 0),
(1039, 585, '', '批量修改用户标签', 'admin', '', '', 'api/admin/user/batch/label', 'PUT', '[]', 0, 0, 0, 1, '', '9/10/585', 2, '', 0, '', 0),
(1040, 7, '', '获取logo', 'admin', '', '', 'api/admin/logo', 'GET', '[]', 0, 1, 0, 1, '', '7', 2, '', 0, '', 0),
(1041, 7, '', '消息通知', 'admin', '', '', 'api/admin/jnotice', 'GET', '[]', 0, 1, 0, 1, '', '7', 2, '', 0, '', 0),
(1042, 7, '', '获取菜单', 'admin', '', '', 'api/admin/menusList', 'GET', '[]', 0, 1, 0, 1, '', '7', 2, '', 0, '', 0),
(1043, 1011, '', '获取应用列表接口', 'admin', '', '', 'api/admin/app', 'GET', '[]', 0, 0, 0, 1, '', '12/1011', 2, '', 0, '', 0),
(1044, 1011, '', '保存应用接口', 'admin', '', '', 'api/admin/app', 'POST', '[]', 0, 0, 0, 1, '', '12/1011', 2, '', 0, '', 0),
(1045, 1011, '', '获取应用创建接口', 'admin', '', '', 'api/admin/app/create', 'GET', '[]', 0, 0, 0, 1, '', '12/1011', 2, '', 0, '', 0),
(1046, 1011, '', '获取修改应用接口', 'admin', '', '', 'api/admin/app/<id>/edit', 'GET', '[]', 0, 0, 0, 1, '', '12/1011', 2, '', 0, '', 0),
(1047, 1011, '', '修改应用接口', 'admin', '', '', 'api/admin/app/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '12/1011', 2, '', 0, '', 0),
(1048, 1011, '', '删除应用接口', 'admin', '', '', 'api/admin/app/<id>', 'DELETE', '[]', 0, 0, 0, 1, '', '12/1011', 2, '', 0, '', 0),
(1049, 678, '', '客服列表', 'admin', '', '', 'api/admin/chat/kefu', 'GET', '[]', 0, 0, 0, 1, '', '165/678', 2, '', 0, 'admin-user-group', 0),
(1050, 423, '', '添加客服', 'admin', '', '', 'api/admin/chat/kefu', 'POST', '[]', 0, 0, 0, 1, '', '165/678/423', 2, '', 0, '', 0),
(1051, 423, '', '客服登录', 'admin', '', '', 'api/admin/chat/kefu/login/<id>', 'GET', '[]', 0, 0, 0, 1, '', '165/678/423', 2, '', 0, '', 0),
(1052, 423, '', '添加客服表单', 'admin', '', '', 'api/admin/chat/kefu/add', 'GET', '[]', 0, 0, 0, 1, '', '165/678/423', 2, '', 0, 'setting-store_service-add', 0),
(1053, 423, '', '修改客服表单', 'admin', '', '', 'api/admin/chat/kefu/<id>/edit', 'GET', '[]', 0, 0, 0, 1, '', '165/678/423', 2, '', 0, '', 0),
(1054, 423, '', '修改客服', 'admin', '', '', 'api/admin/chat/kefu/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '165/678/423', 2, '', 0, '', 0),
(1055, 423, '', '删除客服', 'admin', '', '', 'api/admin/chat/kefu/<id>', 'DELETE', '[]', 0, 0, 0, 1, '', '165/678/423', 2, '', 0, '', 0),
(1056, 423, '', '修改客服状态', 'admin', '', '', 'api/admin/chat/kefu/set_status/<id>/<status>', 'PUT', '[]', 0, 0, 0, 1, '', '165/678/423', 2, '', 0, '', 0),
(1057, 423, '', '聊天记录', 'admin', '', '', 'api/admin/chat/kefu/record/<id>', 'GET', '[]', 0, 0, 0, 1, '', '165/678/423', 2, '', 0, '', 0),
(1058, 423, '', '查看对话', 'admin', '', '', 'api/admin/chat/kefu/chat_list', 'GET', '[]', 0, 0, 0, 1, '', '165/678/423', 2, '', 0, '', 0),
(1059, 743, '', '保存话术接口', 'admin', '', '', 'api/admin/chat/speechcraft', 'POST', '[]', 0, 0, 0, 1, '', '165/679/743', 2, '', 0, 'setting-store_service-add', 0),
(1060, 743, '', '获取修改话术接口', 'admin', '', '', 'api/admin/chat/speechcraft/<id>/edit', 'GET', '[]', 0, 0, 0, 1, '', '165/679/743', 2, '', 0, '', 0),
(1061, 743, '', '获取话术详情接口', 'admin', '', '', 'api/admin/chat/speechcraft/<id>', 'GET', '[]', 0, 0, 0, 1, '', '165/679/743', 2, '', 0, '', 0),
(1062, 789, '', '获取话术分类详情接口', 'admin', '', '', 'api/admin/chat/speechcraftcate/<id>', 'GET', '[]', 0, 0, 0, 1, '', '165/679/743/789', 2, '', 0, '', 0),
(1063, 25, '', '附件管理', 'admin', '', '', '', '', '[]', 0, 1, 1, 1, '/admin/system/attachment', '25', 1, '', 0, '', 0),
(1064, 1063, '', '图片附件列表', 'admin', '', '', 'api/admin/file/file', 'GET', '[]', 0, 0, 0, 1, '', '25/1063', 2, '', 0, '', 0),
(1065, 1063, '', '删除图片', 'admin', '', '', 'api/admin/file/file/delete', 'POST', '[]', 0, 0, 0, 1, '', '25/1063', 2, '', 0, '', 0),
(1066, 1063, '', '移动图片分类表单', 'admin', '', '', 'api/admin/file/file/move', 'GET', '[]', 0, 0, 0, 1, '', '25/1063', 2, '', 0, '', 0),
(1067, 1063, '', '移动图片分类', 'admin', '', '', 'api/admin/file/file/do_move', 'PUT', '[]', 0, 0, 0, 1, '', '25/1063', 2, '', 0, '', 0),
(1068, 1063, '', '修改图片名称', 'admin', '', '', 'api/admin/file/file//<id>', 'PUT', '[]', 0, 0, 0, 1, '', '25/1063', 2, '', 0, '', 0),
(1069, 1063, '', '修改图片名称', 'admin', '', '', 'api/admin/file/file/update/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '25/1063', 2, '', 0, '', 0),
(1070, 1063, '', '上传图片', 'admin', '', '', 'api/admin/file/upload/<upload_type?>', 'POST', '[]', 0, 0, 0, 1, '', '25/1063', 2, '', 0, '', 0),
(1071, 1063, '', '获取附件分类列表接口', 'admin', '', '', 'api/admin/file/category', 'GET', '[]', 0, 0, 0, 1, '', '25/1063', 2, '', 0, '', 0),
(1072, 1063, '', '保存附件分类接口', 'admin', '', '', 'api/admin/file/category', 'POST', '[]', 0, 0, 0, 1, '', '25/1063', 2, '', 0, '', 0),
(1073, 1063, '', '获取附件分类创建接口', 'admin', '', '', 'api/admin/file/category/create', 'GET', '[]', 0, 0, 0, 1, '', '25/1063', 2, '', 0, '', 0),
(1074, 1063, '', '获取修改附件分类接口', 'admin', '', '', 'api/admin/file/category/<id>/edit', 'GET', '[]', 0, 0, 0, 1, '', '25/1063', 2, '', 0, '', 0),
(1075, 1063, '', '获取附件分类详情接口', 'admin', '', '', 'api/admin/file/category/<id>', 'GET', '[]', 0, 0, 0, 1, '', '25/1063', 2, '', 0, '', 0),
(1076, 1063, '', '修改附件分类接口', 'admin', '', '', 'api/admin/file/category/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '25/1063', 2, '', 0, '', 0),
(1077, 1063, '', '删除附件分类接口', 'admin', '', '', 'api/admin/file/category/<id>', 'DELETE', '[]', 0, 0, 0, 1, '', '25/1063', 2, '', 0, '', 0),
(1078, 20, '', '删除管理员接口', 'admin', '', '', 'api/admin/setting/admin/<id>', 'DELETE', '[]', 0, 0, 0, 1, '', '12/14/20', 2, '', 0, '', 0),
(1079, 21, '', '获取修改权限菜单接口', 'admin', '', '', 'api/admin/setting/menus/<id>/edit', 'GET', '[]', 0, 0, 0, 1, '', '12/14/21', 2, '', 0, '', 0),
(1080, 7, '', '退出登陆', 'admin', '', '', 'api/admin/setting/admin/logout', 'GET', '[]', 0, 1, 0, 1, '', '7', 2, '', 0, '', 0),
(1082, 25, '', '管理员中心', 'admin', '', '', '', '', '[]', 0, 1, 1, 1, '/admin/system/user', '25', 1, '', 0, '', 0),
(1083, 1082, '', '修改当前管理员信息', 'admin', '', '', 'api/admin/setting/update_admin', 'PUT', '[]', 0, 0, 0, 1, '', '25/1082', 2, '', 0, '', 0),
(1084, 1082, '', '获取当前管理员信息', 'admin', '', '', 'api/admin/setting/info', 'GET', '[]', 0, 0, 0, 1, '', '25/1082', 2, '', 0, '', 0),
(1085, 476, '', '组合数据全部', 'admin', '', '', 'api/admin/setting/group_all', 'GET', '[]', 0, 0, 0, 1, '', '25/56/112/476', 2, '', 0, '', 0),
(1086, 476, '', '获取组合数据创建接口', 'admin', '', '', 'api/admin/setting/group/create', 'GET', '[]', 0, 0, 0, 1, '', '25/56/112/476', 2, '', 0, '', 0),
(1087, 476, '', '获取修改组合数据接口', 'admin', '', '', 'api/admin/setting/group/<id>/edit', 'GET', '[]', 0, 0, 0, 1, '', '25/56/112/476', 2, '', 0, '', 0),
(1088, 21, '', '未添加的权限规则列表', 'admin', '', '', 'api/admin/setting/ruleList', 'GET', '[]', 0, 0, 0, 1, '', '12/14/21', 2, '', 0, '', 0),
(1089, 23, '', '基本配置上传文件', 'admin', '', '', 'api/admin/setting/config/upload', 'POST', '[]', 0, 0, 0, 1, '', '12/23', 2, '', 0, '', 0),
(1090, 23, '', '获取修改系统配置接口', 'admin', '', '', 'api/admin/setting/config/<id>/edit', 'GET', '[]', 0, 0, 0, 1, '', '12/23', 2, '', 0, '', 0),
(1091, 462, '', '修改配置分类状态', 'admin', '', '', 'api/admin/setting/config_class/set_status/<id>/<status>', 'PUT', '[]', 0, 0, 0, 1, '', '25/56/111/462', 2, '', 0, '', 0),
(1092, 462, '', '获取配置分类详情接口', 'admin', '', '', 'api/admin/setting/config_class/<id>', 'GET', '[]', 0, 0, 0, 1, '', '25/56/111/462', 2, '', 0, '', 0),
(1093, 476, '', '获取组合数据资源详情接口', 'admin', '', '', 'api/admin/setting/group_data/<id>', 'GET', '[]', 0, 0, 0, 1, '', '25/56/112/476', 2, '', 0, '', 0),
(1097, 423, '', '自动回复列表', 'admin', '', '', 'api/admin/chat/reply', 'GET', '[]', 0, 0, 0, 1, '', '165/678/423', 2, '', 0, '', 0),
(1098, 423, '', '删除自动回复', 'admin', '', '', 'api/admin/chat/reply/<id>', 'DELETE', '[]', 0, 0, 0, 1, '', '165/678/423', 2, '', 0, '', 0),
(1099, 423, '', '保存自动回复', 'admin', '', '', 'api/admin/chat/reply/<id>', 'POST', '[]', 0, 0, 0, 1, '', '165/678/423', 2, '', 0, '', 0),
(1100, 423, '', '获取自动回复表单', 'admin', '', '', 'api/admin/chat/reply/<id>', 'GET', '[]', 0, 0, 0, 1, '', '165/678/423', 2, '', 0, '', 0),
(1101, 585, '', '用户标签搜索列表', 'admin', '', '', 'api/admin/user/user_label', 'GET', '[]', 0, 0, 0, 1, '', '9/10/585', 2, '', 0, '', 0),
(1102, 1011, '', '重置token', 'admin', '', '', 'api/admin/app/reset/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '12/1011', 2, '', 0, '', 0);

-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `eb_auxiliary` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `binding_id` int(10) NOT NULL DEFAULT '0' COMMENT '绑定id',
  `appid` varchar(32) NOT NULL DEFAULT '' COMMENT 'APPid',
  `relation_id` int(10) NOT NULL DEFAULT '0' COMMENT '关联id',
  `type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '类型0=客服转接辅助，1=商品和分类辅助，2=优惠券和商品辅助',
  `other` varchar(2048) NOT NULL DEFAULT '' COMMENT '其他数据为json',
  `status` int(1) unsigned NOT NULL DEFAULT '0' COMMENT '数据状态 0：未执行，1：成功， 2：失败， 3:删除',
  `update_time` int(10) NOT NULL DEFAULT '0' COMMENT '更新时间',
  `add_time` int(10) NOT NULL DEFAULT '0' COMMENT '添加时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='辅助表';

--
-- 表的结构 `eb_system_role`
--

CREATE TABLE IF NOT EXISTS `eb_system_role` (
  `id` int(11) NOT NULL,
  `role_name` varchar(32) NOT NULL DEFAULT '' COMMENT '身份管理名称',
  `rules` text NOT NULL COMMENT '身份管理权限(menus_id)',
  `level` int(3) NOT NULL DEFAULT '0' COMMENT '身份等级',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态'
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='身份管理表';

--
-- Indexes for dumped tables
--

--
-- Indexes for table `eb_application`
--
ALTER TABLE `eb_application`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `eb_cache`
--
ALTER TABLE `eb_cache`
  ADD KEY `key` (`key`);

--
-- Indexes for table `eb_category`
--
ALTER TABLE `eb_category`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `eb_chat_service`
--
ALTER TABLE `eb_chat_service`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account` (`account`),
  ADD KEY `phone` (`phone`);

--
-- Indexes for table `eb_chat_service_dialogue_record`
--
ALTER TABLE `eb_chat_service_dialogue_record`
  ADD PRIMARY KEY (`id`),
  ADD KEY `to_uid` (`to_user_id`,`user_id`);

--
-- Indexes for table `eb_chat_service_feedback`
--
ALTER TABLE `eb_chat_service_feedback`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `eb_chat_service_record`
--
ALTER TABLE `eb_chat_service_record`
  ADD PRIMARY KEY (`id`),
  ADD KEY `to_uid` (`to_user_id`);

--
-- Indexes for table `eb_chat_service_speechcraft`
--
ALTER TABLE `eb_chat_service_speechcraft`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kefu_id` (`kefu_id`),
  ADD KEY `cate_id` (`cate_id`);

--
-- Indexes for table `eb_chat_user`
--
ALTER TABLE `eb_chat_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uid` (`id`,`appid`);

--
-- Indexes for table `eb_chat_user_group`
--
ALTER TABLE `eb_chat_user_group`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `eb_chat_user_label`
--
ALTER TABLE `eb_chat_user_label`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cate_id` (`cate_id`);

--
-- Indexes for table `eb_chat_user_label_assist`
--
ALTER TABLE `eb_chat_user_label_assist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uid_id` (`user_id`);

--
-- Indexes for table `eb_system_admin`
--
ALTER TABLE `eb_system_admin`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account` (`account`,`status`),
  ADD KEY `account_2` (`account`);

--
-- Indexes for table `eb_system_attachment`
--
ALTER TABLE `eb_system_attachment`
  ADD PRIMARY KEY (`att_id`);

--
-- Indexes for table `eb_system_attachment_category`
--
ALTER TABLE `eb_system_attachment_category`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `eb_system_config`
--
ALTER TABLE `eb_system_config`
  ADD PRIMARY KEY (`id`),
  ADD KEY `key_status` (`menu_name`(191),`status`),
  ADD KEY `menu_name` (`menu_name`(191));

--
-- Indexes for table `eb_system_config_tab`
--
ALTER TABLE `eb_system_config_tab`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `eb_system_group`
--
ALTER TABLE `eb_system_group`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cate_id` (`cate_id`);

--
-- Indexes for table `eb_system_group_data`
--
ALTER TABLE `eb_system_group_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gid` (`gid`);

--
-- Indexes for table `eb_system_log`
--
ALTER TABLE `eb_system_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `add_time` (`add_time`),
  ADD KEY `type` (`type`);

--
-- Indexes for table `eb_system_menus`
--
ALTER TABLE `eb_system_menus`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pid` (`pid`),
  ADD KEY `is_show` (`is_show`),
  ADD KEY `access` (`access`);

--
-- Indexes for table `eb_system_role`
--
ALTER TABLE `eb_system_role`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `eb_application`
--
ALTER TABLE `eb_application`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=5;
--
-- AUTO_INCREMENT for table `eb_category`
--
ALTER TABLE `eb_category`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=25;
--
-- AUTO_INCREMENT for table `eb_chat_service`
--
ALTER TABLE `eb_chat_service`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=11;
--
-- AUTO_INCREMENT for table `eb_chat_service_dialogue_record`
--
ALTER TABLE `eb_chat_service_dialogue_record`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=466;
--
-- AUTO_INCREMENT for table `eb_chat_service_feedback`
--
ALTER TABLE `eb_chat_service_feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=7;
--
-- AUTO_INCREMENT for table `eb_chat_service_record`
--
ALTER TABLE `eb_chat_service_record`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=28;
--
-- AUTO_INCREMENT for table `eb_chat_service_speechcraft`
--
ALTER TABLE `eb_chat_service_speechcraft`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=16;
--
-- AUTO_INCREMENT for table `eb_chat_user`
--
ALTER TABLE `eb_chat_user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=795;
--
-- AUTO_INCREMENT for table `eb_chat_user_group`
--
ALTER TABLE `eb_chat_user_group`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=10;
--
-- AUTO_INCREMENT for table `eb_chat_user_label`
--
ALTER TABLE `eb_chat_user_label`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=13;
--
-- AUTO_INCREMENT for table `eb_chat_user_label_assist`
--
ALTER TABLE `eb_chat_user_label_assist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=24;
--
-- AUTO_INCREMENT for table `eb_system_admin`
--
ALTER TABLE `eb_system_admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=4;
--
-- AUTO_INCREMENT for table `eb_system_attachment`
--
ALTER TABLE `eb_system_attachment`
  MODIFY `att_id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=53;
--
-- AUTO_INCREMENT for table `eb_system_attachment_category`
--
ALTER TABLE `eb_system_attachment_category`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=4;
--
-- AUTO_INCREMENT for table `eb_system_config`
--
ALTER TABLE `eb_system_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=375;
--
-- AUTO_INCREMENT for table `eb_system_config_tab`
--
ALTER TABLE `eb_system_config_tab`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=78;
--
-- AUTO_INCREMENT for table `eb_system_group`
--
ALTER TABLE `eb_system_group`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `eb_system_group_data`
--
ALTER TABLE `eb_system_group_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `eb_system_log`
--
ALTER TABLE `eb_system_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=7947;
--
-- AUTO_INCREMENT for table `eb_system_menus`
--
ALTER TABLE `eb_system_menus`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=1094;
--
-- AUTO_INCREMENT for table `eb_system_role`
--
ALTER TABLE `eb_system_role`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;

-- 多租户改造：业务表补充租户维度（与update.sql保持一致）
ALTER TABLE `eb_chat_user` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_chat_service` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_chat_service_dialogue_record` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_chat_service_record` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_chat_service_feedback` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_chat_auto_reply` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_auxiliary` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_chat_service_speechcraft` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_chat_user_group` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_chat_user_label` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_chat_user_label_assist` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_chat_complain` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_category` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_system_role` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_system_attachment` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `att_id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_system_attachment_category` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_system_log` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID,0=平台' AFTER `id`, ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `eb_system_config` ADD `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID,0=平台默认' AFTER `id`, ADD INDEX `idx_tenant_menu` (`tenant_id`, `menu_name`);

-- 初始数据归入默认租户；system_config/system_log 保留为平台层(0)
UPDATE `eb_chat_user` t INNER JOIN `eb_application` a ON t.appid = a.appid SET t.tenant_id = a.tenant_id WHERE t.tenant_id = 0;
UPDATE `eb_chat_service` t INNER JOIN `eb_application` a ON t.appid = a.appid SET t.tenant_id = a.tenant_id WHERE t.tenant_id = 0;
UPDATE `eb_chat_user` SET `tenant_id` = 1 WHERE `tenant_id` = 0;
UPDATE `eb_chat_service` SET `tenant_id` = 1 WHERE `tenant_id` = 0;
UPDATE `eb_system_attachment` SET `tenant_id` = 1 WHERE `tenant_id` = 0;

-- 2026/08/29 多租户改造·阶段六：套餐计费体系
CREATE TABLE IF NOT EXISTS `eb_tenant_plan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '套餐名称',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '月价格(元)',
  `app_limit` int(10) NOT NULL DEFAULT '0' COMMENT '接入应用数上限,0=不限',
  `seat_limit` int(10) NOT NULL DEFAULT '0' COMMENT '客服坐席数上限,0=不限',
  `daily_msg_limit` int(10) NOT NULL DEFAULT '0' COMMENT '每日消息条数上限,0=不限',
  `storage_limit_mb` int(10) NOT NULL DEFAULT '0' COMMENT '附件存储上限(MB),0=不限',
  `record_keep_days` int(10) NOT NULL DEFAULT '0' COMMENT '聊天记录保留天数,0=永久',
  `auto_reply` tinyint(1) NOT NULL DEFAULT '0' COMMENT '关键词自动回复',
  `brand_custom` tinyint(1) NOT NULL DEFAULT '0' COMMENT '品牌自定义(站点名/LOGO/头像)',
  `data_export` tinyint(1) NOT NULL DEFAULT '0' COMMENT '数据导出',
  `app_push` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'APP离线推送',
  `sort` int(10) NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0=停售,1=在售',
  `is_delete` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否删除',
  `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(10) NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='租户套餐表';

INSERT INTO `eb_tenant_plan` (`id`, `name`, `price`, `app_limit`, `seat_limit`, `daily_msg_limit`, `storage_limit_mb`, `record_keep_days`, `auto_reply`, `brand_custom`, `data_export`, `app_push`, `sort`, `status`, `create_time`, `update_time`) VALUES
(1, '免费版', 0.00, 1, 2, 500, 200, 7, 0, 0, 0, 0, 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '体验版', 500.00, 2, 5, 5000, 2048, 30, 1, 0, 0, 1, 2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(3, '标准版', 1000.00, 5, 20, 20000, 10240, 180, 1, 1, 1, 1, 3, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(4, '旗舰版', 2000.00, 0, 100, 0, 51200, 0, 1, 1, 1, 1, 4, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

CREATE TABLE IF NOT EXISTS `eb_tenant_plan_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID',
  `order_no` varchar(32) NOT NULL DEFAULT '' COMMENT '对账单号',
  `plan_id` int(10) NOT NULL DEFAULT '0' COMMENT '套餐ID',
  `plan_name` varchar(50) NOT NULL DEFAULT '' COMMENT '套餐名称快照',
  `plan_snapshot` text COMMENT '套餐配额快照json',
  `months` int(10) NOT NULL DEFAULT '1' COMMENT '订购月数',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '金额(元)',
  `pay_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=后台开通,2=线下转账',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=已生效,2=已作废',
  `expire_before` int(10) NOT NULL DEFAULT '0' COMMENT '订购前到期时间',
  `expire_after` int(10) NOT NULL DEFAULT '0' COMMENT '订购后到期时间',
  `admin_id` int(10) NOT NULL DEFAULT '0' COMMENT '操作管理员ID',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_order_no` (`order_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='租户套餐订购对账表';

CREATE TABLE IF NOT EXISTS `eb_tenant_invoice` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID',
  `order_id` int(10) NOT NULL DEFAULT '0' COMMENT '关联订购单ID',
  `order_no` varchar(32) NOT NULL DEFAULT '' COMMENT '关联对账单号',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '发票抬头',
  `tax_no` varchar(50) NOT NULL DEFAULT '' COMMENT '税号',
  `type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=普票,2=专票',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '开票金额(元)',
  `email` varchar(100) NOT NULL DEFAULT '' COMMENT '接收邮箱',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=待开具,1=已开具,2=已驳回',
  `invoice_no` varchar(50) NOT NULL DEFAULT '' COMMENT '发票号码',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注/驳回原因',
  `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '申请时间',
  `update_time` int(10) NOT NULL DEFAULT '0' COMMENT '处理时间',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='租户发票记录表';

CREATE TABLE IF NOT EXISTS `eb_tenant_notice` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID',
  `type` varchar(20) NOT NULL DEFAULT '' COMMENT '通知类型expire_warn=即将到期,expired=已到期,renew=续费成功',
  `content` varchar(255) NOT NULL DEFAULT '' COMMENT '通知内容',
  `is_read` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已读',
  `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='租户通知表';

ALTER TABLE `eb_tenant` ADD `plan_id` int(10) NOT NULL DEFAULT '0' COMMENT '当前套餐ID,0=未订购(不限制)' AFTER `plan`;
-- 存量租户按旗舰版兜底，避免升级后被配额限制影响现网
UPDATE `eb_tenant` SET `plan_id` = 4 WHERE `plan_id` = 0;
-- 2026/08/30 多租户改造：租户管理菜单与接口权限（页面由前端仓库配合开发）
-- 显示菜单：平台超管(level=0)可见全部；租户管理员按角色授权可见（对账/发票/通知等自助菜单）
INSERT INTO `eb_system_menus` (`id`, `pid`, `icon`, `menu_name`, `module`, `controller`, `action`, `api_url`, `methods`, `params`, `sort`, `is_show`, `is_show_path`, `access`, `menu_path`, `path`, `auth_type`, `header`, `is_header`, `unique_auth`, `is_del`) VALUES
(1200, 0, 'md-albums', '租户管理', 'admin', '', '', '', '', '[]', 3, 1, 0, 1, '/admin/tenant', '', 1, 'tenant', 1, 'admin-tenant', 0),
(1201, 1200, '', '租户列表', 'admin', '', '', '', '', '[]', 10, 1, 0, 1, '/admin/tenant/list', '1200', 1, 'tenant', 1, 'tenant-list', 0),
(1202, 1200, '', '套餐管理', 'admin', '', '', '', '', '[]', 9, 1, 0, 1, '/admin/tenant/plan', '1200', 1, 'tenant', 1, 'tenant-plan', 0),
(1203, 1200, '', '订购对账', 'admin', '', '', '', '', '[]', 8, 1, 0, 1, '/admin/tenant/orders', '1200', 1, 'tenant', 1, 'tenant-orders', 0),
(1204, 1200, '', '发票管理', 'admin', '', '', '', '', '[]', 7, 1, 0, 1, '/admin/tenant/invoice', '1200', 1, 'tenant', 1, 'tenant-invoice', 0),
(1205, 1200, '', '租户通知', 'admin', '', '', '', '', '[]', 6, 1, 0, 1, '/admin/tenant/notice', '1200', 1, 'tenant', 1, 'tenant-notice', 0),
(1210, 1201, '', '租户列表接口', 'admin', '', '', 'api/admin/setting/tenant', 'GET', '[]', 0, 0, 0, 1, '', '1200/1201', 2, '', 0, '', 0),
(1211, 1201, '', '创建租户', 'admin', '', '', 'api/admin/setting/tenant', 'POST', '[]', 0, 0, 0, 1, '', '1200/1201', 2, '', 0, '', 0),
(1212, 1201, '', '修改租户', 'admin', '', '', 'api/admin/setting/tenant/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '1200/1201', 2, '', 0, '', 0),
(1213, 1201, '', '启停租户', 'admin', '', '', 'api/admin/setting/tenant/set_status/<id>/<status>', 'PUT', '[]', 0, 0, 0, 1, '', '1200/1201', 2, '', 0, '', 0),
(1214, 1201, '', '创建租户管理员', 'admin', '', '', 'api/admin/setting/tenant/admin', 'POST', '[]', 0, 0, 0, 1, '', '1200/1201', 2, '', 0, '', 0),
(1215, 1202, '', '开通续费套餐', 'admin', '', '', 'api/admin/setting/tenant/subscribe', 'POST', '[]', 0, 0, 0, 1, '', '1200/1202', 2, '', 0, '', 0),
(1216, 1202, '', '套餐列表接口', 'admin', '', '', 'api/admin/setting/tenant/plan', 'GET', '[]', 0, 0, 0, 1, '', '1200/1202', 2, '', 0, '', 0),
(1217, 1202, '', '在售套餐下拉', 'admin', '', '', 'api/admin/setting/tenant/plan/all', 'GET', '[]', 0, 0, 0, 1, '', '1200/1202', 2, '', 0, '', 0),
(1218, 1202, '', '创建套餐', 'admin', '', '', 'api/admin/setting/tenant/plan', 'POST', '[]', 0, 0, 0, 1, '', '1200/1202', 2, '', 0, '', 0),
(1219, 1202, '', '修改套餐', 'admin', '', '', 'api/admin/setting/tenant/plan/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '1200/1202', 2, '', 0, '', 0),
(1220, 1202, '', '上架停售套餐', 'admin', '', '', 'api/admin/setting/tenant/plan/set_status/<id>/<status>', 'PUT', '[]', 0, 0, 0, 1, '', '1200/1202', 2, '', 0, '', 0),
(1221, 1202, '', '删除套餐', 'admin', '', '', 'api/admin/setting/tenant/plan/<id>', 'DELETE', '[]', 0, 0, 0, 1, '', '1200/1202', 2, '', 0, '', 0),
(1222, 1203, '', '订购对账列表', 'admin', '', '', 'api/admin/setting/tenant/orders', 'GET', '[]', 0, 0, 0, 1, '', '1200/1203', 2, '', 0, '', 0),
(1223, 1203, '', '对账导出', 'admin', '', '', 'api/admin/setting/tenant/orders/export', 'GET', '[]', 0, 0, 0, 1, '', '1200/1203', 2, '', 0, '', 0),
(1224, 1204, '', '发票列表', 'admin', '', '', 'api/admin/setting/tenant/invoice', 'GET', '[]', 0, 0, 0, 1, '', '1200/1204', 2, '', 0, '', 0),
(1225, 1204, '', '申请开票', 'admin', '', '', 'api/admin/setting/tenant/invoice', 'POST', '[]', 0, 0, 0, 1, '', '1200/1204', 2, '', 0, '', 0),
(1226, 1204, '', '开具驳回发票', 'admin', '', '', 'api/admin/setting/tenant/invoice/audit/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '1200/1204', 2, '', 0, '', 0),
(1227, 1205, '', '租户通知列表', 'admin', '', '', 'api/admin/setting/tenant/notice', 'GET', '[]', 0, 0, 0, 1, '', '1200/1205', 2, '', 0, '', 0),
(1228, 1205, '', '通知已读', 'admin', '', '', 'api/admin/setting/tenant/notice/read/<id>', 'PUT', '[]', 0, 0, 0, 1, '', '1200/1205', 2, '', 0, '', 0);


-- 切换租户视角权限点（与update.sql保持一致）
INSERT INTO `eb_system_menus` (`id`, `pid`, `icon`, `menu_name`, `module`, `controller`, `action`, `api_url`, `methods`, `params`, `sort`, `is_show`, `is_show_path`, `access`, `menu_path`, `path`, `auth_type`, `header`, `is_header`, `unique_auth`, `is_del`) VALUES
(1229, 1200, '', '切换租户视角', 'admin', '', '', 'api/admin/setting/tenant/view_switch', 'GET', '[]', 0, 0, 0, 1, '', '1200', 2, '', 0, '', 0);

-- RBAC多租户适配：维护管理树与租户管理树为平台专属，通知管理独立为顶级通用菜单（与update.sql保持一致）
UPDATE `eb_system_menus` SET `is_tenant` = 0 WHERE `id` IN (25, 1200) OR `path` = '25' OR `path` LIKE '25/%' OR `path` = '1200' OR `path` LIKE '1200/%';
UPDATE `eb_system_menus` SET `pid` = 0, `icon` = 'md-notifications', `menu_name` = '通知管理', `path` = '', `header` = 'notice', `is_header` = 1, `is_tenant` = 1, `sort` = 1 WHERE `id` = 1205;
UPDATE `eb_system_menus` SET `path` = '1205', `is_tenant` = 1, `menu_name` = '通知列表' WHERE `id` = 1227;
UPDATE `eb_system_menus` SET `path` = '1205', `is_tenant` = 1 WHERE `id` = 1228;
INSERT INTO `eb_system_menus` (`id`, `pid`, `icon`, `menu_name`, `module`, `controller`, `action`, `api_url`, `methods`, `params`, `sort`, `is_show`, `is_show_path`, `access`, `menu_path`, `path`, `auth_type`, `header`, `is_header`, `unique_auth`, `is_del`, `is_tenant`) VALUES
(1230, 1205, '', '发送通知', 'admin', '', '', 'api/admin/setting/tenant/notice', 'POST', '[]', 0, 0, 0, 1, '', '1205', 2, '', 0, '', 0, 0);

-- 访客接入安全加固与应用管理菜单命名（与update.sql保持一致；全新安装种子应用同样走签名模式）
ALTER TABLE `eb_application` ADD `auth_mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '接入模式0=标准(默认),1=签名(需服务端下发签名)' AFTER `token_md5`;
UPDATE `eb_system_menus` SET `menu_name` = '应用管理' WHERE `id` = 1011;

-- 租户端我的订阅菜单（与update.sql保持一致）
INSERT INTO `eb_system_menus` (`id`, `pid`, `icon`, `menu_name`, `module`, `controller`, `action`, `api_url`, `methods`, `params`, `sort`, `is_show`, `is_show_path`, `access`, `menu_path`, `path`, `auth_type`, `header`, `is_header`, `unique_auth`, `is_del`, `is_tenant`) VALUES
(1240, 0, 'md-card', '我的订阅', 'admin', '', '', '', '', '[]', 0, 1, 0, 1, '/admin/tenant/subscription', '', 1, 'subscription', 1, 'tenant-subscription', 0, 1),
(1241, 1240, '', '我的订阅概览', 'admin', '', '', 'api/admin/setting/tenant/my', 'GET', '[]', 0, 0, 0, 1, '', '1240', 2, '', 0, '', 0, 1),
(1242, 1240, '', '我的订阅订单', 'admin', '', '', 'api/admin/setting/tenant/orders', 'GET', '[]', 0, 0, 0, 1, '', '1240', 2, '', 0, '', 0, 1),
(1243, 1240, '', '我的发票列表', 'admin', '', '', 'api/admin/setting/tenant/invoice', 'GET', '[]', 0, 0, 0, 1, '', '1240', 2, '', 0, '', 0, 1),
(1244, 1240, '', '申请开票', 'admin', '', '', 'api/admin/setting/tenant/invoice', 'POST', '[]', 0, 0, 0, 1, '', '1240', 2, '', 0, '', 0, 1);

-- 租户端在售套餐展示权限（与update.sql保持一致）
INSERT INTO `eb_system_menus` (`id`, `pid`, `icon`, `menu_name`, `module`, `controller`, `action`, `api_url`, `methods`, `params`, `sort`, `is_show`, `is_show_path`, `access`, `menu_path`, `path`, `auth_type`, `header`, `is_header`, `unique_auth`, `is_del`, `is_tenant`) VALUES
(1245, 1240, '', '在售套餐展示', 'admin', '', '', 'api/admin/setting/tenant/plans', 'GET', '[]', 0, 0, 0, 1, '', '1240', 2, '', 0, '', 0, 1);

-- AI 智能客服（与update.sql保持一致）
-- AI配置：租户级一套（多应用共用）
CREATE TABLE IF NOT EXISTS `eb_ai_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID',
  `enable` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否启用0=否,1=是',
  `mode` varchar(20) NOT NULL DEFAULT 'standby' COMMENT '接待模式standby=值守,ai_first=AI优先',
  `greeting` varchar(500) NOT NULL DEFAULT '' COMMENT 'AI欢迎语',
  `system_prompt` text COMMENT '身份设定与业务介绍',
  `faq` text COMMENT 'FAQ问答对json',
  `transfer_keywords` varchar(500) NOT NULL DEFAULT '人工,转人工,客服,投诉' COMMENT '转人工关键词,逗号分隔',
  `model` varchar(64) NOT NULL DEFAULT '' COMMENT '模型覆盖,空=用平台默认',
  `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(10) NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI客服配置表';

-- AI调用用量流水：平台级（不受租户隔离），供成本归集与对账
CREATE TABLE IF NOT EXISTS `eb_ai_usage_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '租户ID',
  `appid` varchar(50) NOT NULL DEFAULT '' COMMENT '应用ID',
  `user_id` int(10) NOT NULL DEFAULT '0' COMMENT '访客chat_user id',
  `model` varchar(64) NOT NULL DEFAULT '' COMMENT '实际调用模型',
  `prompt_tokens` int(10) NOT NULL DEFAULT '0' COMMENT '输入token',
  `completion_tokens` int(10) NOT NULL DEFAULT '0' COMMENT '输出token',
  `duration_ms` int(10) NOT NULL DEFAULT '0' COMMENT '耗时毫秒',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=成功,2=失败,3=超时',
  `error` varchar(255) NOT NULL DEFAULT '' COMMENT '失败原因',
  `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '调用时间',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_time` (`tenant_id`, `create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI调用用量流水表';

-- 虚拟AI坐席标记：分配/配额/转接/登录/后台管理五处据此排除
ALTER TABLE `eb_chat_service` ADD `is_ai` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否AI虚拟坐席0=否,1=是' AFTER `auto_reply`;
-- 消息来源：为质检与统计提供依据
ALTER TABLE `eb_chat_service_dialogue_record` ADD `source` tinyint(1) NOT NULL DEFAULT '0' COMMENT '来源0=人工,1=关键词,2=AI正文,3=AI兜底,4=超限降级' AFTER `msn_type`;
-- 套餐AI能力：功能开关+日回复配额
ALTER TABLE `eb_tenant_plan` ADD `ai_reply` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'AI智能客服0=关闭,1=开启' AFTER `app_push`, ADD `daily_ai_limit` int(10) NOT NULL DEFAULT '0' COMMENT 'AI日回复上限,0=不限' AFTER `daily_msg_limit`;
UPDATE `eb_tenant_plan` SET `ai_reply` = 1, `daily_ai_limit` = 200 WHERE `name` = '标准版';
UPDATE `eb_tenant_plan` SET `ai_reply` = 1, `daily_ai_limit` = 2000 WHERE `name` = '旗舰版';

-- 菜单：租户端AI客服设置（is_tenant=1）
INSERT INTO `eb_system_menus` (`id`, `pid`, `icon`, `menu_name`, `module`, `controller`, `action`, `api_url`, `methods`, `params`, `sort`, `is_show`, `is_show_path`, `access`, `menu_path`, `path`, `auth_type`, `header`, `is_header`, `unique_auth`, `is_del`, `is_tenant`) VALUES
(1250, 165, '', 'AI客服设置', 'admin', '', '', '', '', '[]', 5, 1, 0, 1, '/admin/chat/ai', '165', 1, 'kefu', 0, 'chat-ai-config', 0, 1),
(1251, 1250, '', 'AI配置详情', 'admin', '', '', 'api/admin/chat/ai', 'GET', '[]', 0, 0, 0, 1, '', '165/1250', 2, '', 0, '', 0, 1),
(1252, 1250, '', '保存AI配置', 'admin', '', '', 'api/admin/chat/ai', 'POST', '[]', 0, 0, 0, 1, '', '165/1250', 2, '', 0, '', 0, 1),
(1253, 1250, '', 'AI用量统计', 'admin', '', '', 'api/admin/chat/ai/usage', 'GET', '[]', 0, 0, 0, 1, '', '165/1250', 2, '', 0, '', 0, 1);
UPDATE `eb_system_role` SET `rules` = CONCAT(`rules`, ',1250,1251,1252,1253') WHERE `role_name` = '租户管理员' AND `tenant_id` > 0 AND `rules` NOT LIKE '%1250%';

-- 客户端装修（与update.sql保持一致）
-- 按应用维度：接入代码按应用发放，同租户不同应用可以是不同品牌与站点
CREATE TABLE IF NOT EXISTS `eb_application_theme` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) NOT NULL DEFAULT '0' COMMENT '所属租户ID',
  `appid` varchar(50) NOT NULL DEFAULT '' COMMENT '所属应用',
  `title` varchar(50) NOT NULL DEFAULT '' COMMENT '窗口标题,空=用应用名',
  `logo` varchar(255) NOT NULL DEFAULT '' COMMENT '窗口LOGO',
  `theme_color` varchar(20) NOT NULL DEFAULT '#2d8cf0' COMMENT '主题色',
  `theme_style` varchar(20) NOT NULL DEFAULT 'modern' COMMENT '布局风格modern/minimal/soft/midnight',
  `bubble_style` varchar(20) NOT NULL DEFAULT 'soft' COMMENT '气泡风格soft/clean/pill/outline/card',
  `pc_icon` varchar(2000) NOT NULL DEFAULT '' COMMENT 'PC悬浮按钮图标,预设为SVG data-URI或自定义URL',
  `mobile_icon` varchar(2000) NOT NULL DEFAULT '' COMMENT '移动端悬浮按钮图标',
  `banners` text COMMENT '轮播广告json[{image,link,sort}]',
  `custom_html` text COMMENT '自定义广告HTML',
  `show_platform_brand` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否显示平台标识1=显示,0=白标(需套餐支持)',
  `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(10) NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_appid` (`appid`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客户端装修配置表';

-- 白标能力：去除平台标识，仅旗舰版
ALTER TABLE `eb_tenant_plan` ADD `white_label` tinyint(1) NOT NULL DEFAULT '0' COMMENT '去平台标识0=否,1=是' AFTER `brand_custom`;
UPDATE `eb_tenant_plan` SET `white_label` = 1 WHERE `name` = '旗舰版';

-- 修复存量漏洞：客服广告原以固定key存于缓存表导致所有租户串台，改为按租户拆分key（见CacheServices::kfAdvKey），无需改表结构

-- 菜单：租户端客户端装修
INSERT INTO `eb_system_menus` (`id`, `pid`, `icon`, `menu_name`, `module`, `controller`, `action`, `api_url`, `methods`, `params`, `sort`, `is_show`, `is_show_path`, `access`, `menu_path`, `path`, `auth_type`, `header`, `is_header`, `unique_auth`, `is_del`, `is_tenant`) VALUES
(1260, 165, '', '客户端装修', 'admin', '', '', '', '', '[]', 4, 1, 0, 1, '/admin/chat/theme', '165', 1, 'kefu', 0, 'chat-theme', 0, 1),
(1261, 1260, '', '装修配置详情', 'admin', '', '', 'api/admin/chat/theme', 'GET', '[]', 0, 0, 0, 1, '', '165/1260', 2, '', 0, '', 0, 1),
(1262, 1260, '', '保存装修配置', 'admin', '', '', 'api/admin/chat/theme', 'POST', '[]', 0, 0, 0, 1, '', '165/1260', 2, '', 0, '', 0, 1);
UPDATE `eb_system_role` SET `rules` = CONCAT(`rules`, ',1260,1261,1262') WHERE `role_name` = '租户管理员' AND `tenant_id` > 0 AND `rules` NOT LIKE '%1260%';

-- 权限收口修复（与update.sql保持一致）
-- 此前用path列做子树级联失效（存量path大面积为空），改用pid递归重新标注；
-- 平台专属：维护管理(25)/租户管理(1200)/权限规则(21) 整棵子树，但排除管理员自助与附件两棵
UPDATE `eb_system_menus` SET `is_tenant` = 0 WHERE `id` IN (21,47,56,65,111,112,125,126,338,339,340,341,342,343,344,345,346,462,464,465,466,467,468,469,470,471,472,473,474,475,476,477,478,479,480,481,482,483,484,485,486,487,488,489,619,641,1079,1088,1090);
-- 反向误伤修正：附件管理与管理员中心是每个管理员的自助功能，租户必须可用
UPDATE `eb_system_menus` SET `is_tenant` = 1 WHERE `id` IN (1063,1064,1065,1066,1067,1068,1069,1070,1071,1072,1073,1074,1075,1076,1077,1078,1082,1083,1084);
-- 重算存量租户默认角色（ensureDefaultRole仅在角色不存在时创建，不会自动纠正旧角色）
UPDATE `eb_system_role` SET `rules` = (SELECT GROUP_CONCAT(`id`) FROM (SELECT `id` FROM `eb_system_menus` WHERE `is_tenant` = 1 AND `is_del` = 0) t) WHERE `role_name` = '租户管理员' AND `tenant_id` > 0;

-- 替换失效的外链资源（与update.sql保持一致）
-- 官方演示站已下线导致全站破图，改用仓库自带的本地资源
UPDATE `eb_system_config` SET `value` = '["\/statics\/avatar\/tourist-1.svg","\/statics\/avatar\/tourist-2.svg","\/statics\/avatar\/tourist-3.svg","\/statics\/avatar\/tourist-4.svg"]' WHERE `menu_name` = 'tourist_avatar';
UPDATE `eb_system_config` SET `value` = '""' WHERE `menu_name` IN ('site_logo','site_logo_square','login_logo') AND `value` LIKE '%crmeb.net%';
UPDATE `eb_application` SET `icon` = '/statics/avatar/tourist-1.svg' WHERE `icon` LIKE '%crmeb.net%';
UPDATE `eb_chat_service` SET `avatar` = '/statics/avatar/tourist-1.svg' WHERE `avatar` LIKE '%crmeb.net%';
UPDATE `eb_chat_user` SET `avatar` = '/statics/avatar/tourist-1.svg' WHERE `avatar` LIKE '%crmeb.net%';

-- AI密钥数据库管理（与update.sql保持一致）
-- 密钥加密存储（APP_KEY派生），后台可自助更换；留空时回落环境变量 AI_API_KEY
INSERT INTO `eb_system_config_tab` (`id`, `pid`, `title`, `eng_title`, `status`, `info`, `icon`, `type`, `sort`) VALUES
(90, 0, 'AI客服配置', 'ai_config', 1, 0, 'md-bulb', 0, 0);
INSERT INTO `eb_system_config` (`tenant_id`, `menu_name`, `type`, `input_type`, `config_tab_id`, `parameter`, `upload_type`, `required`, `width`, `high`, `value`, `info`, `desc`, `sort`, `status`) VALUES
(0, 'ai_base_url', 'text', 'input', 90, '', 0, '', 100, 0, '"https://api.deepseek.com"', '接口地址', '大模型服务地址，需兼容OpenAI协议。DeepSeek填 https://api.deepseek.com', 40, 1),
(0, 'ai_api_key', 'text', 'input', 90, '', 0, '', 100, 0, '""', 'API密钥', '加密存储；留空则回落环境变量 AI_API_KEY。保存后仅显示掩码', 30, 1),
(0, 'ai_model', 'text', 'input', 90, '', 0, '', 100, 0, '"deepseek-chat"', '模型名称', '如 deepseek-chat、qwen-plus、glm-4', 20, 1),
(0, 'ai_timeout', 'text', 'input', 90, '', 0, '', 100, 0, '"30"', '超时秒数', '单次调用超时时间，建议20-60秒', 10, 1);

-- 菜单信息架构重排与平台默认广告（与update.sql保持一致）
SET SESSION group_concat_max_len = 1000000;
-- 客服管理提至一级第二位（原header为空属存量脏数据）；客服列表含"进入工作台"入口，排组内第一
UPDATE `eb_system_menus` SET `sort` = 120, `header` = 'kefu', `is_header` = 1 WHERE `id` = 165;
UPDATE `eb_system_menus` SET `sort` = 50 WHERE `id` = 678;
UPDATE `eb_system_menus` SET `sort` = 40 WHERE `id` = 679;
UPDATE `eb_system_menus` SET `sort` = 30 WHERE `id` = 738;
UPDATE `eb_system_menus` SET `sort` = 20 WHERE `id` = 1250;
UPDATE `eb_system_menus` SET `sort` = 10 WHERE `id` = 1260;
-- eb_chat_user 是访客档案，与管理员账号并列时叫"用户"有歧义
UPDATE `eb_system_menus` SET `menu_name` = '客户管理', `sort` = 100 WHERE `id` = 9;
UPDATE `eb_system_menus` SET `menu_name` = '客户列表' WHERE `id` = 10;
UPDATE `eb_system_menus` SET `menu_name` = '客户分组' WHERE `id` = 227;
UPDATE `eb_system_menus` SET `menu_name` = '客户标签' WHERE `id` = 1008;
-- 应用管理与权限管理自立门户：前者是租户开通后第一件事，后者是独立管理域
UPDATE `eb_system_menus` SET `pid` = 0, `icon` = 'md-apps', `header` = 'app', `is_header` = 1, `sort` = 90, `path` = '' WHERE `id` = 1011;
UPDATE `eb_system_menus` SET `path` = '1011' WHERE `pid` = 1011;
UPDATE `eb_system_menus` SET `pid` = 0, `icon` = 'md-key', `header` = 'auth', `is_header` = 1, `sort` = 70, `menu_name` = '权限管理', `path` = '' WHERE `id` = 14;
UPDATE `eb_system_menus` SET `path` = '14', `header` = 'auth' WHERE `pid` = 14;
-- 账户中心收拢两个单页低频菜单，腾出一级位
INSERT INTO `eb_system_menus` (`id`, `pid`, `icon`, `menu_name`, `module`, `controller`, `action`, `api_url`, `methods`, `params`, `sort`, `is_show`, `is_show_path`, `access`, `menu_path`, `path`, `auth_type`, `header`, `is_header`, `unique_auth`, `is_del`, `is_tenant`) VALUES
(1300, 0, 'md-briefcase', '账户中心', 'admin', '', '', '', '', '[]', 60, 1, 0, 1, '', '', 1, 'account', 1, 'admin-account', 0, 1);
UPDATE `eb_system_menus` SET `pid` = 1300, `header` = 'account', `is_header` = 0, `sort` = 20, `path` = '1300' WHERE `id` = 1240;
UPDATE `eb_system_menus` SET `pid` = 1300, `header` = 'account', `is_header` = 0, `sort` = 10, `path` = '1300', `menu_name` = '消息通知' WHERE `id` = 1205;
-- 附件管理/个人中心迁出平台专属子树：建树从根递归，父级不在权限内会导致整棵丢弃，租户因此传不了图
UPDATE `eb_system_menus` SET `pid` = 12, `header` = 'setting', `path` = '12' WHERE `id` IN (1063, 1082);
UPDATE `eb_system_menus` SET `menu_name` = '个人中心' WHERE `id` = 1082;
-- 客服页面广告能力已并入客户端装修，先对租户隐藏保留平台侧编辑入口以迁移存量内容
UPDATE `eb_system_menus` SET `is_tenant` = 0 WHERE `id` IN (656, 913, 915, 916);
UPDATE `eb_system_menus` SET `sort` = 50 WHERE `id` = 12;
UPDATE `eb_system_menus` SET `sort` = 20 WHERE `id` = 1200;
UPDATE `eb_system_menus` SET `sort` = 10 WHERE `id` = 25;

-- 广告分层：平台配默认广告，付费套餐方可自定义
-- custom_html 已在 eb_application_theme 建表语句中定义，此处不再重复 ADD（否则报 1060）
ALTER TABLE `eb_tenant_plan` ADD `custom_ad` tinyint(1) NOT NULL DEFAULT '0' COMMENT '自定义广告位0=用平台默认,1=可自定义' AFTER `white_label`;
UPDATE `eb_tenant_plan` SET `custom_ad` = 1 WHERE `name` IN ('标准版', '旗舰版');
INSERT INTO `eb_system_config` (`tenant_id`, `menu_name`, `type`, `input_type`, `config_tab_id`, `parameter`, `upload_type`, `required`, `width`, `high`, `value`, `info`, `desc`, `sort`, `status`) VALUES
(0, 'platform_ad_banners', 'upload', 'upload', 69, '', 2, '', 100, 0, '[]', '平台默认广告图', '未开通自定义广告位的租户，其客服窗口展示这些图片（可多张轮播）', 8, 1),
(0, 'platform_ad_html', 'textarea', 'textarea', 69, '', 0, '', 100, 6, '""', '平台默认广告HTML', '选填。未配置广告图时展示此内容，支持HTML（会做安全清洗）', 7, 1);

UPDATE `eb_system_role` SET `rules` = (SELECT GROUP_CONCAT(`id`) FROM (SELECT `id` FROM `eb_system_menus` WHERE `is_tenant` = 1 AND `is_del` = 0) t) WHERE `role_name` = '租户管理员' AND `tenant_id` > 0;

-- 配置分类隔离与独立域名（与update.sql保持一致）
-- 独立域名：访客入口寻址依据，属高阶套餐能力
ALTER TABLE `eb_tenant_plan` ADD `custom_domain` tinyint(1) NOT NULL DEFAULT '0' COMMENT '独立域名0=否,1=是' AFTER `custom_ad`;
UPDATE `eb_tenant_plan` SET `custom_domain` = 1 WHERE `name` = '旗舰版';
ALTER TABLE `eb_tenant_plan` ADD `file_send` tinyint(1) NOT NULL DEFAULT '0' COMMENT '文件收发0=否,1=是' AFTER `ai_reply`;
UPDATE `eb_tenant_plan` SET `file_send` = 1 WHERE `price` > 0 AND `is_delete` = 0;
-- 自定义敏感词：租户维护自己的业务词库；平台合规词库不受套餐约束
ALTER TABLE `eb_tenant_plan` ADD `sensitive_word` tinyint(1) NOT NULL DEFAULT '0' COMMENT '自定义敏感词0=否,1=是' AFTER `file_send`;
UPDATE `eb_tenant_plan` SET `sensitive_word` = 1 WHERE `price` > 0 AND `is_delete` = 0;
-- 说明：系统设置的分类可见性不再依赖硬编码，改为按"分类下是否含租户可覆盖配置项"动态判定
-- （见 SystemConfigServices::filterTenantTabs），新增配置项时可见性自动跟随，无需再改数据

-- 客服端配置的全局默认与应用级覆盖（与update.sql保持一致）
-- 游客头像与客服反馈原为租户级单份，现支持按应用差异化：
-- 应用未单独配置时留空，读取时回落「系统设置-客服端配置」里的租户全局值，
-- 与"平台默认+租户覆盖"是同一套两层模型，单应用租户只需配一次
ALTER TABLE `eb_application_theme`
  ADD `tourist_avatar` text COMMENT '游客头像池,空=继承租户全局' AFTER `custom_html`,
  ADD `service_feedback` varchar(255) NOT NULL DEFAULT '' COMMENT '客服反馈文案,空=继承租户全局' AFTER `tourist_avatar`;

-- 移除失效的客服页面广告，并补齐配置分类图标（与update.sql保持一致）
-- 该功能存取的缓存key按租户隔离（kf_adv:{tenant_id}），但菜单是平台专属，
-- 平台管理员的租户上下文为0、只会写入全局key kf_adv，而访客读的是 kf_adv:{自己租户}，
-- 两边永远对不上，等于死配置。广告能力已由「客服端配置-平台默认广告」与「客户端装修」承接。
DELETE FROM `eb_system_menus` WHERE `id` IN (656, 913, 915, 916);
DELETE FROM `eb_cache` WHERE `key` LIKE 'kf_adv%';

-- 配置分类补图标：顶级tab渲染icon，缺失时与其它分类视觉不齐
UPDATE `eb_system_config_tab` SET `icon` = 'md-chatbubbles' WHERE `id` = 69;
UPDATE `eb_system_config_tab` SET `icon` = 'md-bulb' WHERE `id` = 90;

-- 菜单按端隔离与订阅能力门禁（与update.sql保持一致）
-- 平台账号的租户上下文为0，进「我的订阅」「AI客服设置」「客户端装修」只会得到
-- "请切换到租户视角"的报错，这类页面不应出现在平台端菜单里。
-- 原有 is_tenant 只表达"租户可见"，无法表达"仅租户可见"，故补一列。
ALTER TABLE `eb_system_menus`
  ADD `is_platform` tinyint(1) NOT NULL DEFAULT 1 COMMENT '平台端是否可见,0=仅租户端' AFTER `is_tenant`;

UPDATE `eb_system_menus` SET `is_platform` = 0
  WHERE `id` IN (1240,1241,1242,1243,1244,1245,1250,1251,1252,1253,1260,1261,1262);

-- 订阅能力门禁接口：功能页据此展示内容或升级提示
INSERT INTO `eb_system_menus`
  (`id`,`pid`,`menu_name`,`api_url`,`methods`,`is_show`,`is_tenant`,`is_platform`,`auth_type`,`is_del`,`is_show_path`,`sort`,`params`,`header`,`path`,`unique_auth`,`icon`,`module`,`controller`,`action`,`access`,`menu_path`)
VALUES
  (1301,1240,'订阅能力门禁','api/admin/setting/tenant/features','GET',0,1,0,2,0,0,0,'[]','','12','','','admin','','',0,'');

UPDATE `eb_system_role` SET `rules` = CONCAT(`rules`, ',1301')
  WHERE `id` = 2 AND CONCAT(',',`rules`,',') NOT LIKE '%,1301,%';

-- 租户端订阅订单导出（与update.sql保持一致）
-- 租户端原本只有列表接口的权限菜单，没有导出接口的，调用会被鉴权拦下
INSERT INTO `eb_system_menus`
  (`id`,`pid`,`menu_name`,`api_url`,`methods`,`is_show`,`is_tenant`,`is_platform`,`auth_type`,`is_del`,`is_show_path`,`sort`,`params`,`header`,`path`,`unique_auth`,`icon`,`module`,`controller`,`action`,`access`,`menu_path`)
VALUES
  (1302,1240,'我的订阅订单导出','api/admin/setting/tenant/orders/export','GET',0,1,0,2,0,0,0,'[]','','12','','','admin','','',0,'');

UPDATE `eb_system_role` SET `rules` = CONCAT(`rules`, ',1302')
  WHERE `tenant_id` > 0 AND CONCAT(',',`rules`,',') NOT LIKE '%,1302,%';

-- 租户视角权限接口（与update.sql保持一致）
-- 平台账号切换租户视角后，可用菜单与权限随之变化，前端需重新拉取。
-- 注意：SystemRoleServices 里的短名白名单（menuslist等）实际匹配不上——
-- 比对用的是 api/admin/xxx 完整形式，那是上游遗留的死分支，新接口须走菜单授权。
INSERT INTO `eb_system_menus`
  (`id`,`pid`,`menu_name`,`api_url`,`methods`,`is_show`,`is_tenant`,`is_platform`,`auth_type`,`is_del`,`is_show_path`,`sort`,`params`,`header`,`path`,`unique_auth`,`icon`,`module`,`controller`,`action`,`access`,`menu_path`)
SELECT 1303,`pid`,'获取当前视角权限','api/admin/viewAuth','GET',0,1,1,2,0,0,0,'[]','',`path`,'','','admin','','',0,''
  FROM `eb_system_menus` WHERE `id` = 1042;

UPDATE `eb_system_role` SET `rules` = CONCAT(`rules`, ',1303')
  WHERE CONCAT(',',`rules`,',') NOT LIKE '%,1303,%';

-- 悬浮挂件配置（与update.sql保持一致）
-- 悬浮按钮由嵌入脚本在接入方页面渲染，早于聊天窗口打开，
-- 拿不到走websocket下发的装修数据，故新增公开接口 GET /api/mobile/widget?token=xxx。
-- 「PC悬浮图标」「移动端图标」两项此前虽在后台可配但从未生效，一并由该接口下发。
-- 生效优先级：接入方显式传参 > 后台装修 > 脚本内置默认值。
ALTER TABLE `eb_application_theme`
  ADD `show_tip` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否显示悬浮客服按钮' AFTER `mobile_icon`,
  ADD `window_style` varchar(20) NOT NULL DEFAULT 'float' COMMENT '窗口形态float悬浮/center居中' AFTER `show_tip`;

-- 平台销售线索（与update.sql保持一致）
-- 官网表单与手工录入的潜在客户在此沉淀，按阶段推进直至开通租户。
-- 平台自身数据，不属于任何租户，模型中 tenantScoped=false。
-- 升级账本：全量脚本已含所有历史版本，故预置版本号，install 后不会重复执行增量脚本
CREATE TABLE IF NOT EXISTS `eb_system_upgrade` (
  `version` varchar(20) NOT NULL COMMENT '版本号',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '脚本描述',
  `checksum` varchar(32) NOT NULL DEFAULT '' COMMENT '文件md5,用于核对脚本是否被改动',
  `cost_ms` int NOT NULL DEFAULT 0 COMMENT '执行耗时',
  `create_time` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='升级版本账本';

INSERT INTO `eb_system_upgrade` (`version`,`name`,`create_time`) VALUES
('20260902_01','platform_crm',UNIX_TIMESTAMP()),
('20260902_02','widget_config',UNIX_TIMESTAMP()),
('20260902_03','fix_lead_menu_path',UNIX_TIMESTAMP()),
('20260902_04','website_url_config',UNIX_TIMESTAMP()),
('20260903_01','drop_website_url_config',UNIX_TIMESTAMP()),
('20260903_02','visitor_account',UNIX_TIMESTAMP()),
('20260903_03','plan_file_send',UNIX_TIMESTAMP()),
('20260903_04','launcher_icon_len',UNIX_TIMESTAMP()),
('20260903_05','launcher_icon_len_2000',UNIX_TIMESTAMP()),
('20260904_01','chat_history_menu',UNIX_TIMESTAMP()),
('20260904_02','chat_history_export',UNIX_TIMESTAMP()),
('20260904_03','chat_history_rename_export_all',UNIX_TIMESTAMP()),
('20260908_01','export_task',UNIX_TIMESTAMP()),
('20260908_02','sensitive_word',UNIX_TIMESTAMP()),
('20260908_03','chat_session',UNIX_TIMESTAMP()),
('20260908_04','reply_timeout',UNIX_TIMESTAMP()),
('20260908_05','faq_card',UNIX_TIMESTAMP()),
('20260908_06','default_tenant_faq_speech',UNIX_TIMESTAMP()),
('20260908_07','fix_faq_menu_conflict',UNIX_TIMESTAMP()),
('20260909_01','visitor_transcript',UNIX_TIMESTAMP()),
('20260910_01','record_exempt',UNIX_TIMESTAMP()),
('20260910_02','platform_support',UNIX_TIMESTAMP());

CREATE TABLE IF NOT EXISTS `eb_platform_lead` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '联系人',
  `company` varchar(100) NOT NULL DEFAULT '' COMMENT '公司名称',
  `phone` varchar(30) NOT NULL DEFAULT '' COMMENT '联系电话',
  `email` varchar(100) NOT NULL DEFAULT '' COMMENT '邮箱',
  `scale` varchar(30) NOT NULL DEFAULT '' COMMENT '团队规模',
  `intent_plan` varchar(50) NOT NULL DEFAULT '' COMMENT '意向套餐',
  `content` varchar(1000) NOT NULL DEFAULT '' COMMENT '需求描述',
  `source` varchar(20) NOT NULL DEFAULT 'website' COMMENT '来源website官网表单/chat客服会话/manual手工录入',
  `stage` tinyint(1) NOT NULL DEFAULT 1 COMMENT '阶段1新线索2已联系3意向确认4已成交5已关闭',
  `owner_id` int NOT NULL DEFAULT 0 COMMENT '跟进人管理员ID',
  `tenant_id` int NOT NULL DEFAULT 0 COMMENT '成交后关联的租户ID',
  `next_follow_time` int NOT NULL DEFAULT 0 COMMENT '下次跟进时间',
  `last_follow_time` int NOT NULL DEFAULT 0 COMMENT '最近跟进时间',
  `chat_user_id` int NOT NULL DEFAULT 0 COMMENT '来源会话的访客ID,用于去重',
  `from_kefu` varchar(50) NOT NULL DEFAULT '' COMMENT '转线索的客服名称,客服表与管理员表无关联故只存名称',
  `is_delete` tinyint(1) NOT NULL DEFAULT 0,
  `create_time` int NOT NULL DEFAULT 0,
  `update_time` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_stage` (`stage`),
  KEY `idx_owner` (`owner_id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_chat_user` (`chat_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='平台销售线索';

CREATE TABLE IF NOT EXISTS `eb_platform_lead_follow` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lead_id` int NOT NULL DEFAULT 0 COMMENT '线索ID',
  `admin_id` int NOT NULL DEFAULT 0 COMMENT '操作人',
  `admin_name` varchar(50) NOT NULL DEFAULT '' COMMENT '操作人名称,冗余避免列表联查',
  `content` varchar(1000) NOT NULL DEFAULT '' COMMENT '跟进内容',
  `stage_from` tinyint(1) NOT NULL DEFAULT 0 COMMENT '变更前阶段,0表示本次未变更阶段',
  `stage_to` tinyint(1) NOT NULL DEFAULT 0 COMMENT '变更后阶段',
  `create_time` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_lead` (`lead_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='线索跟进记录';

-- 访客账号：绑定手机号后可换设备接续会话。手机号可枚举故非凭据，
-- 绑定/登录都要过验证码或密码；token_version 变更即吊销旧续接令牌。
CREATE TABLE IF NOT EXISTS `eb_chat_visitor_account` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL DEFAULT 0 COMMENT '所属租户ID',
  `appid` varchar(32) NOT NULL DEFAULT '' COMMENT '所属应用',
  `phone` varchar(20) NOT NULL DEFAULT '' COMMENT '绑定手机号',
  `password_hash` varchar(255) NOT NULL DEFAULT '' COMMENT '密码哈希,空串=未设密码',
  `user_id` int NOT NULL DEFAULT 0 COMMENT '关联的访客ID(eb_chat_user.id)',
  `token_version` int NOT NULL DEFAULT 1 COMMENT '续接令牌版本,改密码或注销时自增',
  `failed_attempts` int NOT NULL DEFAULT 0 COMMENT '连续失败次数',
  `locked_until` int NOT NULL DEFAULT 0 COMMENT '锁定到期时间戳',
  `last_login_time` int NOT NULL DEFAULT 0 COMMENT '最近登录时间',
  `create_time` int NOT NULL DEFAULT 0,
  `update_time` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tenant_app_phone` (`tenant_id`, `appid`, `phone`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='访客账号';

-- 菜单挂在租户管理下，仅平台端可见
INSERT INTO `eb_system_menus` (`id`,`pid`,`menu_name`,`menu_path`,`api_url`,`methods`,`is_show`,`is_tenant`,`is_platform`,`auth_type`,`is_del`,`is_show_path`,`sort`,`params`,`header`,`path`,`unique_auth`,`icon`,`module`,`controller`,`action`,`access`) VALUES
(1310,1200,'销售线索','/admin/tenant/lead','','',1,0,1,1,0,0,90,'[]','tenant','1200','platform-lead','','admin','','',0),
(1311,1310,'线索列表','','api/admin/setting/lead','GET',0,0,1,2,0,0,0,'[]','','1200/1310','','','admin','','',0),
(1312,1310,'线索选项','','api/admin/setting/lead/options','GET',0,0,1,2,0,0,0,'[]','','1200/1310','','','admin','','',0),
(1313,1310,'线索详情','','api/admin/setting/lead/<id>','GET',0,0,1,2,0,0,0,'[]','','1200/1310','','','admin','','',0),
(1314,1310,'录入线索','','api/admin/setting/lead','POST',0,0,1,2,0,0,0,'[]','','1200/1310','','','admin','','',0),
(1315,1310,'记录跟进','','api/admin/setting/lead/follow/<id>','POST',0,0,1,2,0,0,0,'[]','','1200/1310','','','admin','','',0),
(1316,1310,'转派线索','','api/admin/setting/lead/assign/<id>','POST',0,0,1,2,0,0,0,'[]','','1200/1310','','','admin','','',0),
(1317,1310,'关联租户','','api/admin/setting/lead/link/<id>','POST',0,0,1,2,0,0,0,'[]','','1200/1310','','','admin','','',0),
(1318,1310,'删除线索','','api/admin/setting/lead/<id>','DELETE',0,0,1,2,0,0,0,'[]','','1200/1310','','','admin','','',0);

-- 历史对话：挂在客服管理下，租户专属（平台视角无租户上下文）
INSERT INTO `eb_system_menus` (`id`,`pid`,`menu_name`,`menu_path`,`api_url`,`methods`,`is_show`,`is_tenant`,`is_platform`,`auth_type`,`is_del`,`is_show_path`,`sort`,`params`,`header`,`path`,`unique_auth`,`icon`,`module`,`controller`,`action`,`access`) VALUES
(1320,165,'历史对话','/admin/chat/history','','',1,1,0,1,0,0,25,'[]','kefu','165','chat-history','','admin','','',1),
(1321,1320,'历史对话列表','','api/admin/chat/history/sessions','GET',0,1,0,2,0,0,0,'[]','','165/1320','','','admin','','',1),
(1322,1320,'历史访客列表','','api/admin/chat/history/visitors','GET',0,1,0,2,0,0,0,'[]','','165/1320','','','admin','','',1),
(1323,1320,'访客会话列表','','api/admin/chat/history/visitor/<id>','GET',0,1,0,2,0,0,0,'[]','','165/1320','','','admin','','',1),
(1324,1320,'历史对话内容','','api/admin/chat/history/records','GET',0,1,0,2,0,0,0,'[]','','165/1320','','','admin','','',1),
(1325,1320,'导出对话','','api/admin/chat/history/export','GET',0,1,0,2,0,0,0,'[]','','165/1320','','','admin','','',1),
(1326,1320,'全局导出对话','','api/admin/chat/history/export_all','POST',0,1,0,2,0,0,0,'[]','','165/1320','','','admin','','',1);

-- 下载中心：导出任务表与菜单
-- 同步导出受限于请求超时与单进程内存，改为落任务由常驻进程异步产出，
-- 文件带保留期，到期由 GC 连同记录一起清掉。
CREATE TABLE IF NOT EXISTS `eb_export_task` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT '0' COMMENT '租户ID',
  `admin_id` int(11) NOT NULL DEFAULT '0' COMMENT '发起人ID',
  `admin_name` varchar(64) NOT NULL DEFAULT '' COMMENT '发起人名称，冗余保留',
  `type` varchar(32) NOT NULL DEFAULT '' COMMENT '导出类型',
  `type_name` varchar(64) NOT NULL DEFAULT '' COMMENT '导出类型中文名',
  `params` varchar(2000) NOT NULL DEFAULT '' COMMENT '筛选条件JSON',
  `format` varchar(8) NOT NULL DEFAULT 'csv' COMMENT '文件格式 csv/xlsx',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0待处理 1处理中 2成功 3失败',
  `file_url` varchar(255) NOT NULL DEFAULT '' COMMENT '成功后的下载地址',
  `file_size` int(11) NOT NULL DEFAULT '0' COMMENT '文件字节数',
  `row_count` int(11) NOT NULL DEFAULT '0' COMMENT '数据行数，不含表头',
  `message` varchar(255) NOT NULL DEFAULT '' COMMENT '失败原因或截断说明',
  `expire_time` int(11) NOT NULL DEFAULT '0' COMMENT '文件过期时间',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `start_time` int(11) NOT NULL DEFAULT '0',
  `finish_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_status_id` (`status`,`id`),
  KEY `idx_expire` (`expire_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='导出任务';

INSERT INTO `eb_system_menus` (`id`,`pid`,`menu_name`,`menu_path`,`api_url`,`methods`,`is_show`,`is_tenant`,`is_platform`,`auth_type`,`is_del`,`is_show_path`,`sort`,`params`,`header`,`path`,`unique_auth`,`icon`,`module`,`controller`,`action`,`access`) VALUES
(1330,12,'下载中心','/admin/export/list','','',1,1,1,1,0,0,5,'[]','setting','12','export-center','','admin','','',1),
(1331,1330,'导出任务列表','','api/admin/export/task','GET',0,1,1,2,0,0,0,'[]','','12/1330','','','admin','','',1),
(1332,1330,'删除导出任务','','api/admin/export/task/<id>','DELETE',0,1,1,2,0,0,0,'[]','','12/1330','','','admin','','',1);

-- 敏感词：两级词库（tenant_id=0为平台合规词，对所有租户强制生效）与命中留痕
CREATE TABLE IF NOT EXISTS `eb_sensitive_word` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT '0' COMMENT '租户ID，0为平台级合规词',
  `word` varchar(64) NOT NULL DEFAULT '' COMMENT '词条',
  `category` varchar(32) NOT NULL DEFAULT '' COMMENT '分类，便于批量管理',
  `action` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1拦截 2替换 3仅告警',
  `scope` tinyint(1) NOT NULL DEFAULT '7' COMMENT '作用范围位掩码 1访客 2客服 4AI',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0停用 1启用',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_word` (`tenant_id`,`word`),
  KEY `idx_tenant_status` (`tenant_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='敏感词库';

CREATE TABLE IF NOT EXISTS `eb_sensitive_hit` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT '0' COMMENT '租户ID',
  `word_id` int(11) NOT NULL DEFAULT '0' COMMENT '命中词ID',
  `word` varchar(64) NOT NULL DEFAULT '' COMMENT '命中词，冗余保留以防词条被删',
  `is_platform` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否平台级词命中',
  `action` tinyint(1) NOT NULL DEFAULT '1' COMMENT '实际处置',
  `scope` tinyint(1) NOT NULL DEFAULT '1' COMMENT '来源 1访客 2客服 4AI',
  `appid` varchar(32) NOT NULL DEFAULT '' COMMENT '应用',
  `user_id` int(11) NOT NULL DEFAULT '0' COMMENT '发送方',
  `to_user_id` int(11) NOT NULL DEFAULT '0' COMMENT '接收方',
  `nickname` varchar(64) NOT NULL DEFAULT '' COMMENT '发送方昵称，冗余保留',
  `content` varchar(500) NOT NULL DEFAULT '' COMMENT '原文片段，留作证据链',
  `handled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0待处理 1已处理',
  `create_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_time` (`tenant_id`,`create_time`),
  KEY `idx_tenant_handled` (`tenant_id`,`handled`),
  KEY `idx_word` (`word_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='敏感词命中记录';

INSERT INTO `eb_system_menus` (`id`,`pid`,`menu_name`,`menu_path`,`api_url`,`methods`,`is_show`,`is_tenant`,`is_platform`,`auth_type`,`is_del`,`is_show_path`,`sort`,`params`,`header`,`path`,`unique_auth`,`icon`,`module`,`controller`,`action`,`access`) VALUES
(1340,12,'敏感词管理','/admin/sensitive/word','','',1,1,1,1,0,0,4,'[]','setting','12','sensitive-word','','admin','','',1),
(1341,1340,'敏感词列表','','api/admin/sensitive/word','GET',0,1,1,2,0,0,0,'[]','','12/1340','','','admin','','',1),
(1342,1340,'新增敏感词','','api/admin/sensitive/word','POST',0,1,1,2,0,0,0,'[]','','12/1340','','','admin','','',1),
(1343,1340,'修改敏感词','','api/admin/sensitive/word/<id>','PUT',0,1,1,2,0,0,0,'[]','','12/1340','','','admin','','',1),
(1344,1340,'删除敏感词','','api/admin/sensitive/word/<id>','DELETE',0,1,1,2,0,0,0,'[]','','12/1340','','','admin','','',1),
(1345,1340,'批量导入敏感词','','api/admin/sensitive/word/import','POST',0,1,1,2,0,0,0,'[]','','12/1340','','','admin','','',1),
(1346,12,'敏感词命中','/admin/sensitive/hit','','',1,1,1,1,0,0,3,'[]','setting','12','sensitive-hit','','admin','','',1),
(1347,1346,'命中记录列表','','api/admin/sensitive/hit','GET',0,1,1,2,0,0,0,'[]','','12/1346','','','admin','','',1),
(1348,1346,'标记命中已处理','','api/admin/sensitive/hit/handle/<id>','PUT',0,1,1,2,0,0,0,'[]','','12/1346','','','admin','','',1);

-- 无人应答提醒阈值：挂在客服配置分类下，租户可覆盖
INSERT INTO `eb_system_config` (`id`,`tenant_id`,`menu_name`,`type`,`input_type`,`config_tab_id`,`parameter`,`upload_type`,`required`,`width`,`high`,`value`,`info`,`desc`,`sort`,`status`) VALUES
(390,0,'reply_timeout','text','number',69,'',0,'',100,0,'180','无人应答提醒阈值(秒)','访客发问后超过该秒数仍无客服回复即触发提醒；填 0 关闭。AI 接待的会话不计入',0,1);

-- 客服会话（一次接待）：绩效与满意度的共同粒度
CREATE TABLE IF NOT EXISTS `eb_chat_session` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT '0' COMMENT '租户ID',
  `appid` varchar(32) NOT NULL DEFAULT '' COMMENT '应用',
  `kefu_user_id` int(11) NOT NULL DEFAULT '0' COMMENT '接待客服',
  `visitor_user_id` int(11) NOT NULL DEFAULT '0' COMMENT '访客',
  `is_ai` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否AI坐席接待',
  `transferred` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否发生过转人工',
  `start_time` int(11) NOT NULL DEFAULT '0' COMMENT '首条消息时间',
  `last_time` int(11) NOT NULL DEFAULT '0' COMMENT '最后一条消息时间',
  `end_time` int(11) NOT NULL DEFAULT '0' COMMENT '结束时间',
  `end_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0进行中 1超时自动 2客服结束',
  `visitor_msg_num` int(11) NOT NULL DEFAULT '0' COMMENT '访客消息数',
  `kefu_msg_num` int(11) NOT NULL DEFAULT '0' COMMENT '客服消息数',
  `first_reply_cost` int(11) NOT NULL DEFAULT '0' COMMENT '首次响应秒数，0=未回复',
  `reply_cost_sum` int(11) NOT NULL DEFAULT '0' COMMENT '响应耗时累计，用于算平均',
  `reply_count` int(11) NOT NULL DEFAULT '0' COMMENT '有效响应次数',
  `pending_since` int(11) NOT NULL DEFAULT '0' COMMENT '访客最新一条待回复消息的时间，0=无待回复',
  `max_pending_cost` int(11) NOT NULL DEFAULT '0' COMMENT '本次接待中访客最长等待秒数',
  `alerted_at` int(11) NOT NULL DEFAULT '0' COMMENT '最近一次超时告警时间，用于告警去重',
  `rate` tinyint(1) NOT NULL DEFAULT '0' COMMENT '满意度1-5，0=未评价',
  `rate_remark` varchar(255) NOT NULL DEFAULT '' COMMENT '评价留言',
  `rate_time` int(11) NOT NULL DEFAULT '0' COMMENT '评价时间',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1进行中 2已结束',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_tenant_start` (`tenant_id`,`start_time`),
  KEY `idx_kefu_start` (`kefu_user_id`,`start_time`),
  KEY `idx_pair_status` (`tenant_id`,`kefu_user_id`,`visitor_user_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客服会话（一次接待）';

INSERT INTO `eb_system_menus` (`id`,`pid`,`menu_name`,`menu_path`,`api_url`,`methods`,`is_show`,`is_tenant`,`is_platform`,`auth_type`,`is_del`,`is_show_path`,`sort`,`params`,`header`,`path`,`unique_auth`,`icon`,`module`,`controller`,`action`,`access`) VALUES
(1350,165,'客服绩效','/admin/chat/performance','','',1,1,0,1,0,0,26,'[]','kefu','165','chat-performance','','admin','','',1),
(1351,1350,'绩效概览','','api/admin/chat/performance/overview','GET',0,1,0,2,0,0,0,'[]','','165/1350','','','admin','','',1),
(1352,1350,'客服绩效明细','','api/admin/chat/performance/agents','GET',0,1,0,2,0,0,0,'[]','','165/1350','','','admin','','',1),
(1353,1350,'绩效趋势','','api/admin/chat/performance/trend','GET',0,1,0,2,0,0,0,'[]','','165/1350','','','admin','','',1),
(1354,1350,'会话明细','','api/admin/chat/performance/sessions','GET',0,1,0,2,0,0,0,'[]','','165/1350','','','admin','','',1),
(1355,1350,'超时未应答会话','','api/admin/chat/performance/pending','GET',0,1,0,2,0,0,0,'[]','','165/1350','','','admin','','',1);

-- --------------------------------------------------------

--
-- 默认租户（平台自用客服）的常见问题与公共话术，对应增量 V20260908_06
--
-- 平台用默认租户(id=1)接待自己的客户，问答与话术不预置的话，新装环境
-- 访客窗口没有任何卡片、客服工作台话术面板也是空的。内容面向本产品：
-- 套餐配额、接入方式、功能边界，接待对象是潜在客户与已开通租户的管理员。
--
-- add_time 取默认租户创建时间(1625735898)之后 1~2 小时，与租户、应用的
-- 创建时间保持先后关系。新装库这三张表为空，故直接写显式 id。
-- 本段须在 eb_chat_auto_reply / eb_category / eb_chat_service_speechcraft
-- 补 tenant_id 的 ALTER 之后执行，因此放在文件末尾。

-- 常见问题卡片索引，与增量脚本 V20260908_05 保持一致
ALTER TABLE `eb_chat_auto_reply` ADD INDEX `idx_faq` (`tenant_id`,`appid`,`is_faq`,`sort`);

INSERT INTO `eb_category` (`id`,`tenant_id`,`pid`,`owner_id`,`name`,`sort`,`type`,`other`,`add_time`) VALUES
(1,1,0,0,'开场问候',100,1,'',1625739498),
(2,1,0,0,'售前咨询',95,1,'',1625739498),
(3,1,0,0,'套餐与报价',90,1,'',1625739498),
(4,1,0,0,'试用与开通',85,1,'',1625739498),
(5,1,0,0,'接入对接',80,1,'',1625739498),
(6,1,0,0,'功能使用',75,1,'',1625739498),
(7,1,0,0,'账单发票',70,1,'',1625739498),
(8,1,0,0,'故障排查',65,1,'',1625739498),
(9,1,0,0,'安抚致歉',60,1,'',1625739498),
(10,1,0,0,'转接升级',55,1,'',1625739498),
(11,1,0,0,'结束回访',50,1,'',1625739498);

INSERT INTO `eb_chat_service_speechcraft` (`id`,`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`) VALUES
(1,1,0,1,'标准开场','您好，这里是在线客服，很高兴为您服务，请问有什么可以帮您？',6,1625739498),
(2,1,0,1,'售前开场','您好，欢迎咨询。想先了解产品功能、套餐价格，还是接入方式？我按您关心的先说。',5,1625739498),
(3,1,0,1,'已接入稍等','您好，已收到您的留言，我正在为您查看，请稍等片刻。',4,1625739498),
(4,1,0,1,'非工作时间','您好，当前为非工作时间，您的留言我们已经记录，工程师上线后会第一时间回复您。',3,1625739498),
(5,1,0,1,'老客户问候','您好，看到您已经是我们的用户了，请问这次是使用上遇到问题，还是想调整套餐？',2,1625739498),
(6,1,0,1,'简短应答','在的，您请讲，我这边正在为您跟进。',1,1625739498),
(7,1,0,2,'产品一句话介绍','我们是一套多租户在线客服系统：访客从您的网站、H5、小程序或 APP 发起咨询，您的客服在统一工作台接待，支持关键词自动回复、AI 智能客服、话术库、满意度评价与绩效报表。',7,1625739498),
(8,1,0,2,'了解客户场景','为了给您更准确的建议，方便问一下：您大概几个人做客服？日均咨询量多少？主要从网站还是小程序、APP 进来？',6,1625739498),
(9,1,0,2,'适用场景','电商售后、SaaS 售前、设备运维、门店加盟咨询这类场景用得比较多。您可以说下您的业务，我判断一下是否合适。',5,1625739498),
(10,1,0,2,'与自建对比','自建客服要处理长连接、消息可靠投递、多端同步和历史检索，投入不小。我们这边开箱即用，接入通常半小时内完成，后续功能随套餐解锁，不用自己维护。',4,1625739498),
(11,1,0,2,'多应用支持','支持。同一账号下可以建多个应用，各自独立的访客、会话与常见问题，适合一家公司多个站点或多个品牌分开接待。',3,1625739498),
(12,1,0,2,'可否先看演示','可以。注册后用免费版就能把完整流程跑通，也可以直接打开应用自带的会话窗口体验访客侧效果，不用改代码。',2,1625739498),
(13,1,0,2,'引导留资','方便留个联系方式吗？我把方案和报价整理好发您，也可以约个时间做一次在线演示。',1,1625739498),
(14,1,0,3,'四档概览','共四档：免费版（1应用/2坐席/日500条/记录7天）、体验版（2应用/5坐席/日5000条/记录30天）、标准版（5应用/20坐席/日2万条/记录180天）、旗舰版（应用与消息不限/100坐席/记录永久）。',7,1625739498),
(15,1,0,3,'功能分界','功能上的分界点是：关键词自动回复、文件收发、自定义敏感词、APP推送从体验版起；AI 智能客服、客户端装修、自定义广告位、数据导出从标准版起；独立域名与去除平台标识为旗舰版。',6,1625739498),
(16,1,0,3,'选型建议','给您个判断口径：只是试水用免费版；有固定客服岗、需要自动回复和文件收发选体验版；要 AI 应答、数据导出和品牌化选标准版；要独立域名、去平台标识或坐席较多选旗舰版。',5,1625739498),
(17,1,0,3,'报价引导','各档价格以官网价格页为准。您告诉我坐席数和日咨询量，我帮您核算哪一档更划算，避免买高了浪费。',4,1625739498),
(18,1,0,3,'超量说明','配额是硬上限：坐席数、应用数超出后新增会被拦下，日消息量用完当天不再收发。升级后立即放开，原有配置和数据都保留。',3,1625739498),
(19,1,0,3,'升级即时生效','升级是即时生效的，支付完成后配额和功能马上可用，不需要重新接入或重新配置。',2,1625739498),
(20,1,0,3,'续费顺延','续费会在当前到期时间上顺延，剩余天数不会作废，可以放心提前续。',1,1625739498),
(21,1,0,4,'免费版说明','注册即开通免费版，不限时长也不需要付费，包含 1 个应用、2 个坐席、每日 500 条消息、200MB 存储、记录保留 7 天，足够把接入跑通。',6,1625739498),
(22,1,0,4,'开通流程','开通后您会拿到管理员账号，登录后台先建应用拿接入代码，再添加客服坐席，就可以开始接待了。整个过程通常十几分钟。',5,1625739498),
(23,1,0,4,'初始密码提醒','账号开通时会给一个初始密码，建议首次登录后立即在个人设置里修改，避免安全风险。',4,1625739498),
(24,1,0,4,'试用转正式','试用期间的所有配置、话术、常见问题和历史数据，升级后都完整保留，不需要重做。',3,1625739498),
(25,1,0,4,'协助配置','如果您希望我们帮忙做初始配置（常见问题、话术、自动回复），把您的业务资料发我，我这边整理好导入，您确认即可。',2,1625739498),
(26,1,0,4,'催办跟进','您上次说要试一下，请问接入过程还顺利吗？有卡住的地方我随时可以协助。',1,1625739498),
(27,1,0,5,'接入三步','三步：后台「应用管理」建应用拿 appid 和接入代码；把代码贴到您的页面；需要识别访客身份的，把您系统的 uid、昵称、头像随参数传过来。',8,1625739498),
(28,1,0,5,'appid 与密钥','appid 用于标识应用，可以出现在前端；app_secret 是服务端签名用的密钥，务必只存在您的服务器上，不要写进网页或 APP 包。泄露了可以在应用详情重置。',7,1625739498),
(29,1,0,5,'两种接入模式','标准接入直接信任前端传的 uid，配置简单、自带窗口可直接用；签名接入要求服务端下发 sign（md5(appid+uid+timestamp+app_secret)，5分钟有效），能防冒充，适合涉及订单和账户的场景。',6,1625739498),
(30,1,0,5,'游客模式','不传 uid 时按游客处理，系统会自动生成临时标识，同一浏览器内会话保持连续，适合官网这种未登录也要能咨询的场景。',5,1625739498),
(31,1,0,5,'多端接入','网站和 H5 直接嵌代码或跳转会话页；小程序和 APP 用 WebView 打开会话页并传访客参数即可，不需要单独的 SDK。',4,1625739498),
(32,1,0,5,'验签失败排查','验签失败一般是三种原因：timestamp 用了毫秒（应为秒）、拼接顺序不对（appid+uid+timestamp+app_secret）、或服务器时间偏差超过 5 分钟。麻烦把您的拼接串发我，我帮您比对。',3,1625739498),
(33,1,0,5,'窗口打不开','会话窗口打不开，麻烦确认接入代码里的 appid 是否正确、页面是否有跨域或 CSP 限制，并把浏览器控制台的报错截图发我。',2,1625739498),
(34,1,0,5,'对接文档','接入代码和参数说明都在后台「应用管理」里，展开应用即可查看和复制。有需要我也可以直接把对应的代码片段发您。',1,1625739498),
(35,1,0,6,'添加坐席','后台「客服管理」新增坐席，填账号和昵称即可；权限用角色控制，可按菜单粒度授权。开通时自带的「租户管理员」角色包含全部租户侧权限。',8,1625739498),
(36,1,0,6,'自动回复配置','在后台配「关键词 → 答案」，多个关键词用逗号隔开。访客消息里出现任一关键词就会自动回复，命中多条时优先返回匹配更精确、排序更靠前的一条。关键词写短词更容易命中。',7,1625739498),
(37,1,0,6,'常见问题卡片','在「常见问题」维护问答，勾选「展示在卡片」的会在访客新会话时弹出，最多 8 条，按排序值排列。访客点卡片直接给预设答案，不消耗 AI 额度。',6,1625739498),
(38,1,0,6,'话术使用','公共话术由管理员统一维护、全体坐席可用；坐席也能建自己的分类和话术。接待时从话术面板按分类选取插入，适合固化标准口径。',5,1625739498),
(39,1,0,6,'AI 智能客服','AI 从标准版起提供，标准版每日 200 次、旗舰版每日 2000 次。访客消息先由 AI 应答，答不上或访客要求时自动转人工；额度用完当天不再调用 AI，人工接待不受影响。',4,1625739498),
(40,1,0,6,'敏感词','体验版起支持自定义词库，处理方式可选替换星号、拦截不发送或仅告警留痕，访客端、客服端与 AI 回复三条链路都会过滤，命中记录可查。',3,1625739498),
(41,1,0,6,'满意度评价','接待结束时可邀请访客评价，客服也能主动发起。评价结果计入绩效报表，可按坐席和时间查看。',2,1625739498),
(42,1,0,6,'数据导出','数据导出从标准版起提供。在「历史对话」按时间和应用筛选后提交导出任务，完成后到下载中心取文件，大数据量是异步处理的，不用一直等在页面上。',1,1625739498),
(43,1,0,7,'开票流程','在「我的订阅 - 我的发票」提交开票申请，填写抬头、税号和接收邮箱，选择要开票的订阅订单即可，审核通过后由财务开具并发到您邮箱。',6,1625739498),
(44,1,0,7,'开票范围','开票金额以实付的订阅订单为准，未支付或已退款的订单不在开票范围内。',5,1625739498),
(45,1,0,7,'订单查询','所有订阅订单可以在「我的订阅 - 订阅订单」中查看，含下单时间、套餐、金额与支付状态。',4,1625739498),
(46,1,0,7,'抬头信息有误','发票抬头或税号填错了，请把正确信息发我，我这边协调财务处理，已开具的需要先作废再重开。',3,1625739498),
(47,1,0,7,'到期提醒','套餐到期前系统会在后台发送通知提醒，建议提前续费，避免访客无法发起新会话。',2,1625739498),
(48,1,0,7,'对公转账','需要对公转账的，我把收款信息发您，转账后把回单发我，我们核对到账后为您手工开通对应套餐。',1,1625739498),
(49,1,0,8,'索取定位信息','为了快速定位，麻烦提供：您的租户名称或管理员账号、出问题的应用 appid、问题现象与出现时间，有报错截图更好。',7,1625739498),
(50,1,0,8,'收不到消息','客服端收不到消息，先确认工作台是否在线、浏览器标签是否被挂起、以及套餐的日消息量是否已用完。我这边同步查一下服务端的会话记录。',6,1625739498),
(51,1,0,8,'访客发不出消息','访客发不出消息，常见原因是日消息量已达上限、套餐已到期，或消息命中了敏感词被拦截。我帮您查一下具体是哪一项。',5,1625739498),
(52,1,0,8,'历史记录看不到','聊天记录按套餐保留：免费版 7 天、体验版 30 天、标准版 180 天、旗舰版永久。超出保留期的记录会被清理且无法找回，如需长期留存建议升级套餐。',4,1625739498),
(53,1,0,8,'附件上传失败','附件上传失败多为存储额度已满或文件超出单个大小限制。您可以清理历史附件或升级套餐，我也可以帮您查一下当前用量。',3,1625739498),
(54,1,0,8,'菜单看不到','后台看不到某个菜单，通常是当前账号的角色未授权，或该功能所属套餐档位不包含。我帮您确认是权限问题还是套餐问题。',2,1625739498),
(55,1,0,8,'登记工单','这个问题需要工程师进一步排查，我先为您登记工单，处理完成后主动联系您。方便留个手机号吗？',1,1625739498),
(56,1,0,9,'致歉并优先处理','非常抱歉给您带来不便，我这边优先为您处理，请您稍等一下。',6,1625739498),
(57,1,0,9,'共情安抚','理解您的心情，影响到您正常接待确实很着急，我马上帮您核实并给出处理方案。',5,1625739498),
(58,1,0,9,'久等致歉','让您久等了，非常抱歉。您的问题我一直在跟进，一有进展我立刻告知您。',4,1625739498),
(59,1,0,9,'感谢反馈','感谢您的反馈，这个点确实值得优化，我会同步给产品同事排期评估。',3,1625739498),
(60,1,0,9,'故障说明','这次的问题是我们这边的疏漏，已经在处理了，恢复后我会第一时间通知您，给您造成的影响我们非常抱歉。',2,1625739498),
(61,1,0,9,'需求暂不支持','这个能力目前还没有提供，我如实告诉您以免耽误您的判断。我会把需求记录下来反馈给产品，如果后续排上会主动通知您。',1,1625739498),
(62,1,0,10,'转技术处理','您的问题需要技术同事进一步核查，我帮您转接过去，请稍等片刻。',6,1625739498),
(63,1,0,10,'转商务','价格和合同这块由商务同事对接更准确，我帮您转过去，您稍等。',5,1625739498),
(64,1,0,10,'排队说明','当前咨询量较大，我这边尽快为您安排专人跟进，感谢您的理解和等待。',4,1625739498),
(65,1,0,10,'升级优先级','已为您升级至高优先级处理，请保持联系方式畅通，我们会尽快给您答复。',3,1625739498),
(66,1,0,10,'跨部门协调','这个情况需要和财务/研发同事核对，我这边提交协调，预计今日内给您明确回复。',2,1625739498),
(67,1,0,10,'约定回访时间','排查需要一些时间，您方便的话我们约个时间，处理完成后我主动联系您同步结果。',1,1625739498),
(68,1,0,11,'确认是否解决','请问您的问题是否已经解决？还有其他可以帮您的吗？',6,1625739498),
(69,1,0,11,'结束语','感谢您的咨询，祝您工作顺利，后续有任何问题随时联系我们。',5,1625739498),
(70,1,0,11,'邀请评价','本次服务已完成，麻烦您对我的服务做个评价，您的反馈是我们改进的动力，非常感谢。',4,1625739498),
(71,1,0,11,'留言引导','如果后续还遇到问题，可以直接在这里留言，我们看到后会尽快回复您。',3,1625739498),
(72,1,0,11,'超时结束','由于长时间未收到您的回复，本次会话先为您结束。如仍需帮助，请随时重新发起咨询。',2,1625739498),
(73,1,0,11,'处理结果回访','您好，之前反馈的问题我们已经处理完成，想跟您确认一下现在使用是否正常？',1,1625739498);

INSERT INTO `eb_chat_auto_reply` (`id`,`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`) VALUES
(1,1,'免费,试用,体验,免费版,先试试','可以免费试用吗？',1,'可以。注册即开通免费版，不限时长、无需付费：包含 1 个接入应用、2 个客服坐席、每日 500 条消息、200MB 附件存储、聊天记录保留 7 天。
先用免费版把接入跑通，业务量上来了再按需升级，历史数据不受影响。',0,'202116257358989495',100,1625743098),
(2,1,'套餐,版本,区别,怎么选,档位,价格,多少钱,报价','各套餐有什么区别？怎么选？',1,'共四档，主要差异在坐席数、应用数、消息量与功能开关：
· 免费版：1应用/2坐席/日500条/记录7天，基础收发
· 体验版：2应用/5坐席/日5000条/记录30天，增加关键词自动回复、文件收发、自定义敏感词、APP推送
· 标准版：5应用/20坐席/日2万条/记录180天，增加 AI 智能客服、客户端装修、自定义广告位、数据导出
· 旗舰版：应用与消息不限/100坐席/记录永久，增加独立域名、去除平台标识
具体价格以官网价格页为准，也可以告诉我你的坐席数和日咨询量，我帮你算哪档更划算。',0,'202116257358989495',95,1625743098),
(3,1,'接入,对接,嵌入,集成,怎么用,安装,部署','怎么把客服接入我的网站或APP？',1,'三步：
1. 后台「应用管理」新建应用，拿到 appid 与接入代码；
2. 把接入代码贴到你网站页面（或在 APP/小程序里按文档跳转会话页）；
3. 需要识别访客身份的，把你系统的用户 uid、昵称、头像随接入参数传过来即可。
只想先看效果的话，用应用自带的会话窗口地址直接打开就能对话，不用改任何代码。',0,'202116257358989495',90,1625743098),
(4,1,'坐席,客服数,几个客服,账号数,人数,加人','一个套餐能开几个客服坐席？',1,'免费版 2 个、体验版 5 个、标准版 20 个、旗舰版 100 个。
坐席在后台「客服管理」添加，可分配不同权限。超出上限时新增会被拦下，升级套餐后立即生效，无需重新配置。',0,'202116257358989495',85,1625743098),
(5,1,'AI,智能客服,机器人,自动应答,大模型,GPT','AI 智能客服怎么开通？',1,'AI 智能客服从标准版起提供：标准版每日 200 次 AI 回复，旗舰版每日 2000 次。
开通后在后台配置 AI 坐席与知识来源，访客消息先由 AI 应答，答不上或访客要求时自动转人工。日额度用完当天不再调用 AI，人工接待不受影响。',0,'202116257358989495',80,1625743098),
(6,1,'续费,升级,换套餐,加钱,扩容,到期续','怎么续费或升级套餐？',1,'在租户后台「我的订阅」选择目标套餐下单，支付完成后配额与功能立即生效。
升级为即时生效，坐席数、应用数、消息量同步放开；续费则在当前到期时间上顺延，不会浪费剩余天数。',0,'202116257358989495',75,1625743098),
(7,1,'发票,开票,报销,税号,增值税,专票','怎么开发票？',1,'在「我的订阅 - 我的发票」提交开票申请，填写抬头、税号与接收邮箱，选择要开票的订阅订单即可。
开票金额以实付订单为准，审核通过后由财务开具并发送到你填写的邮箱。',0,'202116257358989495',70,1625743098),
(8,1,'技术支持,联系,人工,售后,客服电话,找人','遇到问题怎么联系技术支持？',1,'直接在本窗口留言即可，工作时间内会有工程师接入。
为了更快定位，建议一次性提供：你的租户名称或管理员账号、出问题的应用 appid、问题现象与出现时间，以及报错截图。非工作时间留言同样会记录，上线后优先回复。',0,'202116257358989495',65,1625743098),
(9,1,'appid,app_secret,密钥,接入代码,token,凭据','接入代码在哪里获取？appid 和 app_secret 是什么？',0,'后台「应用管理」里每个应用都有独立的 appid 与接入代码，展开即可复制。
appid 用于标识应用，可以出现在前端；app_secret 是服务端签名用的密钥，只能保存在你的服务器上，切勿写进网页或 APP 包内。密钥泄露时可在应用详情重置。',0,'202116257358989495',60,1625743098),
(10,1,'签名,标准接入,兼容模式,auth_mode,安全接入,验签','标准接入和签名接入有什么区别？',0,'标准接入：直接信任前端传来的访客 uid，配置简单，自带会话窗口与嵌入代码开箱即用，适合内部系统或对身份要求不高的场景。
签名接入：携带 uid 时必须由你的服务端下发 sign（sign=md5(appid+uid+timestamp+app_secret)，5 分钟内有效），可防止他人冒充任意用户，适合涉及订单、账户等敏感信息的场景。',0,'202116257358989495',58,1625743098),
(11,1,'访客身份,用户信息,uid,昵称,头像,识别用户','访客身份怎么传给客服系统？',0,'接入时把你系统的用户 uid、昵称、头像、手机号一并传入，客服端就能直接看到访客是谁，历史会话也会归到同一个人名下。
不传 uid 时按游客处理，系统会自动生成临时标识，同一浏览器内的会话仍然连续。',0,'202116257358989495',56,1625743098),
(12,1,'小程序,APP,H5,网站,PC,支持端,兼容','支持哪些端？网站、小程序、APP 都能用吗？',0,'都支持。网站与 H5 直接嵌入代码或跳转会话页；小程序与 APP 通过 WebView 打开会话页并传入访客参数即可。
客服端本身也有网页工作台与移动端，客服在手机上同样可以接待。',0,'202116257358989495',54,1625743098),
(13,1,'聊天记录,保留,存多久,导出,历史对话,备份','聊天记录能保存多久？可以导出吗？',0,'保留时长按套餐：免费版 7 天、体验版 30 天、标准版 180 天、旗舰版永久。
数据导出从标准版起提供，在「历史对话」按时间与应用筛选后提交导出任务，完成后到下载中心取文件。升级套餐只影响之后的保留策略，已被清理的记录无法找回，有长期留存需求建议尽早升级。',0,'202116257358989495',52,1625743098),
(14,1,'添加客服,坐席账号,权限,角色,分配,员工','客服坐席怎么添加和分配权限？',0,'后台「客服管理」新增坐席，填写账号与昵称即可；权限通过角色控制，可按菜单粒度授权，让不同岗位只看到该看的部分。
租户开通时会自动创建「租户管理员」角色，包含全部租户侧权限，其余角色可在此基础上裁剪。',0,'202116257358989495',50,1625743098),
(15,1,'自动回复,关键词,机器人回复,自动应答','关键词自动回复怎么配置？',0,'体验版起支持。在后台配置「关键词 → 答案」，多个关键词用逗号隔开；访客消息中出现其中任一关键词即自动回复，命中多条时优先返回匹配更精确、排序更靠前的一条。
关键词写短词更容易命中，例如写「退款」而不是「怎么申请退款呢」。',0,'202116257358989495',48,1625743098),
(16,1,'常见问题,FAQ,卡片,快捷问题,问题列表','常见问题卡片怎么配置？',0,'在后台「常见问题」维护问答，勾选「展示在卡片」的条目会在访客新会话时以卡片形式弹出，最多展示 8 条，顺序按排序值。
访客点卡片直接返回预设答案，不消耗 AI 额度，也不受自动回复开关影响；同一条数据同时供访客打字时命中关键词，不用两处维护。',0,'202116257358989495',46,1625743098),
(17,1,'话术,快捷短语,常用语,模板,快捷回复','客服话术怎么用？',0,'话术分公共话术与客服私有话术：公共话术由管理员统一维护、全体坐席可用；坐席也可以在工作台建自己的分类和话术。
接待时从话术面板按分类选取即可插入，适合把入驻流程、退款政策这类标准口径固化下来，减少口径不一。',0,'202116257358989495',44,1625743098),
(18,1,'敏感词,过滤,屏蔽,违禁词,风控','自定义敏感词怎么用？',0,'体验版起支持。可自定义词库并选择处理方式：替换为星号、拦截不发送、或仅告警留痕。
访客端、客服端与 AI 回复三条链路都会经过过滤，命中记录可在后台查询，便于事后追溯。',0,'202116257358989495',42,1625743098),
(19,1,'装修,样式,自定义,品牌,主题,配色,logo','客户端可以自定义样式和品牌吗？',0,'客户端装修从标准版起提供：可设置主题配色、客服头像、欢迎语、游客头像池与自定义广告位，让会话窗口与你的站点风格一致。
想彻底去掉平台标识需要旗舰版。',0,'202116257358989495',40,1625743098),
(20,1,'独立域名,白标,去标识,自有域名,OEM','可以用自己的独立域名吗？能去掉平台标识吗？',0,'独立域名与去除平台标识均为旗舰版能力。绑定后访客通过你自己的域名打开会话窗口，页面不出现平台品牌信息。
域名需全局唯一并完成解析，绑定后即时生效。',0,'202116257358989495',38,1625743098),
(21,1,'文件,附件,图片,存储,上传,容量','附件存储上限是多少？支持发文件吗？',0,'文件收发从体验版起支持。存储上限：免费版 200MB、体验版 2GB、标准版 10GB、旗舰版 50GB。
超出上限后新附件将无法上传，可清理历史附件或升级套餐；聊天中的图片同样计入该额度。',0,'202116257358989495',36,1625743098),
(22,1,'满意度,评价,打分,星级,评分','满意度评价怎么开启？',0,'接待结束时可邀请访客评价，客服也能在会话中主动发起。评价结果计入客服绩效，可在报表中按坐席与时间维度查看。
评价入口与文案可在后台调整，不需要访客额外登录。',0,'202116257358989495',34,1625743098),
(23,1,'绩效,报表,统计,数据,考核,接待量','客服绩效报表能看到什么？',0,'按坐席统计接待会话数、消息量、首响与平均响应时长、满意度评分等，支持按时间范围与应用筛选。
还提供无人应答提醒，长时间没人接待的会话会触发提示，避免访客被晾着。',0,'202116257358989495',32,1625743098),
(24,1,'到期,过期,欠费,停用,数据删除,失效','套餐到期了会怎样？数据会被删除吗？',0,'到期后账号会被限制使用，需要续费后恢复，期间访客无法发起新会话。
数据不会因到期立即删除，但聊天记录仍按套餐的保留天数正常清理，因此长期不续费可能导致历史记录过期。到期前系统会在后台发送通知提醒。',0,'202116257358989495',30,1625743098),
(25,1,'隔离,多应用,多站点,数据分开,互不影响','多个应用之间的数据是隔离的吗？',0,'是的。同一租户下每个应用的访客、会话与常见问题都按 appid 区分，互不串扰，适合一家公司多个站点或多个品牌分开接待。
租户与租户之间是更高一层的隔离，任何数据都不会跨租户可见。',0,'202116257358989495',28,1625743098),
(26,1,'安全,隐私,数据保护,合规,泄露','数据安全和隐私怎么保障？',0,'租户之间数据强隔离，接口层按租户上下文校验，越权访问会被直接拒绝；签名接入模式下访客身份需服务端签名，无法被冒充。
敏感词可拦截对话中的违规内容，附件与聊天记录按套餐策略定期清理。如需签署保密协议或了解更多合规细节，可以联系我们。',0,'202116257358989495',26,1625743098),
(27,1,'私有化,本地部署,独立部署,源码,自建','可以私有化部署吗？',0,'标准服务为 SaaS 模式，开箱即用、免运维。确有私有化需求的（如数据不出内网），可以告诉我你的部署环境与规模，我们评估后给出方案与报价。',0,'202116257358989495',24,1625743098);

-- --------------------------------------------------------

--
-- 常见问题菜单，对应增量 V20260908_05 + V20260908_07
--
-- 增量里常见问题最初分配的 1330-1335 与下载中心冲突（下载中心先占了
-- 1330-1332），V20260908_07 已把它整体迁到 1360-1365，此处直接写终态。

INSERT INTO `eb_system_menus` (`id`,`pid`,`icon`,`menu_name`,`module`,`controller`,`action`,`api_url`,`methods`,`params`,`sort`,`is_show`,`is_show_path`,`is_tenant`,`is_platform`,`access`,`menu_path`,`path`,`auth_type`,`header`,`is_header`,`unique_auth`,`is_del`) VALUES
(1360,165,'','常见问题','admin','','','','','[]',24,1,0,1,0,1,'/admin/chat/faq','165',1,'kefu',0,'chat-faq',0),
(1361,1360,'','常见问题列表','admin','','','api/admin/chat/faq','GET','[]',0,0,0,1,0,1,'','165/1360',2,'',0,'',0),
(1362,1360,'','保存常见问题','admin','','','api/admin/chat/faq','POST','[]',0,0,0,1,0,1,'','165/1360',2,'',0,'',0),
(1363,1360,'','更新常见问题','admin','','','api/admin/chat/faq/<id>','PUT','[]',0,0,0,1,0,1,'','165/1360',2,'',0,'',0),
(1364,1360,'','删除常见问题','admin','','','api/admin/chat/faq/<id>','DELETE','[]',0,0,0,1,0,1,'','165/1360',2,'',0,'',0),
(1365,1360,'','常见问题排序','admin','','','api/admin/chat/faq/sort','POST','[]',0,0,0,1,0,1,'','165/1360',2,'',0,'',0);

-- 访客全量对话（跨客服合并），对应增量 V20260909_01
INSERT INTO `eb_system_menus` (`id`,`pid`,`icon`,`menu_name`,`module`,`controller`,`action`,`api_url`,`methods`,`params`,`sort`,`is_show`,`is_show_path`,`is_tenant`,`is_platform`,`access`,`menu_path`,`path`,`auth_type`,`header`,`is_header`,`unique_auth`,`is_del`) VALUES
(1370,1320,'','访客全量对话','admin','','','api/admin/chat/history/visitor_records','GET','[]',0,0,0,1,0,1,'','165/1320',2,'',0,'',0),
(1371,1320,'','导出访客对话','admin','','','api/admin/chat/history/visitor_export','GET','[]',0,0,0,1,0,1,'','165/1320',2,'',0,'',0);

-- 平台客服入口，对应增量 V20260910_02
INSERT INTO `eb_system_menus` (`id`,`pid`,`icon`,`menu_name`,`module`,`controller`,`action`,`api_url`,`methods`,`params`,`sort`,`is_show`,`is_show_path`,`is_tenant`,`is_platform`,`access`,`menu_path`,`path`,`auth_type`,`header`,`is_header`,`unique_auth`,`is_del`) VALUES
(1380,1300,'','平台客服入口','admin','','','api/admin/platform/support','GET','[]',0,0,0,1,0,1,'','1300',2,'',0,'',0);
