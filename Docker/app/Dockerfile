# Ultiorganizer web server image

# This ancient PHP must be used as long as we use msqli to connect to the DB
FROM php:5-apache

# Debian stretch is already archived. Changing to archive repos
RUN echo "deb http://archive.debian.org/debian stretch main\ndeb http://archive.debian.org/debian-security stretch/updates main" > /etc/apt/sources.list

RUN apt-get --assume-yes update && apt-get --assume-yes install zlib1g-dev libpng-dev sendmail

RUN docker-php-ext-install mysqli gettext gd mbstring

CMD ["apache2-foreground"]
