FROM serversideup/php:8.4-fpm-nginx

# Switch to root to perform installations and file copying
USER root

# Install dependencies
RUN install-php-extensions bcmath

# Install Node.js for frontend build
RUN curl -sL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

WORKDIR /var/www/html

# Copy composer files and install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Copy package files and install node dependencies
COPY package.json package-lock.json ./
RUN npm ci

# Copy the rest of the application source code
COPY . .

# Build frontend assets
RUN npm run build

# Fix permissions for the webuser (standard user in serversideup images)
RUN chown -R webuser:webgroup /var/www/html

# Switch back to the non-root user for security
USER webuser
