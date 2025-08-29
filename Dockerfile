FROM php:5.6-apache AS php_5_6

RUN docker-php-ext-install mysqli