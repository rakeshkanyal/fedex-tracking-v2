FROM php:8.2-apache

# Install system dependencies, PHP extensions, and Ghostscript
RUN apt-get update && apt-get install -y \
    unzip \
    libzip-dev \
    libpng-dev \
    libxml2-dev \
    libonig-dev \
    git \
    curl \
    ghostscript \
    && docker-php-ext-install zip gd xml mbstring \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite

# Configure Apache - keep default port 80
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY ./src /var/www/html/

# Create required directories with proper permissions
RUN mkdir -p uploads pods results vendor \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/uploads /var/www/html/pods /var/www/html/results

# Install Composer dependencies (only if composer.json exists)
RUN if [ -f "composer.json" ]; then \
        composer install --no-dev --optimize-autoloader --no-interaction; \
    fi

# Set proper permissions after composer install
RUN chown -R www-data:www-data /var/www/html

# Expose port 80 (default Apache port)
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]