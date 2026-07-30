FROM composer:2 AS vendor

WORKDIR /app/backend
COPY backend/composer.json ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader

FROM php:8.3-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev \
    && docker-php-ext-install curl mbstring pdo_mysql \
    && a2enmod headers rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html
COPY --from=vendor /app/backend/vendor /var/www/html/backend/vendor
COPY docker/apache/ffticket.conf /etc/apache2/sites-available/000-default.conf
COPY docker/app/entrypoint.sh /usr/local/bin/ffticket-entrypoint
COPY docker/app/bootstrap-admin.php /opt/ffticket/bootstrap-admin.php

RUN sed -i 's/\r$//' /usr/local/bin/ffticket-entrypoint \
    && mkdir -p /var/www/html/backend/storage/uploads \
    && chown -R www-data:www-data /var/www/html/backend/storage/uploads \
    && chmod 0755 /usr/local/bin/ffticket-entrypoint

WORKDIR /var/www/html
ENTRYPOINT ["ffticket-entrypoint"]
CMD ["apache2-foreground"]
