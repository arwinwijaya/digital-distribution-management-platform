# syntax=docker/dockerfile:1
#
# Multi-stage build for the Laravel API.
#   base   → PHP + extensions (also the dev target: `php artisan serve`)
#   vendor → composer dependencies (no dev, no scripts)
#   app    → production image: code + vendor + opcache + entrypoint (php-fpm)
#
FROM php:8.3-fpm AS base

# phpredis is REQUIRED: config/database.php defaults REDIS_CLIENT=phpredis, but
# the base image ships neither phpredis nor predis.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        curl \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libpq-dev \
        libicu-dev \
        libonig-dev \
        libzip-dev \
        zip \
        unzip \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql mbstring exif pcntl bcmath gd intl zip opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ── vendor ──────────────────────────────────────────────────────────────────
FROM base AS vendor
COPY apps/api/composer.json apps/api/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# ── app (production) ────────────────────────────────────────────────────────
FROM base AS app
COPY --from=vendor /var/www/html/vendor ./vendor
COPY apps/api .

COPY docker/api/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/api/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-clear-env.conf
COPY docker/api/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
