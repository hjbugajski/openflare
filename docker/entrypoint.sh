#!/bin/sh
set -e

cd /var/www/html

echo "==> Starting OpenFlare deployment..."

# Graceful shutdown handler
shutdown() {
    echo "==> Received shutdown signal, stopping gracefully..."
    if [ -f /var/run/supervisor.sock ]; then
        supervisorctl stop all
    fi
    exit 0
}
trap shutdown SIGTERM SIGINT

# Database setup (SQLite only - Postgres requires no file setup)
DB_CONNECTION="${DB_CONNECTION:-sqlite}"

if [ "$DB_CONNECTION" = "sqlite" ]; then
    DB_PATH="${DB_DATABASE:-database/database.sqlite}"
    DB_DIR="$(dirname "$DB_PATH")"

    # Ensure directory exists and is writable (for WAL mode -wal/-shm files)
    echo "==> Setting up SQLite directory at $DB_DIR..."
    mkdir -p "$DB_DIR"
    chown www-data:www-data "$DB_DIR"
    chmod 775 "$DB_DIR"

    if [ ! -f "$DB_PATH" ]; then
        echo "==> Creating SQLite database at $DB_PATH..."
        touch "$DB_PATH"
    fi
    chown www-data:www-data "$DB_PATH"

    # Enable WAL mode for better concurrent access and crash recovery
    echo "==> Configuring SQLite for production..."
    sqlite3 "$DB_PATH" "PRAGMA journal_mode=WAL; PRAGMA synchronous=NORMAL; PRAGMA busy_timeout=5000;"
else
    echo "==> Using $DB_CONNECTION database..."
fi

# Run migrations with simple file lock (single instance guard)
LOCK_FILE="/tmp/migrate.lock"
echo "==> Running database migrations..."
if [ -f "$LOCK_FILE" ]; then
    echo "==> Migration lock exists, waiting..."
    sleep 5
fi
touch "$LOCK_FILE"
php artisan migrate --force
rm -f "$LOCK_FILE"

# Create storage link if not exists
if [ ! -L public/storage ]; then
    echo "==> Creating storage link..."
    php artisan storage:link || true
fi

# Cache configuration for production (explicit ordering)
echo "==> Optimizing for production..."
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache

# Start the application
echo "==> Starting services..."
exec "$@"
