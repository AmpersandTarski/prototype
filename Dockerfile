# To run generated prototypes we require a apache webserver with php
FROM php:8.3-apache-bookworm AS framework

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
ENV COMPOSER_HOME=/usr/local/bin/

# Install NodeJs with NPM
RUN curl -sL https://deb.nodesource.com/setup_18.x  | bash - \
  && apt-get install -y nodejs \
  && node -v \
  && npm -v

# Copy Ampersand compiler
# NOTE! Also check/update constraints in compiler-version.txt when updating the compiler
COPY --from=ampersandtarski/ampersand:v5.3.2 /bin/ampersand /usr/local/bin
RUN chmod +x /usr/local/bin/ampersand

# Install php backend dependencies using PHP Composer package specification (composer.json)
COPY composer.json composer.lock /var/www/
WORKDIR /var/www
RUN composer --version \
 && composer check-platform-reqs
RUN composer install --prefer-dist --no-dev --profile

# Add default data folder that Apache can write to
RUN mkdir /var/www/data && chown -R www-data:www-data /var/www/data

# Install frontend dependencies using NPM package specification (package.json)
COPY frontend/package.json frontend/package-lock.json /var/www/frontend/
WORKDIR /var/www/frontend
RUN npm install

# Copy remaining parts of framework
COPY frontend /var/www/frontend
COPY apache-conf/.htaccess /var/www/html
COPY backend /var/www/backend
RUN mv /var/www/backend/public/* /var/www/html/
