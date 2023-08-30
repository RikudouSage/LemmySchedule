FROM php:8.2-apache

ENV APP_CACHE_DIR=/opt/runtime-cache
ENV APP_LOG_DIR=/opt/logs
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

EXPOSE 80

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN apt-get update && \
    apt-get -y install libxml2-dev libonig-dev unzip && \
    rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install intl opcache
RUN pecl install redis && \
    docker-php-ext-enable redis
RUN a2enmod rewrite

COPY docker/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY . /var/www/html

ENTRYPOINT /var/www/html/docker/prod/entrypoint.bash
