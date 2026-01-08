#!/bin/bash
# Start script for Railway Worker service
# Processes background jobs from the queue

set -e

echo "Starting queue worker..."
php artisan queue:work --queue=monitors,notifications,default --sleep=3 --tries=3 --max-time=3600
