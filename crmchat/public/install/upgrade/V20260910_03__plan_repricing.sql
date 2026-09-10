-- 版本：V20260910_03
-- 内容：套餐调价，标准版 1000→1500、旗舰版 2000→3000
--
-- 标准版到旗舰版原本是 2 倍价差，旗舰版上调后若标准版不动就变成 3 倍跳，
-- 中间断层太大，客户从标准版往上走会犹豫。两档同幅上调 50%，
-- 每坐席成本 100 / 75 / 30 元，随档位递减，销售也好讲。
--
-- 只改在售价格，不动历史订单：订单里的 amount 与 plan_snapshot 是成交时的
-- 事实，改了就等于篡改对账记录。

UPDATE `eb_tenant_plan` SET `price` = 1500.00, `update_time` = UNIX_TIMESTAMP()
WHERE `name` = '标准版' AND `price` = 1000.00 AND `is_delete` = 0;

UPDATE `eb_tenant_plan` SET `price` = 3000.00, `update_time` = UNIX_TIMESTAMP()
WHERE `name` = '旗舰版' AND `price` = 2000.00 AND `is_delete` = 0;
