FROM wordpress:5.9-php7.3-apache

ENV CF7_VERSION="5.5.4"
ENV RSC_VERSION="2.1"

# Install required packages.
RUN apt-get update \
    && apt-get install -y unzip wget zlib1g-dev libicu-dev g++ \
    && rm -rf /var/lib/apt/lists/*

# Compile and install PHP transliterator.
RUN docker-php-ext-configure intl \
    && docker-php-ext-install intl

# Install Contact Form 7.
RUN wget -O /tmp/cf7.zip "https://downloads.wordpress.org/plugin/contact-form-7.${CF7_VERSION}.zip" \
    && unzip /tmp/cf7.zip -d /usr/src/wordpress/wp-content/plugins \
    && rm /tmp/cf7.zip

# Install Really Simple CAPTCHA.
RUN wget -O /tmp/rsc.zip "https://downloads.wordpress.org/plugin/really-simple-captcha.${RSC_VERSION}.zip" \
    && unzip /tmp/rsc.zip -d  /usr/src/wordpress/wp-content/plugins \
    && rm /tmp/rsc.zip
