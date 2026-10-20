# Multi-stage build for NutriTrace Laravel application.
# The image holds no .env and no secret: it is configured with environment variables
# (docker-compose.yml locally, a ConfigMap and a Secret in Kubernetes).

# Stage 1: Build frontend assets with Node.js
FROM node:22.23.3-alpine AS frontend-builder

WORKDIR /app

# Copy package files and install dependencies
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts --no-audit --no-fund

# Copy source files needed for Vite build
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

# Build production assets
RUN npm run build

# Stage 2: PHP application with Apache
FROM php:8.4.26-apache-bookworm

# PHP extensions, installed as prebuilt packages (minutes faster than compiling them):
# MySQL, gd (QR codes), intl, bcmath, zip (Composer), exif, pcntl and OPcache.
COPY --from=mlocati/php-extension-installer:2.12.2 /usr/bin/install-php-extensions /usr/local/bin/
RUN apt-get update \
    # Apply security updates
    && apt-get upgrade -y \
    && install-php-extensions pdo_mysql gd intl bcmath zip exif pcntl opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer (removed again once the dependencies are installed)
COPY --from=composer:2.10.3 /usr/bin/composer /usr/bin/composer

# Configure Apache and PHP: Laravel's public/ folder on port 8080 (above 1024, so Apache
# runs without root), .htaccess enabled, no directory listing, versions kept private.
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-nutritrace.ini
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && a2enmod rewrite headers \
    && sed -i 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && chown -R www-data:www-data /var/run/apache2 /var/lock/apache2

# Set working directory
WORKDIR /var/www/html

# Copy composer files and install PHP dependencies: the exact versions of composer.lock,
# the ones the tests ran with. This layer is reused as long as composer.lock does not change.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

# Copy application code
COPY . .

# Copy built frontend assets from builder stage
COPY --from=frontend-builder /app/public/build ./public/build

# Startup script: caches the configuration, migrates if asked (see docker/entrypoint.sh)
COPY docker/entrypoint.sh /usr/local/bin/nutritrace-entrypoint

# Generate optimized autoloader, link public/storage (logos, avatars), set permissions.
# The code belongs to root and stays read-only for the web server: only storage/ and
# bootstrap/cache/ are writable by www-data.
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && rm /usr/bin/composer \
    && php artisan storage:link \
    && sed -i 's/\r$//' /usr/local/bin/nutritrace-entrypoint \
    && chmod 755 /usr/local/bin/nutritrace-entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache

# Everything from here on (Apache, artisan commands, docker exec) runs as www-data.
USER www-data

# Health check endpoint
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD curl -f http://localhost:8080/api/v1/health || exit 1

# Expose port 8080
EXPOSE 8080

ENTRYPOINT ["nutritrace-entrypoint"]

# Start Apache in foreground
CMD ["apache2-foreground"]
