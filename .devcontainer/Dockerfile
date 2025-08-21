# Use a lightweight Linux base 
FROM node:18-bookworm-slim

# Install essential development tools and Docker CLI
RUN apt-get update && apt-get install -y \
    git \
    curl \
    wget \
    vim \
    nano \
    htop \
    tree \
    jq \
    ca-certificates \
    gnupg \
    lsb-release \
    && rm -rf /var/lib/apt/lists/*

# Install Docker CLI (not Docker daemon - we'll use host daemon via socket)
RUN curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg \
    && echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/debian $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null \
    && apt-get update \
    && apt-get install -y docker-ce-cli docker-compose-plugin \
    && rm -rf /var/lib/apt/lists/*

# Install global npm packages for development
RUN npm install -g @angular/cli@latest

# Set working directory
WORKDIR /var/www

# Set environment variables for better extension compatibility
ENV SHELL=/bin/bash
ENV TERM=xterm-256color
ENV NODE_ENV=development

# Ensure proper permissions
RUN chown -R www-data:www-data /var/www

# Keep container running
CMD ["tail", "-f", "/dev/null"]
