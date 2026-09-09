# CRM-Chat 运行镜像：PHP 7.4 CLI + Swoole 4.8 常驻服务
# 项目 vendor 目录已随仓库提交，构建期不执行 composer install
FROM php:7.4-cli

# 换归档源：PHP 7.4 官方镜像停在 Debian 11(bullseye)，而 bullseye 已过维保，
# deb.debian.org 及各国内镜像正在下线它的 pool——索引还指向 deb11u8，文件却已 404，
# 构建必挂（exit 100）。archive.debian.org 保留了 base 与 updates，实测可装齐全部依赖。
#
# 两点取舍：
# - 归档源没有 bullseye-security，装到的是 EOL 前的基线版本（如 openssl 1.1.1n）。
#   bullseye 本就不再发安全更新，但这确实比原先少了已发布的补丁
# - Check-Valid-Until=false：归档源的 Release 是过期的，不关检查 apt 会拒绝。
#   GPG 签名校验仍然生效
#
# 治本要换掉 PHP 7.4（自身也已 EOL），届时基础镜像可回到在维护的 Debian
#
# gd 供 think-image 使用；pcntl/sockets 供 Swoole 进程模型使用；zip 供订单导出xlsx
RUN echo 'deb http://archive.debian.org/debian bullseye main' > /etc/apt/sources.list \
    && echo 'deb http://archive.debian.org/debian bullseye-updates main' >> /etc/apt/sources.list \
    && apt-get -o Acquire::Check-Valid-Until=false update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libssl-dev libcurl4-openssl-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd bcmath pdo_mysql mysqli pcntl sockets zip \
    && pecl install -D 'enable-sockets="yes" enable-openssl="yes" enable-http2="yes" enable-mysqlnd="yes"' swoole-4.8.13 \
    && pecl install redis-5.3.7 \
    && docker-php-ext-enable swoole redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/crmchat

COPY . .

RUN chmod +x deploy/docker/entrypoint.sh \
    && mkdir -p runtime public/uploads

EXPOSE 20108

ENTRYPOINT ["./deploy/docker/entrypoint.sh"]
