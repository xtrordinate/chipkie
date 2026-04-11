#!/bin/sh

echo "=== Artisan setup ==="
php artisan config:cache || echo "config:cache failed (continuing)"
php artisan storage:link || echo "storage:link failed (continuing)"

echo "=== Starting php-fpm ==="
php-fpm -D
echo "php-fpm exit code: $?"

echo "=== Starting nginx ==="
exec nginx -g "daemon off;"
