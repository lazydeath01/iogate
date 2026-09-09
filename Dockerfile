# ============================================================
# Stage 1: Build frontend
# ============================================================
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./

RUN npm run build


# ============================================================
# Stage 2: Production Laravel
# ============================================================
FROM php:8.5-apache

WORKDIR /var/www/html

# ------------------------------------------------------------
# System dependencies
# ------------------------------------------------------------
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

# ------------------------------------------------------------
# PHP extensions
# ------------------------------------------------------------
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        xml \
        zip

# ------------------------------------------------------------
# Apache
# ------------------------------------------------------------
RUN a2enmod rewrite

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri \
    -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/000-default.conf \
    /etc/apache2/apache2.conf

# ------------------------------------------------------------
# Composer
# ------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy composer files first for Docker layer caching
COPY composer.json composer.lock ./

# Install production PHP dependencies
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# ------------------------------------------------------------
# Application source
# ------------------------------------------------------------
COPY . .

# ------------------------------------------------------------
# Frontend build
# ------------------------------------------------------------
COPY --from=frontend /app/public/build ./public/build

# ------------------------------------------------------------
# Laravel permissions
# ------------------------------------------------------------
RUN chown -R www-data:www-data \
        storage \
        bootstrap/cache \
    && chmod -R 775 \
        storage \
        bootstrap/cache

# ------------------------------------------------------------
# Laravel package discovery
# ------------------------------------------------------------
RUN php artisan package:discover --ansi

# ------------------------------------------------------------
# PHP production configuration
# ------------------------------------------------------------
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=1'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=20000'; \
    } > /usr/local/etc/php/conf.d/opcache-production.ini

EXPOSE 80

CMD ["apache2-foreground"]