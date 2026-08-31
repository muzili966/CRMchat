# CRM-Chat 运行镜像：PHP 7.4 CLI + Swoole 4.8 常驻服务
# 项目 vendor 目录已随仓库提交，构建期不执行 composer install
FROM php:7.4-cli

# gd 供 think-image 使用；pcntl/sockets 供 Swoole 进程模型使用；zip 供订单导出xlsx
RUN apt-get update && apt-get install -y --no-install-recommends \
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
