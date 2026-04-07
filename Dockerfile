# ── Stage 1: Build frontend assets ───────────────────────────────────────────
FROM node:20-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# ── Stage 2: PHP + nginx app ──────────────────────────────────────────────────
FROM php:8.3-fpm-alpine

# System dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    git \
    curl \
    zip \
    unzip \
    libzip-dev \
    oniguruma-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    linux-headers

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        zip \
        bcmath \
        opcache \
        gd \
        intl \
        pcntl

# Redis extension (needs phpize-deps/autoconf which docker-php-ext-install cleans up)
RUN apk add --no-cache --virtual .pecl-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .pecl-deps

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application files
COPY --chown=www-data:www-data . .

# Copy built frontend assets from Stage 1
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# Ensure storage/cache dirs exist before composer runs package:discover
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/views \
        storage/framework/sessions \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Install PHP dependencies (production)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# nginx + supervisord config
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# PHP-FPM: run as www-data, listen on 127.0.0.1:9000
RUN sed -i 's|^user = .*|user = www-data|' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's|^group = .*|group = www-data|' /usr/local/etc/php-fpm.d/www.conf

# PHP opcache config
RUN echo "opcache.enable=1\nopcache.validate_timestamps=0\nopcache.memory_consumption=128\nopcache.max_accelerated_files=10000" \
    > /usr/local/etc/php/conf.d/opcache.ini

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
