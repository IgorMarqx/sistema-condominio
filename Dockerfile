FROM php:8.3-cli-bookworm AS composer-deps

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libicu-dev \
        libpng-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install gd intl pcntl zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY . .
RUN composer dump-autoload --optimize --no-dev


# O plugin do Wayfinder roda o artisan no build: o Node fica numa imagem com PHP e o vendor.
FROM php:8.3-cli-bookworm AS frontend-build

WORKDIR /app

COPY --from=node:24-bookworm-slim /usr/local /usr/local
COPY --from=composer-deps /app/vendor /app/vendor

COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY . .
RUN npm run build


FROM php:8.3-fpm-bookworm AS app

WORKDIR /var/www/html

# poppler-utils (pdftotext) e qpdf leem as faturas em PDF; o cliente do MySQL faz o backup diário.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        default-mysql-client \
        git \
        libicu-dev \
        libonig-dev \
        libpng-dev \
        libzip-dev \
        poppler-utils \
        qpdf \
        unzip \
    && docker-php-ext-install \
        bcmath \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer-deps /app /var/www/html
COPY --from=frontend-build /app/public/build /var/www/html/public/build
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/php/entrypoint.sh /usr/local/bin/laravel-entrypoint

RUN chmod +x /usr/local/bin/laravel-entrypoint \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

USER www-data

ENTRYPOINT ["laravel-entrypoint"]
CMD ["php-fpm"]


FROM nginx:1.27-alpine AS nginx

WORKDIR /var/www/html

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
