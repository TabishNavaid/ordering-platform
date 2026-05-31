FROM php:8.2-fpm-alpine

# Install system dependencies and Nginx
RUN apk add --no-cache nginx

# Install required PHP extensions
RUN docker-php-ext-install pdo pdo_mysql bcmath

# Copy your existing code into the standard container directory
COPY . /var/www/html

# Copy the custom web server config
COPY default.conf /etc/nginx/http.d/default.conf

# Inform Railway to route traffic to port 8080
EXPOSE 8080

# Run PHP-FPM and Nginx side-by-side cleanly
CMD php-fpm -D && nginx -g "daemon off;"
