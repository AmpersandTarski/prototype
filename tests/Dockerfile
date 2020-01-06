FROM composer:latest

# Install additional php/apache extensions
# enable ZipArchive for importing .xlsx files on runtime
RUN apk update && apk add libzip

RUN docker-php-ext-install \
  mysqli
  # zip # not yet working somehow

RUN apk --no-cache add pcre-dev ${PHPIZE_DEPS} \ 
  && pecl install ast \
  && docker-php-ext-enable ast \
  && apk del pcre-dev ${PHPIZE_DEPS}

# Overwrite entrypoint of composer image
ENTRYPOINT []