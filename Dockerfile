FROM php:8.2-apache
# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql
# Enable Apache mod_rewrite just in case
RUN a2enmod rewrite
