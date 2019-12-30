FROM jakzal/phpqa:php7.4-alpine

# Install additional php/apache extensions
# enable ZipArchive for importing .xlsx files on runtime
# RUN apk update && apk add --no-cache libzip-dev zlib1g-dev \
RUN docker-php-ext-install mysqli zip