# syntax=docker/dockerfile:1

# =============================================================================
# Stage 1: Composer dependencies
# =============================================================================
FROM composer:2 AS composer-builder

WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies (no dev)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

# Copy application code
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-dev

# =============================================================================
# Stage 2: Frontend build (PHP + Node for Wayfinder)
# =============================================================================
FROM php:8.4-cli-alpine AS frontend-builder

# Install Node.js and pnpm
RUN apk add --no-cache nodejs npm \
    && npm install -g pnpm@10.27.0

WORKDIR /app

# Copy PHP application (needed for Wayfinder route generation)
COPY --from=composer-builder /app /app

# Create required directories for Laravel bootstrap
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache

# Install Node dependencies
RUN pnpm install --frozen-lockfile

# Generate Wayfinder routes before build (dummy key for route generation only)
RUN APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    php artisan wayfinder:generate --with-form

# Build frontend assets (including SSR)
RUN pnpm run build:ssr

# =============================================================================
# Stage 3: Production image
# =============================================================================
FROM php:8.4-cli-alpine AS production

# Install system dependencies
RUN apk add --no-cache \
    supervisor \
    sqlite \
    curl \
    nodejs \
    && rm -rf /var/cache/apk/*

# Install PHP extensions
RUN docker-php-ext-install pcntl pdo_mysql

# Install SQLite extension
RUN apk add --no-cache sqlite-dev \
    && docker-php-ext-install pdo_sqlite

# Install Redis extension (optional, for cache/session)
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Configure PHP for production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/99-openflare.ini"

WORKDIR /app

# Copy application from composer builder
COPY --from=composer-builder /app /app

# Copy built assets from frontend builder
COPY --from=frontend-builder /app/public/build /app/public/build
COPY --from=frontend-builder /app/bootstrap/ssr /app/bootstrap/ssr

# Copy docker configuration files
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh

# Create necessary directories
RUN mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    data

# Set environment variables
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=info \
    PORT=8000 \
    DB_DATABASE=/app/data/database.sqlite

# Declare volumes for persistent data
VOLUME ["/app/data", "/app/storage"]

# Expose ports (web server and reverb)
EXPOSE 8000 8080

# Health check (uses PORT env var, defaults to 8000)
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://localhost:${PORT:-8000}/up || exit 1

# Run as root initially, entrypoint will drop to openflare user
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
