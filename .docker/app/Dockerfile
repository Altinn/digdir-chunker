# filepath: ./Dockerfile
FROM jkaninda/laravel-php-fpm:latest

# Set environment variables for pipx
ENV PIPX_HOME=/var/www/.local
ENV PIPX_BIN_DIR=/var/www/.local/bin
ENV PATH="${PIPX_BIN_DIR}:${PATH}"

# Install dependencies
RUN apt update && apt install -y python3-pip pipx \
    && pipx install 'marker-pdf[full]' \
    && apt clean

# Set the working directory
WORKDIR /var/www/html

