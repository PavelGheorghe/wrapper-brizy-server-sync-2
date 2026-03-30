FROM php:7.4-apache

RUN apt-get update \
    && apt-get install -y libzip-dev zip unzip \
    && docker-php-ext-install zip
# RUN apt-get update && apt-get install -y zip
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer



COPY . /var/www/html/

WORKDIR /var/www/html/

RUN curl "https://awscli.amazonaws.com/awscli-exe-linux-aarch64.zip" -o "awscliv2.zip"
RUN unzip awscliv2.zip
RUN ./aws/install

RUN #composer clear-cache && composer install


RUN a2enmod rewrite
