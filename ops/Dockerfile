FROM php:8.4.27-cli-bookworm@sha256:54b467ede1db96c66675e7b78457dd43729d0e7be31978d4a1e5c069ceabcf76
COPY --from=composer:2.8.12@sha256:5248900ab8b5f7f880c2d62180e40960cd87f60149ec9a1abfd62ac72a02577c /usr/bin/composer /usr/local/bin/composer
RUN --mount=type=secret,id=proxy_ca,required=true \
    apt-get -o Acquire::https::CaInfo=/run/secrets/proxy_ca update \
 && apt-get -o Acquire::https::CaInfo=/run/secrets/proxy_ca install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libwebp-dev libonig-dev libxml2-dev unzip git \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j2 pdo_mysql gd mbstring dom \
 && rm -rf /var/lib/apt/lists/*
WORKDIR /app
