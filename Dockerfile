# ==============================================================================
# Stage 1: Generate Wayfinder routes
# ==============================================================================
FROM composer:2 AS wayfinder

WORKDIR /app

COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/cache \
    composer config cache-files-dir /tmp/cache && \
    composer install --no-dev --no-scripts --prefer-dist --ignore-platform-reqs

COPY artisan ./
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY routes ./routes
COPY database ./database

# Create required directories and generate routes
# (fake key - only needed for Laravel to boot, not used for encryption)
RUN mkdir -p bootstrap/cache \
    storage/framework/views \
    storage/framework/cache \
    storage/framework/sessions \
    storage/logs \
    resources/js/actions \
    resources/js/routes \
    resources/js/wayfinder \
    && APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    php artisan wayfinder:generate --with-form

# ==============================================================================
# Stage 2: Build frontend assets
# ==============================================================================
FROM node:22-alpine AS frontend

WORKDIR /app

# Install pnpm
RUN corepack enable && corepack prepare pnpm@10.27.0 --activate

# Copy package files
COPY package.json pnpm-lock.yaml ./

# Install dependencies with persistent cache
RUN --mount=type=cache,target=/pnpm/store \
    pnpm config set store-dir /pnpm/store && \
    pnpm install --frozen-lockfile

# Copy source files
COPY resources ./resources
COPY vite.config.ts tsconfig.json ./

# Copy generated wayfinder routes
COPY --from=wayfinder /app/resources/js/actions ./resources/js/actions
COPY --from=wayfinder /app/resources/js/routes ./resources/js/routes
COPY --from=wayfinder /app/resources/js/wayfinder ./resources/js/wayfinder

# Build assets
RUN pnpm run build

# ==============================================================================
# Stage 3: Install PHP dependencies
# ==============================================================================
FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock ./

# Install dependencies without dev packages
RUN --mount=type=cache,target=/tmp/cache \
    composer config cache-files-dir /tmp/cache && \
    composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

# Copy application for autoload generation
COPY . .

# Create cache directory (excluded by .dockerignore) and generate autoloader
RUN mkdir -p bootstrap/cache && \
    composer dump-autoload --optimize --no-dev

# ==============================================================================
# Stage 4: Production image
# ==============================================================================
FROM php:8.4-fpm-alpine3.21

# Install system dependencies
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

# Configure PHP for production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Configure opcache
COPY docker/php.ini /usr/local/etc/php/conf.d/99-custom.ini

# Configure PHP-FPM
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-openflare.conf
RUN sed -i 's/^listen = .*/listen = 127.0.0.1:9000/' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/^;clear_env = .*/clear_env = no/' /usr/local/etc/php-fpm.d/www.conf

# Set working directory
WORKDIR /var/www/html

# Copy application from composer stage
COPY --from=composer /app/vendor ./vendor
COPY . .

# Copy built frontend assets
COPY --from=frontend /app/public/build ./public/build

# Copy docker configuration files
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh

# Create required directories and set permissions
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

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://127.0.0.1:8080/up || exit 1

# Expose the port (Railway provides $PORT)
EXPOSE 8080

# Start via entrypoint
ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
