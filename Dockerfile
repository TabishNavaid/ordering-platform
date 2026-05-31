FROM php:8.2-fpm-alpine

# Wiping cache via an apk update step
RUN apk update && apk add --no-cache nginx supervisor

# Install required PHP extensions
RUN docker-php-ext-install pdo pdo_mysql bcmath

# Create directories for supervisor logging
RUN mkdir -p /var/log/supervisor

# Copy your existing code into the standard container directory
COPY . /var/www/html

# Copy the custom web server and supervisor configs
COPY default.conf /etc/nginx/http.d/default.conf
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Inform Railway to route traffic to port 8080
EXPOSE 8080

# Run everything through the process supervisor manager
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
