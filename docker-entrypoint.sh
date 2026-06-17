#!/bin/sh
set -e

# Clear any cached config first to ensure env vars from platform are used
echo "Clearing cached config..."
php artisan config:clear
php artisan cache:clear

# Cache configuration and routes for production performance
echo "Caching Laravel config, routes, and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations automatically on deployment
echo "Running database migrations..."
php artisan migrate --force

echo "Seeding database..."
php artisan db:seed --force

echo "Fetching fresh proxies from external sources..."
php artisan proxies:fetch

# Start the queue worker in the background to process parsing jobs
echo "Starting Laravel queue worker..."
nohup php artisan queue:work --verbose --tries=3 --timeout=180 > /dev/null 2>&1 &

# Start the application server
echo "Starting Laravel API server on port ${PORT:-8000}..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
