ARG AMPERSAND_IMAGE_VERSION=latest
FROM docker.pkg.github.com/ampersandtarski/ampersand/ampersand:${AMPERSAND_IMAGE_VERSION} as compiler

# To run generated prototypes we require a apache webserver with php
FROM php:7.3-apache

RUN apt-get update \
 && apt-get install -y libzip-dev zlib1g-dev

# Install additional php/apache extensions
# enable ZipArchive for importing .xlsx files on runtime
RUN docker-php-ext-install mysqli zip\
 && a2enmod rewrite

# Install composer (php's package manager)
RUN php  -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
 && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
 && php -r "unlink('composer-setup.php');" \
 && rm -rf /var/lib/apt/lists/*
ENV COMPOSER_HOME /usr/local/bin/

# Enable Apache to write to www folder
RUN chown -R www-data:www-data /var/www

# Change doc root. Let's move to apache conf file when more configuration is needed
ENV APACHE_DOCUMENT_ROOT /var/www/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY . /var/www

WORKDIR /var/www

RUN composer install --prefer-dist --no-dev --profile

# Copy Ampersand compiler
COPY --from=compiler /root/.local/bin/ampersand /usr/local/bin
RUN chmod +x /usr/local/bin/ampersand