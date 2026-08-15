# To run generated prototypes we require a apache webserver with php
ARG COMPILER_IMAGE=ampersandtarski/ampersand:v5.9.7
FROM --platform=linux/amd64 ${COMPILER_IMAGE} AS compiler
FROM --platform=linux/amd64 php:8.3-apache-bookworm AS framework

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
  vim \
  # build tools needed by pecl to compile the opentelemetry extension below
  $PHPIZE_DEPS

# Install additional php and apache extensions (see composer.json file)
RUN docker-php-ext-install mysqli curl gd fileinfo zip \
 && a2enmod rewrite

# Install the OpenTelemetry PHP extension (needed for zero-code auto-instrumentation)
RUN pecl install opentelemetry \
 && docker-php-ext-enable opentelemetry

# OpenTelemetry configuration. Disabled by default; enable per deployment by setting
# OTEL_SDK_DISABLED=false. See docs/guides/measuring-performance-with-opentelemetry.md
ENV OTEL_SDK_DISABLED=true
ENV OTEL_PHP_AUTOLOAD_ENABLED=true
ENV OTEL_SERVICE_NAME=ampersand-prototype
ENV OTEL_TRACES_EXPORTER=console
ENV OTEL_METRICS_EXPORTER=none
ENV OTEL_LOGS_EXPORTER=none
ENV OTEL_PROPAGATORS=baggage,tracecontext

# Copy site configuration file
COPY ./docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

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
COPY --from=compiler /bin/ampersand /usr/local/bin
RUN chmod +x /usr/local/bin/ampersand

# Add default data folder that Apache can write to
RUN mkdir /var/www/data && chown -R www-data:www-data /var/www/data
