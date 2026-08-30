


-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `eb_chat_auto_reply` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `keyword` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '关键词',
  `content` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT '内容',
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
(1, 'site_name', 'text', 'input', 1, '', 0, 'required:true', 100, 0, '"CRMEB"', '网站名称', '网站名称很多地方会显示的，建议认真填写', 10, 1),
(2, 'site_url', 'text', 'input', 1, '', 0, 'required:true,url:true', 100, 0, '""', '网站地址', '安装自动配置，不要轻易修改，更换会影响网站访问、接口请求、本地文件储存、支付回调、微信授权、支付、小程序图片访问、部分二维码、官方授权等', 5, 1),
(3, 'site_logo', 'upload', '', 1, '', 1, '', 0, 0, '"https:\\/\\/chat.crmeb.net\\/uploads\\/attach\\/2021\\/09/\\20210906\\/6972cb96c04079eb1952ef43a04c6fbf.png"', '后台大LOGO', '菜单展开左上角logo,建议尺寸[170*50]', 3, 1),
(5, 'seo_title', 'text', 'input', 1, '', 0, 'required:true', 100, 0, '"CRMEB"', 'SEO标题', 'SEO标题', 0, 0),
(108, 'upload_type', 'radio', '', 31, '1=>本地存储\n2=>七牛云存储\n3=>阿里云OSS\n4=>腾讯COS', 1, '', 0, 0, '1', '上传类型', '文件储存配置，注意：一旦配置就不要轻易修改，会导致文件不能使用', 40, 1),
(109, 'uploadUrl', 'text', 'input', 32, '', 0, 'url:true', 100, 0, '""', '空间域名 Domain', '空间域名 Domain', 0, 1),
(110, 'accessKey', 'text', 'input', 32, '', 0, '', 100, 0, '""', 'AccessKey ID', 'AccessKey ID', 0, 1),
(111, 'secretKey', 'text', 'input', 32, '', 0, '', 100, 0, '""', 'AccessKey Secret', 'AccessKey Secret', 0, 1),
(112, 'storage_name', 'text', 'input', 32, '', 0, '', 100, 0, '""', 'Bucket', '存储空间名称', 0, 1),
(118, 'storage_region', 'text', 'input', 32, '', 0, '', 100, 0, '""', 'Endpoint', '所属地域', 0, 1),
(142, 'tengxun_map_key', 'text', 'input', 68, '', 0, '', 100, 0, '', '腾讯地图KEY', '腾讯地图KEY，申请地址：https://lbs.qq.com', 0, 1),
(144, 'cache_config', 'text', 'input', 1, '', 0, '', 100, 0, '"86400"', '网站缓存时间', '配置全局缓存时间（秒），默认留空为永久缓存', 0, 1),
(168, 'site_logo_square', 'upload', '', 1, '', 1, '', 0, 0, '"https:\\/\\/chat.crmeb.net/\\uploads\\/attach\\/2021\\/09/\\/20210906\\/32645ce20cd8b945598d06bd2a31dd2a.png"', '后台小LOGO', '后台菜单缩进小LOGO，尺寸180*180', 1, 1),
(171, 'login_logo', 'upload', '', 1, '', 1, '', 0, 0, '', '后台登录页LOGO', '后台登录页LOGO，建议尺寸270x75', 4, 1),
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
  `header` varchar(10) NOT NULL DEFAULT '' COMMENT '顶部菜单标示',
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
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

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
ALTER TABLE `eb_application` ADD `auth_mode` tinyint(1) NOT NULL DEFAULT '1' COMMENT '接入模式0=兼容(信任uid),1=签名' AFTER `token_md5`;
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
  `pc_icon` varchar(255) NOT NULL DEFAULT '' COMMENT 'PC悬浮按钮图标',
  `mobile_icon` varchar(255) NOT NULL DEFAULT '' COMMENT '移动端悬浮按钮图标',
  `banners` text COMMENT '轮播广告json[{image,link,sort}]',
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
