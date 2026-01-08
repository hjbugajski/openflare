#!/bin/bash
# Start script for Railway Cron service
# Runs Laravel scheduler continuously

set -e

echo "Starting scheduler..."
php artisan schedule:work --verbose --no-interaction
