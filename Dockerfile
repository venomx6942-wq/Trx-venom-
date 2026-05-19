FROM php:8.2-apache

# Install SQLite3 extension (teri database ke liye)
RUN apt-get update && apt-get install -y sqlite3 libsqlite3-dev
RUN docker-php-ext-install pdo_sqlite

# Enable mod_rewrite for Apache
RUN a2enmod rewrite

# Copy your PHP files
COPY . /var/www/html/

# Set permissions
RUN chmod -R 777 /var/www/html

EXPOSE 80