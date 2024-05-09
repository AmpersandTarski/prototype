# To run generated prototypes we require a apache webserver with php
FROM php:8.3-apache-bullseye as framework

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

# Move frontend to wwwroot
COPY frontend /var/www/frontend
COPY backend /var/www/backend

WORKDIR /var/www/frontend

# Install frontend dependencies using NPM package specification (package.json)
RUN npm install

FROM framework as project-administration

COPY test/assets/project-administration /usr/local/project/

# Run ampersand compiler to generated new frontend and backend json model files (in generics folder)
RUN ampersand proto --no-frontend /usr/local/project/model/ProjectAdministration.adl \
  --proto-dir /var/www/backend \
  --crud-defaults cRud \
  --verbose

RUN ampersand proto --frontend-version Angular --no-backend /usr/local/project/model/ProjectAdministration.adl \
  --proto-dir /var/www/frontend/src/app/generated \
  --crud-defaults cRud \
  --verbose

WORKDIR /var/www/frontend

RUN npx ng build

# Copy output from frontend build
RUN cp -r /var/www/frontend/dist/prototype-frontend/* /var/www/html
