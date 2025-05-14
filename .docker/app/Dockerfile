# filepath: ./Dockerfile
FROM jkaninda/laravel-php-fpm:latest

ENV PATH="/opt/marker/bin:${PATH}"

RUN apt update \
    && apt install -y python3-pip python3-venv \
    && python3 -m venv /opt/marker \
    && /opt/marker/bin/pip install --upgrade pip \
    && /opt/marker/bin/pip install 'marker-pdf[full]' \
    && apt clean

RUN chown -R www-data:www-data /opt/marker

# Set the working directory
WORKDIR /var/www/html

# ENTRYPOINT ["/var/www/html/artisan migrate && /var/www/html/artisan queue:restart"]
