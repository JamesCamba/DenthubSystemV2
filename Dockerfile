# Use official PHP 8.2 image with Apache
FROM php:8.2-apache

# Install PostgreSQL extensions and dependencies
RUN apt-get update && \
    apt-get install -y \
    libpq-dev \
    postgresql-client \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install Composer dependencies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    composer install --no-dev --optimize-autoloader && \
    rm -rf /root/.composer

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port (Railway sets PORT automatically)
EXPOSE $PORT

# Configure Apache to use Railway's PORT
RUN echo "Listen $PORT" > /etc/apache2/ports.conf && \
    sed -i "s/80/$PORT/g" /etc/apache2/sites-available/000-default.conf

# Start Apache
CMD apache2-foreground
