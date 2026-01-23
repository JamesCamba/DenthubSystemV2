# Use official PHP 8.2 image
FROM php:8.2-cli

# Install system dependencies and PHP extensions
# libzip-dev and zlib1g-dev are required for PHP zip extension
RUN apt-get update && \
    apt-get install -y \
    libpq-dev \
    postgresql-client \
    git \
    unzip \
    zip \
    libzip-dev \
    zlib1g-dev \
    && docker-php-ext-install pdo pdo_pgsql zip \
    && apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /app

# Copy application files
COPY . .

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader

# Expose port (Render/Railway sets PORT automatically)
EXPOSE 8080

# Start PHP built-in server (Render/Railway sets PORT env var)
# Use server.php as router for proper URL handling
CMD php -S 0.0.0.0:${PORT:-8080} -t . server.php