
FROM php:8.2-apache


RUN apt-get update && apt-get install -y --no-install-recommends \
    libsqlite3-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    build-essential \
    make \
    autoconf \
    libzip-dev \
    libonig-dev \
    && rm -rf /var/lib/apt/lists/*


RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_sqlite mbstring zip

RUN a2enmod rewrite


COPY --from=composer:latest /usr/bin/composer /usr/bin/composer


WORKDIR /var/www/html


COPY composer.json composer.lock* ./

RUN if [ -f composer.json ]; then composer install --no-interaction --no-dev --optimize-autoloader; fi

COPY . .

RUN mkdir -p /var/www/html/data && \
    chown -R www-data:www-data /var/www/html/data && \
    if [ -d "/var/www/html/vendor" ]; then chown -R www-data:www-data /var/www/html/vendor; fi

RUN if [ -d "/var/www/html/font" ]; then chown -R www-data:www-data /var/www/html/font; fi

