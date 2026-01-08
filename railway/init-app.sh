#!/bin/bash
# Pre-deploy script for Railway App service
# Run migrations and cache configuration

set -e

echo "Running database migrations..."
php artisan migrate --force

echo "Clearing caches..."
php artisan optimize:clear

echo "Caching configuration..."
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache

echo "App initialization complete."
