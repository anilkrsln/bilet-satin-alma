FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    sqlite3 \
    && docker-php-ext-install pdo pdo_sqlite

WORKDIR /var/www/html

COPY . /var/www/html

RUN a2enmod rewrite

RUN echo "display_errors=On" >> /usr/local/etc/php/conf.d/docker-php.ini

EXPOSE 80
CMD ["apache2-foreground"]