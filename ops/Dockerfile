FROM php:8.4.28-cli-bookworm@sha256:93a0fed307238c5645ccff9d4b87625bd5b96fdfa987e243dab531fd6140bac8
COPY --from=composer:2.11.3@sha256:4e5f671c4a5784b422c20fcb993a38da96529438889dbce40be27c452d18dd6d /usr/bin/composer /usr/local/bin/composer
RUN --mount=type=secret,id=proxy_ca,required=true \
    apt-get -o Acquire::https::CaInfo=/run/secrets/proxy_ca update \
 && apt-get -o Acquire::https::CaInfo=/run/secrets/proxy_ca install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libwebp-dev libonig-dev libxml2-dev unzip git \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j2 pdo_mysql gd mbstring dom \
 && rm -rf /var/lib/apt/lists/*
WORKDIR /app
