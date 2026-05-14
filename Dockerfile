# syntax=docker/dockerfile:1.7

FROM php:8.3-fpm-alpine AS base

ARG APP_ENV=dev
ENV APP_ENV=${APP_ENV} \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1

RUN apk add --no-cache \
        bash \
        git \
        icu-dev \
        libsodium-dev \
        libzip-dev \
        oniguruma-dev \
        openssl-dev \
        zip \
        unzip \
        curl \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
        intl \
        opcache \
        pdo_mysql \
        sodium \
        zip \
        bcmath

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* symfony.lock* ./
RUN composer install --no-interaction --no-scripts --no-progress --prefer-dist $([ "$APP_ENV" = "prod" ] && echo --no-dev --optimize-autoloader)

COPY . .

RUN mkdir -p var/cache var/log var/coverage public/build \
    && chown -R www-data:www-data var public/build

USER www-data

EXPOSE 9000

CMD ["php-fpm", "-F"]
