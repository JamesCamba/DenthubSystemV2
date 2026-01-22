# Use official PHP 8.2 image
FROM php:8.2-cli

# Install system dependencies and PHP extensions
RUN apt-get update && \
    apt-get install -y \
    libpq-dev \
    postgresql-client \
    git \
    unzip \
    zip \
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

# Expose port (Railway sets PORT automatically)
EXPOSE 8080

# Start PHP built-in server (Railway sets PORT env var)
CMD php -S 0.0.0.0:${PORT:-8080} -t .
