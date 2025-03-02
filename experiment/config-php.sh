#!/bin/bash

cd /var/www

apt-get update \
 && apt-get install -y \
  libzip-dev \
  zlib1g-dev \
  libcurl4-gnutls-dev \
  libpng-dev \
  vim unzip

docker-php-ext-install mysqli curl gd fileinfo zip \
    && a2enmod rewrite


php  -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
&& php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
&& php -r "unlink('composer-setup.php');" \
&& rm -rf /var/lib/apt/lists/*

export COMPOSER_HOME=/usr/local/bin/

# Install open telemetry
pecl install opentelemetry

cat <<EOF > /usr/local/etc/php/conf.d/opentelemetry.ini
[opentelemetry]
extension=opentelemetry.so
EOF


composer require --no-interaction \
    open-telemetry/sdk \
    open-telemetry/exporter-otlp \
    open-telemetry/opentelemetry-auto-slim \
    open-telemetry/opentelemetry-auto-psr18

composer require --no-interaction \
    slim/slim \
    php \
    ext-mysqli \
    ext-curl \
    ext-gd \
    ext-fileinfo \
    ext-zip \
    monolog/monolog \
    phpoffice/phpspreadsheet \
    psr/cache \
    psr/log \
    symfony/event-dispatcher \
    symfony/serializer \
    symfony/filesystem \
    easyrdf/easyrdf \
    league/flysystem \
    ramsey/uuid \
    symfony/yaml
