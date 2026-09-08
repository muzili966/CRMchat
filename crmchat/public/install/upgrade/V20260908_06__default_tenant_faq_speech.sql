-- 版本：V20260908_06
-- 内容：默认租户（平台自用客服）的常见问题与公共话术
-- 依赖：V20260908_05（常见问题的 title/is_faq 字段）
--
-- 平台开着一个默认租户(id=1)接待自己的客户，但常见问题与话术一直是空的，
-- 新装环境点开访客窗口没有任何卡片、客服工作台话术面板也是空的。
-- 这里补上一套面向本产品的问答与话术：接待对象是来咨询本客服系统的
-- 潜在客户与已开通租户的管理员，内容围绕套餐配额、接入方式、功能边界。
--
-- 归属：tenant_id=1、appid=202116257358989495（均为全量脚本硬编码的预置值）。
-- 时间：add_time 取默认租户创建时间(1625735898)之后 1~2 小时，与租户、
-- 应用的创建时间保持先后关系，避免出现「配置早于租户」的数据。
--
-- 幂等：三类数据都没有业务唯一键，故用 INSERT...SELECT + NOT EXISTS 判重，
-- 不写死自增 id——固定 id 在已有数据的环境会撞上别人的行。
-- 判重键：分类按名称、话术按标题、常见问题按问题文案。

-- 话术分类

INSERT INTO `eb_category` (`tenant_id`,`pid`,`owner_id`,`name`,`sort`,`type`,`other`,`add_time`)
SELECT 1,0,0,'开场问候',100,1,'',1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='开场问候');

INSERT INTO `eb_category` (`tenant_id`,`pid`,`owner_id`,`name`,`sort`,`type`,`other`,`add_time`)
SELECT 1,0,0,'售前咨询',95,1,'',1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='售前咨询');

INSERT INTO `eb_category` (`tenant_id`,`pid`,`owner_id`,`name`,`sort`,`type`,`other`,`add_time`)
SELECT 1,0,0,'套餐与报价',90,1,'',1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='套餐与报价');

INSERT INTO `eb_category` (`tenant_id`,`pid`,`owner_id`,`name`,`sort`,`type`,`other`,`add_time`)
SELECT 1,0,0,'试用与开通',85,1,'',1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='试用与开通');

INSERT INTO `eb_category` (`tenant_id`,`pid`,`owner_id`,`name`,`sort`,`type`,`other`,`add_time`)
SELECT 1,0,0,'接入对接',80,1,'',1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='接入对接');

INSERT INTO `eb_category` (`tenant_id`,`pid`,`owner_id`,`name`,`sort`,`type`,`other`,`add_time`)
SELECT 1,0,0,'功能使用',75,1,'',1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='功能使用');

INSERT INTO `eb_category` (`tenant_id`,`pid`,`owner_id`,`name`,`sort`,`type`,`other`,`add_time`)
SELECT 1,0,0,'账单发票',70,1,'',1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='账单发票');

INSERT INTO `eb_category` (`tenant_id`,`pid`,`owner_id`,`name`,`sort`,`type`,`other`,`add_time`)
SELECT 1,0,0,'故障排查',65,1,'',1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='故障排查');

INSERT INTO `eb_category` (`tenant_id`,`pid`,`owner_id`,`name`,`sort`,`type`,`other`,`add_time`)
SELECT 1,0,0,'安抚致歉',60,1,'',1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='安抚致歉');

INSERT INTO `eb_category` (`tenant_id`,`pid`,`owner_id`,`name`,`sort`,`type`,`other`,`add_time`)
SELECT 1,0,0,'转接升级',55,1,'',1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='转接升级');

INSERT INTO `eb_category` (`tenant_id`,`pid`,`owner_id`,`name`,`sort`,`type`,`other`,`add_time`)
SELECT 1,0,0,'结束回访',50,1,'',1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='结束回访');


-- 公共话术（kefu_id=0，全体坐席可用）

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='开场问候' LIMIT 1),'标准开场','您好，这里是在线客服，很高兴为您服务，请问有什么可以帮您？',6,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='标准开场');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='开场问候' LIMIT 1),'售前开场','您好，欢迎咨询。想先了解产品功能、套餐价格，还是接入方式？我按您关心的先说。',5,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='售前开场');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='开场问候' LIMIT 1),'已接入稍等','您好，已收到您的留言，我正在为您查看，请稍等片刻。',4,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='已接入稍等');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='开场问候' LIMIT 1),'非工作时间','您好，当前为非工作时间，您的留言我们已经记录，工程师上线后会第一时间回复您。',3,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='非工作时间');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='开场问候' LIMIT 1),'老客户问候','您好，看到您已经是我们的用户了，请问这次是使用上遇到问题，还是想调整套餐？',2,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='老客户问候');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='开场问候' LIMIT 1),'简短应答','在的，您请讲，我这边正在为您跟进。',1,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='简短应答');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='售前咨询' LIMIT 1),'产品一句话介绍','我们是一套多租户在线客服系统：访客从您的网站、H5、小程序或 APP 发起咨询，您的客服在统一工作台接待，支持关键词自动回复、AI 智能客服、话术库、满意度评价与绩效报表。',7,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='产品一句话介绍');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='售前咨询' LIMIT 1),'了解客户场景','为了给您更准确的建议，方便问一下：您大概几个人做客服？日均咨询量多少？主要从网站还是小程序、APP 进来？',6,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='了解客户场景');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='售前咨询' LIMIT 1),'适用场景','电商售后、SaaS 售前、设备运维、门店加盟咨询这类场景用得比较多。您可以说下您的业务，我判断一下是否合适。',5,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='适用场景');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='售前咨询' LIMIT 1),'与自建对比','自建客服要处理长连接、消息可靠投递、多端同步和历史检索，投入不小。我们这边开箱即用，接入通常半小时内完成，后续功能随套餐解锁，不用自己维护。',4,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='与自建对比');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='售前咨询' LIMIT 1),'多应用支持','支持。同一账号下可以建多个应用，各自独立的访客、会话与常见问题，适合一家公司多个站点或多个品牌分开接待。',3,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='多应用支持');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='售前咨询' LIMIT 1),'可否先看演示','可以。注册后用免费版就能把完整流程跑通，也可以直接打开应用自带的会话窗口体验访客侧效果，不用改代码。',2,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='可否先看演示');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='售前咨询' LIMIT 1),'引导留资','方便留个联系方式吗？我把方案和报价整理好发您，也可以约个时间做一次在线演示。',1,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='引导留资');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='套餐与报价' LIMIT 1),'四档概览','共四档：免费版（1应用/2坐席/日500条/记录7天）、体验版（2应用/5坐席/日5000条/记录30天）、标准版（5应用/20坐席/日2万条/记录180天）、旗舰版（应用与消息不限/100坐席/记录永久）。',7,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='四档概览');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='套餐与报价' LIMIT 1),'功能分界','功能上的分界点是：关键词自动回复、文件收发、自定义敏感词、APP推送从体验版起；AI 智能客服、客户端装修、自定义广告位、数据导出从标准版起；独立域名与去除平台标识为旗舰版。',6,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='功能分界');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='套餐与报价' LIMIT 1),'选型建议','给您个判断口径：只是试水用免费版；有固定客服岗、需要自动回复和文件收发选体验版；要 AI 应答、数据导出和品牌化选标准版；要独立域名、去平台标识或坐席较多选旗舰版。',5,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='选型建议');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='套餐与报价' LIMIT 1),'报价引导','各档价格以官网价格页为准。您告诉我坐席数和日咨询量，我帮您核算哪一档更划算，避免买高了浪费。',4,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='报价引导');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='套餐与报价' LIMIT 1),'超量说明','配额是硬上限：坐席数、应用数超出后新增会被拦下，日消息量用完当天不再收发。升级后立即放开，原有配置和数据都保留。',3,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='超量说明');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='套餐与报价' LIMIT 1),'升级即时生效','升级是即时生效的，支付完成后配额和功能马上可用，不需要重新接入或重新配置。',2,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='升级即时生效');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='套餐与报价' LIMIT 1),'续费顺延','续费会在当前到期时间上顺延，剩余天数不会作废，可以放心提前续。',1,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='续费顺延');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='试用与开通' LIMIT 1),'免费版说明','注册即开通免费版，不限时长也不需要付费，包含 1 个应用、2 个坐席、每日 500 条消息、200MB 存储、记录保留 7 天，足够把接入跑通。',6,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='免费版说明');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='试用与开通' LIMIT 1),'开通流程','开通后您会拿到管理员账号，登录后台先建应用拿接入代码，再添加客服坐席，就可以开始接待了。整个过程通常十几分钟。',5,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='开通流程');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='试用与开通' LIMIT 1),'初始密码提醒','账号开通时会给一个初始密码，建议首次登录后立即在个人设置里修改，避免安全风险。',4,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='初始密码提醒');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='试用与开通' LIMIT 1),'试用转正式','试用期间的所有配置、话术、常见问题和历史数据，升级后都完整保留，不需要重做。',3,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='试用转正式');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='试用与开通' LIMIT 1),'协助配置','如果您希望我们帮忙做初始配置（常见问题、话术、自动回复），把您的业务资料发我，我这边整理好导入，您确认即可。',2,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='协助配置');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='试用与开通' LIMIT 1),'催办跟进','您上次说要试一下，请问接入过程还顺利吗？有卡住的地方我随时可以协助。',1,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='催办跟进');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='接入对接' LIMIT 1),'接入三步','三步：后台「应用管理」建应用拿 appid 和接入代码；把代码贴到您的页面；需要识别访客身份的，把您系统的 uid、昵称、头像随参数传过来。',8,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='接入三步');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='接入对接' LIMIT 1),'appid 与密钥','appid 用于标识应用，可以出现在前端；app_secret 是服务端签名用的密钥，务必只存在您的服务器上，不要写进网页或 APP 包。泄露了可以在应用详情重置。',7,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='appid 与密钥');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='接入对接' LIMIT 1),'两种接入模式','标准接入直接信任前端传的 uid，配置简单、自带窗口可直接用；签名接入要求服务端下发 sign（md5(appid+uid+timestamp+app_secret)，5分钟有效），能防冒充，适合涉及订单和账户的场景。',6,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='两种接入模式');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='接入对接' LIMIT 1),'游客模式','不传 uid 时按游客处理，系统会自动生成临时标识，同一浏览器内会话保持连续，适合官网这种未登录也要能咨询的场景。',5,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='游客模式');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='接入对接' LIMIT 1),'多端接入','网站和 H5 直接嵌代码或跳转会话页；小程序和 APP 用 WebView 打开会话页并传访客参数即可，不需要单独的 SDK。',4,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='多端接入');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='接入对接' LIMIT 1),'验签失败排查','验签失败一般是三种原因：timestamp 用了毫秒（应为秒）、拼接顺序不对（appid+uid+timestamp+app_secret）、或服务器时间偏差超过 5 分钟。麻烦把您的拼接串发我，我帮您比对。',3,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='验签失败排查');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='接入对接' LIMIT 1),'窗口打不开','会话窗口打不开，麻烦确认接入代码里的 appid 是否正确、页面是否有跨域或 CSP 限制，并把浏览器控制台的报错截图发我。',2,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='窗口打不开');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='接入对接' LIMIT 1),'对接文档','接入代码和参数说明都在后台「应用管理」里，展开应用即可查看和复制。有需要我也可以直接把对应的代码片段发您。',1,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='对接文档');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='功能使用' LIMIT 1),'添加坐席','后台「客服管理」新增坐席，填账号和昵称即可；权限用角色控制，可按菜单粒度授权。开通时自带的「租户管理员」角色包含全部租户侧权限。',8,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='添加坐席');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='功能使用' LIMIT 1),'自动回复配置','在后台配「关键词 → 答案」，多个关键词用逗号隔开。访客消息里出现任一关键词就会自动回复，命中多条时优先返回匹配更精确、排序更靠前的一条。关键词写短词更容易命中。',7,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='自动回复配置');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='功能使用' LIMIT 1),'常见问题卡片','在「常见问题」维护问答，勾选「展示在卡片」的会在访客新会话时弹出，最多 8 条，按排序值排列。访客点卡片直接给预设答案，不消耗 AI 额度。',6,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='常见问题卡片');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='功能使用' LIMIT 1),'话术使用','公共话术由管理员统一维护、全体坐席可用；坐席也能建自己的分类和话术。接待时从话术面板按分类选取插入，适合固化标准口径。',5,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='话术使用');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='功能使用' LIMIT 1),'AI 智能客服','AI 从标准版起提供，标准版每日 200 次、旗舰版每日 2000 次。访客消息先由 AI 应答，答不上或访客要求时自动转人工；额度用完当天不再调用 AI，人工接待不受影响。',4,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='AI 智能客服');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='功能使用' LIMIT 1),'敏感词','体验版起支持自定义词库，处理方式可选替换星号、拦截不发送或仅告警留痕，访客端、客服端与 AI 回复三条链路都会过滤，命中记录可查。',3,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='敏感词');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='功能使用' LIMIT 1),'满意度评价','接待结束时可邀请访客评价，客服也能主动发起。评价结果计入绩效报表，可按坐席和时间查看。',2,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='满意度评价');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='功能使用' LIMIT 1),'数据导出','数据导出从标准版起提供。在「历史对话」按时间和应用筛选后提交导出任务，完成后到下载中心取文件，大数据量是异步处理的，不用一直等在页面上。',1,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='数据导出');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='账单发票' LIMIT 1),'开票流程','在「我的订阅 - 我的发票」提交开票申请，填写抬头、税号和接收邮箱，选择要开票的订阅订单即可，审核通过后由财务开具并发到您邮箱。',6,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='开票流程');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='账单发票' LIMIT 1),'开票范围','开票金额以实付的订阅订单为准，未支付或已退款的订单不在开票范围内。',5,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='开票范围');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='账单发票' LIMIT 1),'订单查询','所有订阅订单可以在「我的订阅 - 订阅订单」中查看，含下单时间、套餐、金额与支付状态。',4,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='订单查询');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='账单发票' LIMIT 1),'抬头信息有误','发票抬头或税号填错了，请把正确信息发我，我这边协调财务处理，已开具的需要先作废再重开。',3,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='抬头信息有误');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='账单发票' LIMIT 1),'到期提醒','套餐到期前系统会在后台发送通知提醒，建议提前续费，避免访客无法发起新会话。',2,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='到期提醒');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='账单发票' LIMIT 1),'对公转账','需要对公转账的，我把收款信息发您，转账后把回单发我，我们核对到账后为您手工开通对应套餐。',1,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='对公转账');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='故障排查' LIMIT 1),'索取定位信息','为了快速定位，麻烦提供：您的租户名称或管理员账号、出问题的应用 appid、问题现象与出现时间，有报错截图更好。',7,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='索取定位信息');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='故障排查' LIMIT 1),'收不到消息','客服端收不到消息，先确认工作台是否在线、浏览器标签是否被挂起、以及套餐的日消息量是否已用完。我这边同步查一下服务端的会话记录。',6,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='收不到消息');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='故障排查' LIMIT 1),'访客发不出消息','访客发不出消息，常见原因是日消息量已达上限、套餐已到期，或消息命中了敏感词被拦截。我帮您查一下具体是哪一项。',5,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='访客发不出消息');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='故障排查' LIMIT 1),'历史记录看不到','聊天记录按套餐保留：免费版 7 天、体验版 30 天、标准版 180 天、旗舰版永久。超出保留期的记录会被清理且无法找回，如需长期留存建议升级套餐。',4,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='历史记录看不到');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='故障排查' LIMIT 1),'附件上传失败','附件上传失败多为存储额度已满或文件超出单个大小限制。您可以清理历史附件或升级套餐，我也可以帮您查一下当前用量。',3,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='附件上传失败');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='故障排查' LIMIT 1),'菜单看不到','后台看不到某个菜单，通常是当前账号的角色未授权，或该功能所属套餐档位不包含。我帮您确认是权限问题还是套餐问题。',2,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='菜单看不到');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='故障排查' LIMIT 1),'登记工单','这个问题需要工程师进一步排查，我先为您登记工单，处理完成后主动联系您。方便留个手机号吗？',1,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='登记工单');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='安抚致歉' LIMIT 1),'致歉并优先处理','非常抱歉给您带来不便，我这边优先为您处理，请您稍等一下。',6,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='致歉并优先处理');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='安抚致歉' LIMIT 1),'共情安抚','理解您的心情，影响到您正常接待确实很着急，我马上帮您核实并给出处理方案。',5,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='共情安抚');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='安抚致歉' LIMIT 1),'久等致歉','让您久等了，非常抱歉。您的问题我一直在跟进，一有进展我立刻告知您。',4,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='久等致歉');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='安抚致歉' LIMIT 1),'感谢反馈','感谢您的反馈，这个点确实值得优化，我会同步给产品同事排期评估。',3,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='感谢反馈');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='安抚致歉' LIMIT 1),'故障说明','这次的问题是我们这边的疏漏，已经在处理了，恢复后我会第一时间通知您，给您造成的影响我们非常抱歉。',2,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='故障说明');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='安抚致歉' LIMIT 1),'需求暂不支持','这个能力目前还没有提供，我如实告诉您以免耽误您的判断。我会把需求记录下来反馈给产品，如果后续排上会主动通知您。',1,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='需求暂不支持');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='转接升级' LIMIT 1),'转技术处理','您的问题需要技术同事进一步核查，我帮您转接过去，请稍等片刻。',6,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='转技术处理');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='转接升级' LIMIT 1),'转商务','价格和合同这块由商务同事对接更准确，我帮您转过去，您稍等。',5,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='转商务');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='转接升级' LIMIT 1),'排队说明','当前咨询量较大，我这边尽快为您安排专人跟进，感谢您的理解和等待。',4,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='排队说明');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='转接升级' LIMIT 1),'升级优先级','已为您升级至高优先级处理，请保持联系方式畅通，我们会尽快给您答复。',3,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='升级优先级');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='转接升级' LIMIT 1),'跨部门协调','这个情况需要和财务/研发同事核对，我这边提交协调，预计今日内给您明确回复。',2,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='跨部门协调');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='转接升级' LIMIT 1),'约定回访时间','排查需要一些时间，您方便的话我们约个时间，处理完成后我主动联系您同步结果。',1,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='约定回访时间');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='结束回访' LIMIT 1),'确认是否解决','请问您的问题是否已经解决？还有其他可以帮您的吗？',6,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='确认是否解决');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='结束回访' LIMIT 1),'结束语','感谢您的咨询，祝您工作顺利，后续有任何问题随时联系我们。',5,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='结束语');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='结束回访' LIMIT 1),'邀请评价','本次服务已完成，麻烦您对我的服务做个评价，您的反馈是我们改进的动力，非常感谢。',4,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='邀请评价');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='结束回访' LIMIT 1),'留言引导','如果后续还遇到问题，可以直接在这里留言，我们看到后会尽快回复您。',3,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='留言引导');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='结束回访' LIMIT 1),'超时结束','由于长时间未收到您的回复，本次会话先为您结束。如仍需帮助，请随时重新发起咨询。',2,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='超时结束');

INSERT INTO `eb_chat_service_speechcraft` (`tenant_id`,`kefu_id`,`cate_id`,`title`,`message`,`sort`,`add_time`)
SELECT 1,0,(SELECT `id` FROM `eb_category` WHERE `tenant_id`=1 AND `type`=1 AND `owner_id`=0 AND `name`='结束回访' LIMIT 1),'处理结果回访','您好，之前反馈的问题我们已经处理完成，想跟您确认一下现在使用是否正常？',1,1625739498 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_service_speechcraft` WHERE `tenant_id`=1 AND `kefu_id`=0 AND `title`='处理结果回访');


-- 常见问题（user_id=0 全站通用，前 8 条 is_faq=1 上访客端卡片）

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'免费,试用,体验,免费版,先试试','可以免费试用吗？',1,'可以。注册即开通免费版，不限时长、无需付费：包含 1 个接入应用、2 个客服坐席、每日 500 条消息、200MB 附件存储、聊天记录保留 7 天。
先用免费版把接入跑通，业务量上来了再按需升级，历史数据不受影响。',0,'202116257358989495',100,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='可以免费试用吗？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'套餐,版本,区别,怎么选,档位,价格,多少钱,报价','各套餐有什么区别？怎么选？',1,'共四档，主要差异在坐席数、应用数、消息量与功能开关：
· 免费版：1应用/2坐席/日500条/记录7天，基础收发
· 体验版：2应用/5坐席/日5000条/记录30天，增加关键词自动回复、文件收发、自定义敏感词、APP推送
· 标准版：5应用/20坐席/日2万条/记录180天，增加 AI 智能客服、客户端装修、自定义广告位、数据导出
· 旗舰版：应用与消息不限/100坐席/记录永久，增加独立域名、去除平台标识
具体价格以官网价格页为准，也可以告诉我你的坐席数和日咨询量，我帮你算哪档更划算。',0,'202116257358989495',95,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='各套餐有什么区别？怎么选？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'接入,对接,嵌入,集成,怎么用,安装,部署','怎么把客服接入我的网站或APP？',1,'三步：
1. 后台「应用管理」新建应用，拿到 appid 与接入代码；
2. 把接入代码贴到你网站页面（或在 APP/小程序里按文档跳转会话页）；
3. 需要识别访客身份的，把你系统的用户 uid、昵称、头像随接入参数传过来即可。
只想先看效果的话，用应用自带的会话窗口地址直接打开就能对话，不用改任何代码。',0,'202116257358989495',90,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='怎么把客服接入我的网站或APP？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'坐席,客服数,几个客服,账号数,人数,加人','一个套餐能开几个客服坐席？',1,'免费版 2 个、体验版 5 个、标准版 20 个、旗舰版 100 个。
坐席在后台「客服管理」添加，可分配不同权限。超出上限时新增会被拦下，升级套餐后立即生效，无需重新配置。',0,'202116257358989495',85,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='一个套餐能开几个客服坐席？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'AI,智能客服,机器人,自动应答,大模型,GPT','AI 智能客服怎么开通？',1,'AI 智能客服从标准版起提供：标准版每日 200 次 AI 回复，旗舰版每日 2000 次。
开通后在后台配置 AI 坐席与知识来源，访客消息先由 AI 应答，答不上或访客要求时自动转人工。日额度用完当天不再调用 AI，人工接待不受影响。',0,'202116257358989495',80,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='AI 智能客服怎么开通？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'续费,升级,换套餐,加钱,扩容,到期续','怎么续费或升级套餐？',1,'在租户后台「我的订阅」选择目标套餐下单，支付完成后配额与功能立即生效。
升级为即时生效，坐席数、应用数、消息量同步放开；续费则在当前到期时间上顺延，不会浪费剩余天数。',0,'202116257358989495',75,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='怎么续费或升级套餐？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'发票,开票,报销,税号,增值税,专票','怎么开发票？',1,'在「我的订阅 - 我的发票」提交开票申请，填写抬头、税号与接收邮箱，选择要开票的订阅订单即可。
开票金额以实付订单为准，审核通过后由财务开具并发送到你填写的邮箱。',0,'202116257358989495',70,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='怎么开发票？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'技术支持,联系,人工,售后,客服电话,找人','遇到问题怎么联系技术支持？',1,'直接在本窗口留言即可，工作时间内会有工程师接入。
为了更快定位，建议一次性提供：你的租户名称或管理员账号、出问题的应用 appid、问题现象与出现时间，以及报错截图。非工作时间留言同样会记录，上线后优先回复。',0,'202116257358989495',65,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='遇到问题怎么联系技术支持？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'appid,app_secret,密钥,接入代码,token,凭据','接入代码在哪里获取？appid 和 app_secret 是什么？',0,'后台「应用管理」里每个应用都有独立的 appid 与接入代码，展开即可复制。
appid 用于标识应用，可以出现在前端；app_secret 是服务端签名用的密钥，只能保存在你的服务器上，切勿写进网页或 APP 包内。密钥泄露时可在应用详情重置。',0,'202116257358989495',60,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='接入代码在哪里获取？appid 和 app_secret 是什么？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'签名,标准接入,兼容模式,auth_mode,安全接入,验签','标准接入和签名接入有什么区别？',0,'标准接入：直接信任前端传来的访客 uid，配置简单，自带会话窗口与嵌入代码开箱即用，适合内部系统或对身份要求不高的场景。
签名接入：携带 uid 时必须由你的服务端下发 sign（sign=md5(appid+uid+timestamp+app_secret)，5 分钟内有效），可防止他人冒充任意用户，适合涉及订单、账户等敏感信息的场景。',0,'202116257358989495',58,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='标准接入和签名接入有什么区别？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'访客身份,用户信息,uid,昵称,头像,识别用户','访客身份怎么传给客服系统？',0,'接入时把你系统的用户 uid、昵称、头像、手机号一并传入，客服端就能直接看到访客是谁，历史会话也会归到同一个人名下。
不传 uid 时按游客处理，系统会自动生成临时标识，同一浏览器内的会话仍然连续。',0,'202116257358989495',56,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='访客身份怎么传给客服系统？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'小程序,APP,H5,网站,PC,支持端,兼容','支持哪些端？网站、小程序、APP 都能用吗？',0,'都支持。网站与 H5 直接嵌入代码或跳转会话页；小程序与 APP 通过 WebView 打开会话页并传入访客参数即可。
客服端本身也有网页工作台与移动端，客服在手机上同样可以接待。',0,'202116257358989495',54,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='支持哪些端？网站、小程序、APP 都能用吗？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'聊天记录,保留,存多久,导出,历史对话,备份','聊天记录能保存多久？可以导出吗？',0,'保留时长按套餐：免费版 7 天、体验版 30 天、标准版 180 天、旗舰版永久。
数据导出从标准版起提供，在「历史对话」按时间与应用筛选后提交导出任务，完成后到下载中心取文件。升级套餐只影响之后的保留策略，已被清理的记录无法找回，有长期留存需求建议尽早升级。',0,'202116257358989495',52,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='聊天记录能保存多久？可以导出吗？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'添加客服,坐席账号,权限,角色,分配,员工','客服坐席怎么添加和分配权限？',0,'后台「客服管理」新增坐席，填写账号与昵称即可；权限通过角色控制，可按菜单粒度授权，让不同岗位只看到该看的部分。
租户开通时会自动创建「租户管理员」角色，包含全部租户侧权限，其余角色可在此基础上裁剪。',0,'202116257358989495',50,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='客服坐席怎么添加和分配权限？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'自动回复,关键词,机器人回复,自动应答','关键词自动回复怎么配置？',0,'体验版起支持。在后台配置「关键词 → 答案」，多个关键词用逗号隔开；访客消息中出现其中任一关键词即自动回复，命中多条时优先返回匹配更精确、排序更靠前的一条。
关键词写短词更容易命中，例如写「退款」而不是「怎么申请退款呢」。',0,'202116257358989495',48,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='关键词自动回复怎么配置？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'常见问题,FAQ,卡片,快捷问题,问题列表','常见问题卡片怎么配置？',0,'在后台「常见问题」维护问答，勾选「展示在卡片」的条目会在访客新会话时以卡片形式弹出，最多展示 8 条，顺序按排序值。
访客点卡片直接返回预设答案，不消耗 AI 额度，也不受自动回复开关影响；同一条数据同时供访客打字时命中关键词，不用两处维护。',0,'202116257358989495',46,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='常见问题卡片怎么配置？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'话术,快捷短语,常用语,模板,快捷回复','客服话术怎么用？',0,'话术分公共话术与客服私有话术：公共话术由管理员统一维护、全体坐席可用；坐席也可以在工作台建自己的分类和话术。
接待时从话术面板按分类选取即可插入，适合把入驻流程、退款政策这类标准口径固化下来，减少口径不一。',0,'202116257358989495',44,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='客服话术怎么用？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'敏感词,过滤,屏蔽,违禁词,风控','自定义敏感词怎么用？',0,'体验版起支持。可自定义词库并选择处理方式：替换为星号、拦截不发送、或仅告警留痕。
访客端、客服端与 AI 回复三条链路都会经过过滤，命中记录可在后台查询，便于事后追溯。',0,'202116257358989495',42,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='自定义敏感词怎么用？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'装修,样式,自定义,品牌,主题,配色,logo','客户端可以自定义样式和品牌吗？',0,'客户端装修从标准版起提供：可设置主题配色、客服头像、欢迎语、游客头像池与自定义广告位，让会话窗口与你的站点风格一致。
想彻底去掉平台标识需要旗舰版。',0,'202116257358989495',40,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='客户端可以自定义样式和品牌吗？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'独立域名,白标,去标识,自有域名,OEM','可以用自己的独立域名吗？能去掉平台标识吗？',0,'独立域名与去除平台标识均为旗舰版能力。绑定后访客通过你自己的域名打开会话窗口，页面不出现平台品牌信息。
域名需全局唯一并完成解析，绑定后即时生效。',0,'202116257358989495',38,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='可以用自己的独立域名吗？能去掉平台标识吗？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'文件,附件,图片,存储,上传,容量','附件存储上限是多少？支持发文件吗？',0,'文件收发从体验版起支持。存储上限：免费版 200MB、体验版 2GB、标准版 10GB、旗舰版 50GB。
超出上限后新附件将无法上传，可清理历史附件或升级套餐；聊天中的图片同样计入该额度。',0,'202116257358989495',36,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='附件存储上限是多少？支持发文件吗？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'满意度,评价,打分,星级,评分','满意度评价怎么开启？',0,'接待结束时可邀请访客评价，客服也能在会话中主动发起。评价结果计入客服绩效，可在报表中按坐席与时间维度查看。
评价入口与文案可在后台调整，不需要访客额外登录。',0,'202116257358989495',34,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='满意度评价怎么开启？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'绩效,报表,统计,数据,考核,接待量','客服绩效报表能看到什么？',0,'按坐席统计接待会话数、消息量、首响与平均响应时长、满意度评分等，支持按时间范围与应用筛选。
还提供无人应答提醒，长时间没人接待的会话会触发提示，避免访客被晾着。',0,'202116257358989495',32,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='客服绩效报表能看到什么？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'到期,过期,欠费,停用,数据删除,失效','套餐到期了会怎样？数据会被删除吗？',0,'到期后账号会被限制使用，需要续费后恢复，期间访客无法发起新会话。
数据不会因到期立即删除，但聊天记录仍按套餐的保留天数正常清理，因此长期不续费可能导致历史记录过期。到期前系统会在后台发送通知提醒。',0,'202116257358989495',30,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='套餐到期了会怎样？数据会被删除吗？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'隔离,多应用,多站点,数据分开,互不影响','多个应用之间的数据是隔离的吗？',0,'是的。同一租户下每个应用的访客、会话与常见问题都按 appid 区分，互不串扰，适合一家公司多个站点或多个品牌分开接待。
租户与租户之间是更高一层的隔离，任何数据都不会跨租户可见。',0,'202116257358989495',28,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='多个应用之间的数据是隔离的吗？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'安全,隐私,数据保护,合规,泄露','数据安全和隐私怎么保障？',0,'租户之间数据强隔离，接口层按租户上下文校验，越权访问会被直接拒绝；签名接入模式下访客身份需服务端签名，无法被冒充。
敏感词可拦截对话中的违规内容，附件与聊天记录按套餐策略定期清理。如需签署保密协议或了解更多合规细节，可以联系我们。',0,'202116257358989495',26,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='数据安全和隐私怎么保障？');

INSERT INTO `eb_chat_auto_reply` (`tenant_id`,`keyword`,`title`,`is_faq`,`content`,`user_id`,`appid`,`sort`,`add_time`)
SELECT 1,'私有化,本地部署,独立部署,源码,自建','可以私有化部署吗？',0,'标准服务为 SaaS 模式，开箱即用、免运维。确有私有化需求的（如数据不出内网），可以告诉我你的部署环境与规模，我们评估后给出方案与报价。',0,'202116257358989495',24,1625743098 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_chat_auto_reply` WHERE `appid`='202116257358989495' AND `user_id`=0 AND `title`='可以私有化部署吗？');
