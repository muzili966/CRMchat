#!/bin/sh
# 容器入口：无 .env 时按环境变量生成（格式对齐 .example.env），随后前台启动 Swoole
set -e

cd /var/www/crmchat

# 安装状态声明在环境配置中：容器重建后自动恢复install.lock，避免/install重新暴露
# 首次部署保持默认false走安装向导，安装完成后在 .env.<env> 中置 APP_INSTALLED=true
if [ "${APP_INSTALLED:-false}" = "true" ] && [ ! -f public/install/install.lock ]; then
    touch public/install/install.lock
fi

if [ ! -f .env ]; then
    cat > .env <<EOF
APP_DEBUG = ${APP_DEBUG:-false}
APP_KEY = ${APP_KEY:-}
SWOOLE_HOST = ${SWOOLE_HOST:-0.0.0.0}
SWOOLE_PORT = ${SWOOLE_PORT:-20108}
SWOOLE_DAEMONIZE = false
# AI客服：密钥不入库不进后台，仅从环境变量注入；留空即关闭AI能力
AI_BASE_URL = ${AI_BASE_URL:-https://api.deepseek.com}
AI_API_KEY = ${AI_API_KEY:-}

[APP]
DEFAULT_TIMEZONE = Asia/Shanghai

[DATABASE]
TYPE = mysql
HOSTNAME = ${DB_HOST:-host.docker.internal}
DATABASE = ${DB_NAME:-crmchat}
USERNAME = ${DB_USER:-crmchat}
PASSWORD = ${DB_PASSWORD:-}
HOSTPORT = ${DB_PORT:-3306}
PREFIX = ${DB_PREFIX:-eb_}
SQL_MODE = ${DB_SQL_MODE:-NO_ENGINE_SUBSTITUTION}
CHARSET = utf8
DEBUG = false

[LANG]
default_lang = zh-cn

[REDIS]
REDIS_HOSTNAME = ${REDIS_HOST:-redis}
PORT = ${REDIS_PORT:-6379}
REDIS_PASSWORD = ${REDIS_PASSWORD:-}
SELECT = ${REDIS_SELECT:-0}
EOF
fi

# 增量升级：已装好的库在启动时把未执行的版本脚本落库，未安装时跳过（表都还没有）。
# 升级失败即中止启动——带着旧表结构跑新代码只会产出更难查的错
if [ -f public/install/install.lock ]; then
    php think upgrade
fi

# runtime是持久卷，跨部署不会重建；而权限缓存的key只由角色id决定，
# 菜单与权限的变更走升级脚本在启动时落库，没有任何环节会让该缓存失效，
# 曾导致租户拿不到新增的附件上传权限。故每次启动清一次缓存（只清cache，保留日志）
rm -rf runtime/cache

exec php think swoole
