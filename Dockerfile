FROM php:7.4-fpm

RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libmariadb-dev \
    && docker-php-ext-install pdo pdo_mysql sockets

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json ./
RUN composer install

COPY . .