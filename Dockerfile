# To run generated prototypes we require a apache webserver with php
FROM php:8.3-apache-bullseye

RUN apt-get update \
 && apt-get install -y \
  # libzip and zlin needed for php-ext zip below
  libzip-dev \
  zlib1g-dev \
  # lubcurl needed for php-ext curl below
  libcurl4-gnutls-dev \
  # libpng needed for php-ext gd below
  libpng-dev \
  # vim for easy editing files in container
  vim

# Install additional php and apache extensions (see composer.json file)
RUN docker-php-ext-install mysqli curl gd fileinfo zip \
 && a2enmod rewrite

# Install composer (php's package manager)
RUN php  -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
 && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
 && php -r "unlink('composer-setup.php');" \
 && rm -rf /var/lib/apt/lists/*
ENV COMPOSER_HOME /usr/local/bin/

# Install NodeJs with NPM
RUN curl -sL https://deb.nodesource.com/setup_18.x  | bash - \
  && apt-get install -y nodejs \
  && node -v \
  && npm -v

# Install php backend dependencies using PHP Composer package specification (composer.json)
COPY composer.json composer.lock /var/www/
WORKDIR /var/www
RUN composer --version \
 && composer check-platform-reqs
RUN composer install --prefer-dist --no-dev --profile

# Copy Ampersand compiler
# NOTE! Also check/update constraints in compiler-version.txt when updating the compiler
COPY --from=ampersandtarski/ampersand:v4.7 /bin/ampersand /usr/local/bin
RUN chmod +x /usr/local/bin/ampersand

# Add default data folder that Apache can write to
RUN mkdir /var/www/data && chown -R www-data:www-data /var/www/data

# Change doc root. Let's move to apache conf file when more configuration is needed
ENV APACHE_DOCUMENT_ROOT /var/www/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Move frontend to wwwroot
COPY frontend /var/www/frontend

# Copy the rest of the prototype framework
COPY bootstrap/ /var/www/bootstrap
COPY config/ /var/www/config
COPY public/api/ /var/www/public/api
COPY src/Ampersand/ /var/www/src/Ampersand

# Build ampersand frontend application (needs to be done in project Dockerfile with new frontend (right?))
#WORKDIR /var/www
#RUN gulp build-ampersand

WORKDIR /var/www/frontend

# Install frontend dependencies using NPM package specification (package.json)
RUN npm install

# Empty folder needed for ampersand compiler to write files to (later in project dockerfile)
RUN mkdir /var/www/generics
