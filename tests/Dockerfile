FROM composer:latest

# Install additional php/apache extensions
# enable ZipArchive for importing .xlsx files on runtime
RUN apk update && apk add libzip

RUN docker-php-ext-install \
  mysqli
  # zip # not yet working somehow

# Overwrite entrypoint of composer image
ENTRYPOINT []