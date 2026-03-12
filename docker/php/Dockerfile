FROM php:8.5.3-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libicu-dev \
        libonig-dev \
        libpq-dev \
        libxml2-dev \
        libzip-dev \
        python3 \
        python3-jsonschema \
        unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install \
        bcmath \
        intl \
        mbstring

RUN docker-php-ext-install \
        pcntl \
        pdo_pgsql \
        sockets \
        zip

RUN docker-php-ext-install \
        xml \
        simplexml

RUN pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php-fpm"]
