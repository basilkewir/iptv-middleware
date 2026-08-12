FROM php:8.1-fpm

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libssl-dev \
    libcurl4-openssl-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    unzip \
    zip \
    ffmpeg \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/uploads.ini

RUN groupadd -g 1000 www && useradd -u 1000 -ms /bin/bash -g www www

RUN mkdir -p /var/www/storage/framework/{sessions,views,cache} \
    && chown -R www:www /var/www \
    && chmod -R 775 /var/www/storage \
    && sed -i 's/listen = 127.0.0.1:9000/listen = 0.0.0.0:9000/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/user = www-data/user = www/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/group = www-data/group = www/' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www

USER www

EXPOSE 9000

CMD ["php-fpm"]
