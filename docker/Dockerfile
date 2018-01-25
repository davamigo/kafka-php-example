# See https://hub.docker.com/_/php/
FROM php:7.1-apache

ENV LIBRDKAFKA_VERSION v0.9.5

ENV LIBRDKAFKA_DEPS \
    build-essential \
    libsasl2-dev \
    libssl-dev \
    python-minimal \
    zlib1g-dev

## Add user david
RUN useradd -m -u 1000 -U david

# Install O.S extensions
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
        git \
        zip \
        unzip \
        ${LIBRDKAFKA_DEPS}

# Install Kafka libreries for PHP
RUN cd /tmp \
 && git clone \
        --branch ${LIBRDKAFKA_VERSION} \
        --depth 1 \
        https://github.com/edenhill/librdkafka.git \
    && cd librdkafka \
    && ./configure \
    && make \
    && make install \
    && pecl install rdkafka \
    && docker-php-ext-enable rdkafka \
    && rm -rf /tmp/librdkafka

# Install PHP extensions
#RUN docker-php-ext-install \
#        pdo_mysql \
#        bcmath

# Enable PHP extensions
#RUN docker-php-ext-enable \
#        pdo_mysql \
#        bcmath

# Install composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
 && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
 && php -r "unlink('composer-setup.php');"

# Clean up
RUN apt-get purge \
        -y --auto-remove \
        -o APT::AutoRemove::RecommendsImportant=false \
        ${LIBRDKAFKA_DEPS}

# Set the working directory
WORKDIR /var/www/html
