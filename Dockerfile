FROM serversideup/php:8.4-fpm-nginx

# Switch to root to perform installations and file copying
USER root

# Install dependencies
RUN install-php-extensions bcmath gd zip intl

# Install Node.js for frontend build
RUN curl -sL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get update && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy composer files first (for better caching)
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Copy package files and install node dependencies
COPY --chown=www-data:www-data package.json package-lock.json ./
RUN npm ci --legacy-peer-deps

# Copy the rest of the application
COPY --chown=www-data:www-data . .

# Build assets and run Laravel optimizations
RUN npm run build && \
    php artisan storage:link && \
    php artisan view:cache

# Set permissions for storage and cache
RUN chmod -R 775 storage bootstrap/cache

# Switch back to the non-root user for security
USER www-data
