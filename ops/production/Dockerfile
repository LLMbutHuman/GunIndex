FROM php:8.4.28-fpm-bookworm@sha256:54ce0ded7582385bf6b522aff920d2eff5ec60da9476d8c52ce6aa69c9c5c7cb
RUN --mount=type=secret,id=proxy_ca,required=true \
    apt-get -o Acquire::https::CaInfo=/run/secrets/proxy_ca update \
 && DEBIAN_FRONTEND=noninteractive apt-get -o Acquire::https::CaInfo=/run/secrets/proxy_ca install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libwebp-dev libonig-dev libxml2-dev msmtp-mta ca-certificates \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j2 pdo_mysql gd mbstring dom opcache \
 && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY . /app
RUN --network=none \
    sha256sum -c ops/production/composer-vendor.sha256 \
 && tar --extract --gzip --file=ops/production/composer-vendor.tar.gz --no-same-owner --no-same-permissions \
 && php ops/production/verify-composer-vendor.php \
 && test -f assets/vendor/openpgp/openpgp.min.mjs \
 && find /app -type d -exec chmod 0755 {} + \
 && find /app -type f -exec chmod 0644 {} +
COPY --chmod=0644 ops/production/php.ini /usr/local/etc/php/conf.d/90-production.ini
COPY --chmod=0644 ops/production/fpm.conf /usr/local/etc/php-fpm.d/zz-production.conf
USER www-data
CMD ["php-fpm","-F"]
