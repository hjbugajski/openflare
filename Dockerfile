# syntax=docker/dockerfile:1

FROM composer:2 AS wayfinder

WORKDIR /app

COPY composer.json composer.lock ./
# sharing=locked: this stage and the composer stage build concurrently and
# race on composer's cache dir if both mount it unserialized
RUN --mount=type=cache,target=/tmp/cache,sharing=locked \
    composer config cache-files-dir /tmp/cache && \
    composer install --no-dev --no-scripts --prefer-dist --ignore-platform-reqs

COPY artisan ./
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY routes ./routes
COPY database ./database

# Fake key - only needed for Laravel to boot, not used for encryption;
# APP_ENV=local so production-only security checks don't abort the build;
# APP_URL='' so forceRootUrl is empty and wayfinder emits relative URLs
# instead of baking the build-time host into the JS bundle.
RUN mkdir -p bootstrap/cache \
    storage/framework/views \
    storage/framework/cache \
    storage/framework/sessions \
    storage/logs \
    resources/js/actions \
    resources/js/routes \
    resources/js/wayfinder \
    && APP_ENV=local \
    APP_URL='' \
    APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    php artisan wayfinder:generate --with-form

FROM node:26-alpine AS frontend

WORKDIR /app

# corepack is no longer bundled with Node >= 25
RUN npm install -g pnpm@11.22.0

# pnpm-workspace.yaml carries the allowBuilds entry for the Central Icons
# license check and its minimumReleaseAge exemption — without it the install
# fails policy verification.
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./

# The Central Icons license key is exposed only for this instruction via a
# BuildKit secret so it never lands in a layer, the build cache, or
# `docker history`:
#   docker build --secret id=central_license_key,env=CENTRAL_LICENSE_KEY .
RUN --mount=type=cache,target=/pnpm/store \
    --mount=type=secret,id=central_license_key,env=CENTRAL_LICENSE_KEY \
    pnpm config set store-dir /pnpm/store && \
    pnpm install --frozen-lockfile

COPY resources ./resources
COPY vite.config.ts tsconfig.json ./

COPY --from=wayfinder /app/resources/js/actions ./resources/js/actions
COPY --from=wayfinder /app/resources/js/routes ./resources/js/routes
COPY --from=wayfinder /app/resources/js/wayfinder ./resources/js/wayfinder

RUN pnpm run build

FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock ./

# sharing=locked: see wayfinder stage
RUN --mount=type=cache,target=/tmp/cache,sharing=locked \
    composer config cache-files-dir /tmp/cache && \
    composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

COPY . .

# bootstrap/cache is excluded by .dockerignore, so recreate it;
# APP_ENV=local so package:discover's artisan boot skips production-only checks
RUN mkdir -p bootstrap/cache && \
    APP_ENV=local composer dump-autoload --optimize --no-dev

FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    nginx \
    supervisor \
    sqlite \
    sqlite-dev \
    curl \
    libzip-dev \
    oniguruma-dev \
    libpq-dev \
    libsodium \
    && docker-php-ext-install \
    pdo_pgsql \
    pdo_sqlite \
    mbstring \
    zip \
    pcntl \
    opcache \
    && rm -rf /var/cache/apk/*

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini /usr/local/etc/php/conf.d/99-custom.ini

COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-openflare.conf
RUN sed -i 's/^listen = .*/listen = 127.0.0.1:9000/' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/^;clear_env = .*/clear_env = no/' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

COPY --from=composer /app/vendor ./vendor
COPY . .

COPY --from=frontend /app/public/build ./public/build

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh

RUN mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    database \
    && chown -R www-data:www-data \
    storage \
    bootstrap/cache \
    database \
    && chmod -R 775 \
    storage \
    bootstrap/cache \
    database \
    && chmod +x /entrypoint.sh

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://127.0.0.1:8080/up || exit 1

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
