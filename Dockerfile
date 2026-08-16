FROM php:8.3-fpm-alpine

RUN apk add --no-cache freetype libjpeg-turbo libpng libzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS freetype-dev libjpeg-turbo-dev libpng-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd opcache zip \
    && apk del .build-deps

WORKDIR /app

COPY php.ini /usr/local/etc/php/conf.d/99-book.ini
COPY --chown=www-data:www-data src/ /app/

RUN mkdir -p /app/runtime \
    && chown -R www-data:www-data /app/runtime

EXPOSE 9000

CMD ["php-fpm"]