FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    gettext \
    libjpeg62-turbo-dev \
    libonig-dev \
    locales \
    libpng-dev \
    zlib1g-dev \
 && sed -i '/^# *en_US.UTF-8 UTF-8/s/^# *//' /etc/locale.gen \
 && sed -i '/^# *fi_FI.UTF-8 UTF-8/s/^# *//' /etc/locale.gen \
 && locale-gen en_US.UTF-8 fi_FI.UTF-8 \
 && pecl install xdebug \
 && docker-php-ext-enable xdebug \
 && docker-php-ext-configure gd --with-jpeg \
 && docker-php-ext-install mysqli gettext gd mbstring \
 && printf "ServerName localhost\n" > /etc/apache2/conf-available/servername.conf \
 && a2enconf servername \
 && rm -rf /var/lib/apt/lists/*

ENV LANG=en_US.UTF-8
ENV LC_ALL=en_US.UTF-8
