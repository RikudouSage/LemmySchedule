FROM php:8.3-apache

ENV APP_ENV prod
ENV APP_CACHE_DIR=/opt/runtime-cache
ENV LOCAL_FILE_UPLOADER_PATH /opt/uploaded-files
ENV MESSENGER_TRANSPORT_DSN=redis://redis:6379/lemmy_schedule_jobs
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

EXPOSE 80

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf && \
    sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN apt-get update && \
    apt-get -y install libxml2-dev libonig-dev libmagickwand-dev supervisor unzip wget && \
    rm -rf /var/lib/apt/lists/*
RUN mkdir -p /usr/src/php/ext/imagick && curl -fsSL https://github.com/Imagick/imagick/archive/944b67fce68bcb5835999a149f917670555b6fcb.tar.gz | tar xvz -C "/usr/src/php/ext/imagick" --strip 1 && \
    docker-php-ext-install intl opcache pcntl imagick && \
    pecl install redis && \
    docker-php-ext-enable redis
RUN a2enmod rewrite

COPY --chown=33:33 . /var/www/html
COPY docker/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/supervisor.conf /etc/supervisor/conf.d/messenger-worker.conf
RUN mkdir /opt/runtime-cache && \
    mkdir /opt/logs && \
    mkdir /opt/uploaded-files && \
    chown 33:33 /opt/* && \
    chmod +x /var/www/html/docker/entrypoint.bash

ENTRYPOINT /var/www/html/docker/entrypoint.bash
