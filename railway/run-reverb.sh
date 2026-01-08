#!/bin/bash
# Start script for Railway Reverb service
# WebSocket server for real-time updates

set -e

echo "Starting Reverb WebSocket server..."
php artisan reverb:start --host=0.0.0.0 --port=8080
