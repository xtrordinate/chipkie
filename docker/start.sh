#!/bin/sh
set -e

echo "Starting php-fpm..."
php-fpm -D

echo "Starting nginx..."
exec nginx -g "daemon off;"
