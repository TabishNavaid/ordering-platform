FROM php:8.2-fpm-alpine

# System dependencies
RUN apk add --no-cache \
    nginx \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev

# PHP extensions required by this project
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd

# Copy Nginx config
COPY nginx.conf /etc/nginx/nginx.conf

# Copy application source
COPY . /var/www/html

# Ensure uploads directory exists and is writable
RUN mkdir -p /var/www/html/public/uploads \
 && chown -R www-data:www-data /var/www/html/public/uploads

WORKDIR /var/www/html

EXPOSE 80

# Start PHP-FPM as daemon, then run Nginx in foreground
CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
