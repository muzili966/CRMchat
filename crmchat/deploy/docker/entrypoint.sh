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

[APP]
DEFAULT_TIMEZONE = Asia/Shanghai

[DATABASE]
TYPE = mysql
HOSTNAME = ${DB_HOST:-mysql}
DATABASE = ${DB_NAME:-crmchat}
USERNAME = ${DB_USER:-crmchat}
PASSWORD = ${DB_PASSWORD:-}
HOSTPORT = ${DB_PORT:-3306}
PREFIX = ${DB_PREFIX:-eb_}
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

exec php think swoole
