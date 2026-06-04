FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY postcss.config.js tailwind.config.js tsconfig.json vite.config.js ./

RUN rm -f public/hot && npm run build

FROM php:8.3-apache-bookworm AS app

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libpq-dev \
        libzip-dev \
        unzip \
        zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath gd pcntl pdo_pgsql zip \
    && a2enmod rewrite headers \
    && sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf \
    && printf 'ServerName localhost\n' > /etc/apache2/conf-available/server-name.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/laravel.conf \
    && a2enconf server-name \
    && a2enconf laravel \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/stimergie.ini

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
COPY --from=assets /app/public/build ./public/build
COPY docker/entrypoint.sh /usr/local/bin/stimergie-entrypoint

RUN rm -f public/hot \
    && composer dump-autoload --no-dev --optimize \
    && mkdir -p storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache public/build \
    && chmod +x /usr/local/bin/stimergie-entrypoint

ENTRYPOINT ["stimergie-entrypoint"]
CMD ["apache2-foreground"]
