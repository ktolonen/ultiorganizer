FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    gettext \
    locales \
    libpng-dev \
    zlib1g-dev \
 && locale-gen en_US.UTF-8 \
 && docker-php-ext-install mysqli gettext gd mbstring \
 && rm -rf /var/lib/apt/lists/*

ENV LANG=en_US.UTF-8
ENV LC_ALL=en_US.UTF-8
