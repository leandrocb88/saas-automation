# Stage 1: PHP Dependencies
FROM serversideup/php:8.4-fpm-nginx AS composer
USER root
RUN install-php-extensions bcmath gd zip intl pdo_mysql pdo_pgsql opcache
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Stage 2: Build assets
FROM node:20-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --legacy-peer-deps
COPY . .
# Copy vendor from composer stage so TypeScript can find Ziggy files
COPY --from=composer /app/vendor ./vendor
RUN npm run build

# Stage 3: PHP Application
FROM serversideup/php:8.4-fpm-nginx
USER root

# Install PHP extensions
RUN install-php-extensions bcmath gd zip intl pdo_mysql pdo_pgsql opcache

# Copy PHP configuration
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Copy source and vendor from composer stage
WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=composer /app/vendor ./vendor

# Copy built assets from assets stage
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

# Remove source node_modules and other dev files from final image
RUN rm -rf node_modules tests

# Add automatic migration script
COPY --chown=www-data:www-data deploy/run-migrations.sh /etc/entrypoint.d/run-migrations.sh
RUN chmod +x /etc/entrypoint.d/run-migrations.sh

# Run Laravel optimizations
RUN php artisan storage:link && \
    php artisan view:cache && \
    php artisan config:cache && \
    php artisan route:cache

# Set permissions
RUN chmod -R 775 storage bootstrap/cache database

# Switch back to the non-root user
USER www-data
