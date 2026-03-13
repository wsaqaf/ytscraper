FROM php:8.2-apache

# Install dependencies if any (none needed for basic PHP)
# RUN apt-get update && apt-get install -y libpng-dev libjpeg-dev && docker-php-ext-install gd

# Use the default production configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Add custom php.ini settings
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html/

# Create UPLOAD_FOLDER and set permissions
RUN mkdir -p /var/www/html/UPLOAD_FOLDER && \
    chown -R www-data:www-data /var/www/html/UPLOAD_FOLDER && \
    chmod -R 775 /var/www/html/UPLOAD_FOLDER

# Expose port 80
EXPOSE 80
