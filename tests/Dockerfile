FROM composer:latest

RUN apk --no-cache add \
  # pecl, needed to do pecl install below
  pcre-dev \
  curl-dev \
  libpng-dev \
  libzip-dev \
  ${PHPIZE_DEPS}
RUN docker-php-ext-install mysqli curl gd fileinfo zip
RUN pecl install ast
RUN docker-php-ext-enable ast

RUN apk del \
  pcre-dev \
  curl-dev \
  libpng-dev \
  libzip-dev \
  ${PHPIZE_DEPS}

# Overwrite entrypoint of composer image
ENTRYPOINT []