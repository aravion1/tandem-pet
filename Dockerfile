FROM php:8.5-cli

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install --yes --no-install-recommends libonig-dev libpq-dev libsqlite3-dev libzip-dev \
    && docker-php-ext-install mbstring pdo_pgsql pdo_sqlite zip \
    && rm -rf /var/lib/apt/lists/*
