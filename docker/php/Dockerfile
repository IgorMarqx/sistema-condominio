# Imagem de produção: PHP-FPM 8.3 com poppler (pdftotext), qpdf e o cliente do MySQL (backup).
FROM node:24-alpine AS front
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM php:8.3-fpm-bookworm
RUN apt-get update \
    && apt-get install -y --no-install-recommends poppler-utils qpdf default-mysql-client libzip-dev libicu-dev libpng-dev unzip \
    && docker-php-ext-install pdo_mysql bcmath intl zip gd pcntl \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
COPY --from=front /app/public/build public/build
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
