FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    libzip-dev \
    libicu-dev \
    && docker-php-ext-install \
        pdo_mysql \
        bcmath \
        intl \
        zip \
    && rm -rf /var/lib/apt/lists/*
