# Push image from this Dockerfile to ampersandtarski/prototype-framework-ci-runner
# Is used by CI script (Github Actions)
FROM jakzal/phpqa:php7.4-alpine

RUN apk --no-cache add \
  curl-dev \
  libpng-dev \
  libzip-dev
RUN docker-php-ext-install mysqli curl gd fileinfo zip
