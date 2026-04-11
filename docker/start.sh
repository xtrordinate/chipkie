#!/bin/sh

echo "=== Container starting ==="

echo "=== Running migrations ==="
php artisan migrate --force
echo "=== Migrations done ==="

echo "=== Caching config ==="
php artisan config:cache || echo "config:cache failed"

echo "=== Storage link ==="
php artisan storage:link || echo "storage:link failed"

echo "=== Starting php-fpm ==="
php-fpm -D
echo "=== php-fpm started (exit $?) ==="

echo "=== Starting nginx ==="
exec nginx -g "daemon off;"
