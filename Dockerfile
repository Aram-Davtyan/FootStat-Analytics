FROM php:8.4-fpm

RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /var/www

COPY . .

RUN chown -R www-data:www-data /var/www
