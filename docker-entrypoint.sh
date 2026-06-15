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

echo "Seeding database..."
php artisan db:seed --force

# Start the queue worker in the background to process parsing jobs
echo "Starting Laravel queue worker..."
php artisan queue:work --verbose --tries=3 --timeout=180 &

# Start the application server
echo "Starting Laravel API server on port ${PORT:-8000}..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
