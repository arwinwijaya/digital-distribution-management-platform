FROM php:8.3-fpm

# Install system dependencies + PHP extensions.
# phpredis is REQUIRED: config/database.php defaults REDIS_CLIENT=phpredis, but
# the base image ships neither phpredis nor predis — without it every Redis
# cache/session/queue call throws "Class Redis not found".
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
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql mbstring exif pcntl bcmath gd intl zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better layer caching
COPY apps/api/composer.json apps/api/composer.lock ./

# Install dependencies (no scripts yet — the app code isn't copied)
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-interaction

# Copy application code (.dockerignore keeps vendor/ out of the context)
COPY apps/api .

# Generate autoload
RUN composer dump-autoload --optimize

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Expose port
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]
