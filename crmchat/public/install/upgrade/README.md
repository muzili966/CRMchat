# 数据库脚本规则

三种脚本各管一件事，职责不重叠：

| 脚本 | 位置 | 用途 | 何时执行 |
| --- | --- | --- | --- |
| 全量 | `public/install/crmeb.sql` | 新环境建库 | 安装向导 / `php think install` |
| 增量 | `public/install/upgrade/V*.sql` | 已有环境升级 | 容器启动时 `php think upgrade` |
| 历史 | `update.sql` | CRMEB 上游遗留 + 本次改版前的手工记录 | 已冻结，不再追加 |

## 命名

```
V<8位日期>_<2位序号>__<英文描述>.sql
V20260902_01__platform_crm.sql
```

同一天多次发布靠序号区分。版本号即执行顺序，按字符串升序。

## 铁律

**已合入 dev 的脚本文件不可再修改。** 账本记了文件 md5，改动会让各环境的实际结构与版本号对不上。要改就发新版本。

**每个脚本必须可重复执行。** MySQL 的 DDL 不受事务保护，中途失败无法回滚，只能靠重跑收敛：

- 建表用 `CREATE TABLE IF NOT EXISTS`
- 插数据用 `INSERT ... ON DUPLICATE KEY UPDATE`（菜单这类固定 id 的尤其要）
- 加列没有 `IF NOT EXISTS`，用 `information_schema` 判断后 `PREPARE` 执行，写法见 `V20260902_01`
- 数据回填带条件，例如 `WHERE tenant_id = 0`，不要无条件 `UPDATE`

**表名一律写 `eb_` 前缀。** 执行器按 `DB_PREFIX` 替换反引号与单引号两种包裹形式。

**全量脚本要同步。** 新版本落地时把结构合进 `crmeb.sql`（列直接写进 `CREATE TABLE`，不要留 `ALTER`），并在 `eb_system_upgrade` 的预置 `INSERT` 里补上版本号——否则新装的库会把已包含的增量再执行一遍。

## 账本

`eb_system_upgrade` 一行一个已执行版本，记 md5 与耗时。执行器只跑不在账本里的版本。

## 命令

```bash
php think upgrade              # 执行待执行版本
php think upgrade --dry-run    # 只列出，不执行
php think upgrade --baseline   # 只登记不执行
```

`--baseline` 用于存量库：结构已由手工 SQL 改到位，直接登记版本号避免重跑。给一套已在跑的老环境接入本机制时用这个。
