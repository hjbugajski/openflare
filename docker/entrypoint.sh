#!/bin/sh
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "${GREEN}OpenFlare Docker Entrypoint${NC}"
echo "================================"

# Ensure storage directories exist
echo "${YELLOW}Checking storage directories...${NC}"
mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# Create SQLite database directory and file if using sqlite
# Default path is /app/data/database.sqlite (separate from /app/database/migrations)
if [ "$DB_CONNECTION" = "sqlite" ] || [ -z "$DB_CONNECTION" ]; then
    DB_PATH="${DB_DATABASE:-data/database.sqlite}"
    DB_DIR=$(dirname "$DB_PATH")
    mkdir -p "$DB_DIR"
    if [ ! -f "$DB_PATH" ]; then
        echo "${YELLOW}Creating SQLite database at ${DB_PATH}...${NC}"
        touch "$DB_PATH"
    fi
fi

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    echo "${RED}WARNING: APP_KEY is not set. Generating temporary key...${NC}"
    echo "${RED}You should set APP_KEY in your environment variables for production!${NC}"
    php artisan key:generate --force
fi

# Clear old caches before migrations (prevents stale cache issues on upgrade)
echo "${YELLOW}Clearing old caches...${NC}"
php artisan config:clear --no-interaction 2>/dev/null || true
php artisan route:clear --no-interaction 2>/dev/null || true
php artisan view:clear --no-interaction 2>/dev/null || true
php artisan event:clear --no-interaction 2>/dev/null || true

# Run migrations with timeout protection
echo "${YELLOW}Running database migrations...${NC}"
php artisan migrate --force --no-interaction

if [ "$DB_CONNECTION" = "sqlite" ] || [ -z "$DB_CONNECTION" ]; then
    DB_PATH="${DB_DATABASE:-data/database.sqlite}"
    if [ -s "$DB_PATH" ] && ! sqlite3 "$DB_PATH" "SELECT 1 FROM migrations LIMIT 1" >/dev/null 2>&1; then
        echo "${RED}ERROR: Database file exists but migrations table is missing or corrupted.${NC}"
        echo "${RED}Manual intervention required to prevent data loss.${NC}"
        exit 1
    fi
fi

# Cache configuration for production
echo "${YELLOW}Caching configuration...${NC}"
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
php artisan event:cache --no-interaction

# Create storage link if it doesn't exist
if [ ! -L "public/storage" ]; then
    echo "${YELLOW}Creating storage link...${NC}"
    php artisan storage:link --no-interaction
fi

echo "${GREEN}Initialization complete!${NC}"
echo "================================"

# Execute the main command
exec "$@"
