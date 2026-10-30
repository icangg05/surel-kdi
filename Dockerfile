# syntax=docker/dockerfile:1

# ---------- base: FrankenPHP (PHP 8.5.12, dipin) + ekstensi yang dipakai Laravel ----------
FROM dunglas/frankenphp:1.13.4-php8.5.12 AS base

RUN install-php-extensions pdo_mysql gd intl zip opcache pcntl bcmath exif

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/Caddyfile /etc/frankenphp/Caddyfile

WORKDIR /app

# ---------- dev: kode di-bind-mount, PHP mode klasik (hot reload gratis) ----------
FROM base AS dev

# Node dipasang di image yang sama dengan PHP: plugin Vite Wayfinder memanggil
# `php artisan wayfinder:generate` saat build, jadi Vite tidak bisa jalan di
# kontainer Node polos. git/unzip untuk composer.
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    npm_config_cache=/tmp/npm

# Kontainer dev berjalan sebagai user host (lihat compose.yml) supaya berkas
# yang ditulis aplikasi lahir dengan kepemilikan yang sama di kedua sisi bind
# mount. Caddy tetap butuh dua direktori ini bisa ditulis.
RUN chown -R 1000:1000 /data /config

EXPOSE 80

# ---------- build: vendor + aset produksi ----------
FROM base AS build

COPY docker/php.prod.ini /usr/local/etc/php/conf.d/app.ini
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && npm run build \
    && rm -rf node_modules

# ---------- production ----------
FROM base AS production

COPY docker/php.prod.ini /usr/local/etc/php/conf.d/app.ini
COPY --from=build /app /app

RUN chown -R www-data:www-data storage bootstrap/cache

ENV APP_ENV=production
EXPOSE 80
