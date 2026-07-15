FROM php:8.3-cli-alpine

# Install common utilities
RUN apk add --no-cache curl zip unzip git sqlite

# Install PHP extensions using mlocati's script
# This handles all dependencies automatically
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions opcache curl gd mbstring pcntl posix pdo pdo_sqlite sqlite3

WORKDIR /app

# Copy project files
COPY . /app/

# Setup permissions
RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app/runtime

EXPOSE 8080

CMD ["php", "nova.phar", "serve", "start"]