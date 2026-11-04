FROM php:8.3-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev libzip-dev libsqlite3-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 gd intl pdo_sqlite zip opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV APACHE_DOCUMENT_ROOT=/var/www/html/web
RUN sed -ri 's!/var/www/html!/var/www/html/web!g' /etc/apache2/sites-available/*.conf \
    && printf '<Directory /var/www/html/web>\nAllowOverride All\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/drupal.conf \
    && a2enconf drupal \
    && printf 'memory_limit=256M\n' > /usr/local/etc/php/conf.d/drupal.ini
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --no-interaction --no-progress
COPY lifeos_sync web/modules/custom/lifeos_sync
COPY scripts scripts
EXPOSE 80
ENTRYPOINT ["/var/www/html/scripts/docker-entrypoint.sh"]
