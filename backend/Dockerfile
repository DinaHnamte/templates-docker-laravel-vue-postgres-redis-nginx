# =========================
# Base
# =========================
FROM php:8.4-fpm-alpine AS base
# System deps for PHP extensions
RUN apk add --no-cache \
    postgresql-dev \
    icu-dev \
    libzip-dev \
    bash \
    curl \
    git \
    unzip

# PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    intl \
    zip

# Install Composer
RUN curl -sS https://getcomposer.org/installer \
    | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/backend

# =========================
# Dev
# =========================
FROM base AS dev
CMD ["php-fpm"]

# =========================
# Prod
# =========================
FROM base AS prod
COPY . .
RUN composer install --no-dev --optimize-autoloader
CMD ["php-fpm"]
