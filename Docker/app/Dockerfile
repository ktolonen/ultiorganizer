# Ultiorganiser can't upgrade to newer PHP prior to switching to a newer MySQL driver
FROM php:5-apache

# Install dependencies
RUN apt-get --assume-yes update && apt-get --assume-yes install zlib1g-dev libpng-dev sendmail

# Install PHP extensions
RUN docker-php-ext-install mysqli gettext gd mbstring

# Start the application
CMD ["apache2-foreground"]
