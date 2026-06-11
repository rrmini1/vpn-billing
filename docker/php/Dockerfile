FROM php:8.4-fpm-bookworm

ARG UID=1000
ARG GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        bash \
        ca-certificates \
        curl \
        git \
        libicu-dev \
        libonig-dev \
        libpq-dev \
        libzip-dev \
        unzip \
        zip \
    && docker-php-ext-install \
        bcmath \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        sockets \
        zip \
    && pecl install redis xdebug \
    && docker-php-ext-enable redis xdebug \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/* /tmp/pear ~/.pearrc

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-vpn-billing.ini
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/20-xdebug.ini

RUN groupadd -g ${GID} app \
    && useradd -m -u ${UID} -g app -s /bin/bash app \
    && mkdir -p /application /tmp/composer-cache \
    && chown -R app:app /application /tmp/composer-cache

WORKDIR /application

USER app

ENV COMPOSER_HOME=/tmp/composer-cache

CMD ["php-fpm"]
