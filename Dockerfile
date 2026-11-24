FROM php:8.4-apache

# Install mysqli extension
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Enable Apache mod_rewrite and headers
RUN a2enmod rewrite headers

# Copy application code
COPY . /var/www/html/

# Set proper permissions for uploads directory
RUN mkdir -p /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html/uploads && \
    chmod -R 755 /var/www/html/uploads

EXPOSE 80
CMD ["apache2-foreground"]
