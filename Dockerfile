FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libicu-dev \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure intl \
    && docker-php-ext-install pdo pdo_mysql intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache mod_rewrite and set document root to public/
RUN a2enmod rewrite \
 && sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' /etc/apache2/sites-available/000-default.conf \
 && sed -i 's|#ServerName www.example.com|ServerName localhost|g' /etc/apache2/sites-available/000-default.conf \
 && echo 'ServerName localhost' >> /etc/apache2/apache2.conf

# Configure Apache to allow .htaccess overrides in public/
RUN cat <<'EOF' >> /etc/apache2/sites-available/000-default.conf

<Directory /var/www/html>
    AllowOverride None
</Directory>

<Directory /var/www/html/public>
    Options FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

DirectoryIndex index.php index.html
EOF

RUN cat <<'EOF' >> /etc/apache2/apache2.conf

<Directory /var/www/html/public>
    Options FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
EOF

# Copy application source
WORKDIR /var/www/html
COPY . .

# Install Composer dependencies (including dev for Kint debug support)
RUN git config --global --add safe.directory /var/www/html \
 && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --prefer-dist

# Populate system/ThirdParty from installed vendor packages
# (CI4's postUpdate script path-resolves incorrectly when used as a package,
#  so we copy manually after composer install)
RUN SYSTEM=/var/www/html/vendor/codeigniter4/framework/system && \
    VENDOR=/var/www/html/vendor && \
    mkdir -p ${SYSTEM}/ThirdParty/Kint/resources && \
    cp -r ${VENDOR}/kint-php/kint/src/. ${SYSTEM}/ThirdParty/Kint/ && \
    cp -r ${VENDOR}/kint-php/kint/resources/. ${SYSTEM}/ThirdParty/Kint/resources/ && \
    cp ${VENDOR}/kint-php/kint/init.php ${SYSTEM}/ThirdParty/Kint/ && \
    cp ${VENDOR}/kint-php/kint/init_helpers.php ${SYSTEM}/ThirdParty/Kint/ && \
    cp ${VENDOR}/kint-php/kint/LICENSE ${SYSTEM}/ThirdParty/Kint/ && \
    mkdir -p ${SYSTEM}/ThirdParty/Escaper && \
    cp -r ${VENDOR}/laminas/laminas-escaper/src/. ${SYSTEM}/ThirdParty/Escaper/ && \
    cp ${VENDOR}/laminas/laminas-escaper/LICENSE.md ${SYSTEM}/ThirdParty/Escaper/ && \
    mkdir -p ${SYSTEM}/ThirdParty/PSR/Log && \
    cp -r ${VENDOR}/psr/log/src/. ${SYSTEM}/ThirdParty/PSR/Log/ && \
    cp ${VENDOR}/psr/log/LICENSE ${SYSTEM}/ThirdParty/PSR/Log/

# Fix writable directory permissions
RUN mkdir -p writable/logs writable/cache writable/session \
 && chmod -R 777 writable/
