FROM composer:2.8.12@sha256:5248900ab8b5f7f880c2d62180e40960cd87f60149ec9a1abfd62ac72a02577c AS composer
FROM php:8.4.27-fpm-bookworm@sha256:1b6bb4f3d32dfbcd1e159d7bd47caa8852507f61666db7731d73f34acec781b6
RUN --mount=type=secret,id=proxy_ca,required=true \
    apt-get -o Acquire::https::CaInfo=/run/secrets/proxy_ca update \
 && DEBIAN_FRONTEND=noninteractive apt-get -o Acquire::https::CaInfo=/run/secrets/proxy_ca install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libwebp-dev libonig-dev libxml2-dev msmtp-mta ca-certificates git \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j2 pdo_mysql gd mbstring dom opcache \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY . /app
RUN --mount=type=secret,id=proxy_ca,required=true \
    test -f composer.lock \
 && GIT_SSL_CAINFO=/run/secrets/proxy_ca COMPOSER_CAFILE=/run/secrets/proxy_ca COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-plugins --optimize-autoloader \
 && find /app -type d -exec chmod 0755 {} + \
 && find /app -type f -exec chmod 0644 {} +
COPY --chmod=0644 ops/production/php.ini /usr/local/etc/php/conf.d/90-production.ini
COPY --chmod=0644 ops/production/fpm.conf /usr/local/etc/php-fpm.d/zz-production.conf
USER www-data
CMD ["php-fpm","-F"]
