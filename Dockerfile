FROM wordpress:5.3.2-php7.3-apache

# Install Transliterator
RUN apt-get update && apt-get install -y zlib1g-dev libicu-dev g++ \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl
