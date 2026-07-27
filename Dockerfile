FROM php:7.4-fpm

RUN apt-get update && apt-get install -y \
   git \
   zip \
   unzip \
   libmariadb-dev \
   libmemcached-dev \
   zlib1g-dev \
   && docker-php-ext-install pdo pdo_mysql sockets

RUN pecl channel-update pecl.php.net \
    && pecl install --force memcached-3.2.0 \
    && docker-php-ext-enable memcached

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json ./
RUN composer install

COPY . .