FROM gitlab.sol1.net:5050/sol1/base-images/laravel-php:8.3
WORKDIR /app

COPY . .
COPY docker/app/php-ini-overrides.ini /usr/local/etc/php/conf.d/99-docker.ini

RUN composer install --no-dev --optimize-autoloader

RUN npm ci && npm run build

# For artisan tinker
RUN mkdir -p /.config/psysh && chown -R 9001 /.config/psysh

# Clean up
RUN rm -rf /usr/local/include/node \
    /usr/local/lib/node_modules \
    /usr/local/bin/node \
    /usr/local/bin/npm

EXPOSE 9000
USER 9001