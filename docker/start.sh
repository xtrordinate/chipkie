#!/bin/sh

# Railway injects $PORT - nginx must listen on it
PORT=${PORT:-80}

echo "Starting on port $PORT"

# Update nginx to listen on the correct port
sed -i "s/listen 80 default_server/listen ${PORT} default_server/" /etc/nginx/http.d/default.conf

# Ensure nginx log dirs exist
mkdir -p /var/log/nginx /var/run

# Run migrations
php artisan migrate --force

# Cache config and link storage (non-fatal)
php artisan config:cache || true
php artisan storage:link || true

# Start php-fpm as daemon
php-fpm -D

echo "php-fpm started, launching nginx on port $PORT..."

# Start nginx in foreground (this keeps the container alive)
exec nginx -g "daemon off;"
