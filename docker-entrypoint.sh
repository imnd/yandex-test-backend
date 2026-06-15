#!/bin/sh
set -e

# Cache configuration and routes for production performance
echo "Caching Laravel config, routes, and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations automatically on deployment
echo "Running database migrations..."
php artisan migrate --force

# Start the application server
echo "Starting Laravel API server on port 8000..."
exec php artisan serve --host=0.0.0.0 --port=8000
