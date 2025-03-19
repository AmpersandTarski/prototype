# Use the base PHP Dev Container image
FROM mcr.microsoft.com/devcontainers/php:1-8.2-bullseye

# Install required PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libjpeg-dev \
    libpng-dev \
    libwebp-dev \
    libxpm-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd mysqli zip

# Install opentelemetry extension
RUN pecl install opentelemetry && docker-php-ext-enable opentelemetry

# Clean up
RUN apt-get clean && rm -rf /var/lib/apt/lists/*