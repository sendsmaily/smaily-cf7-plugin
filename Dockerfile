FROM wordpress:5.3.2-php7.3-apache

# Install Transliterator
RUN apt-get update && apt-get install -y unzip wget zlib1g-dev libicu-dev g++ \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl
RUN mkdir /usr/src/wordpress/wp-content/plugins/contact-form-7 \
  && wget https://downloads.wordpress.org/plugin/contact-form-7.5.1.7.zip \
  && unzip contact-form-7.5.1.7.zip \
  && mv contact-form-7/* /usr/src/wordpress/wp-content/plugins/contact-form-7 \
  && rm contact-form-7.5.1.7.zip
RUN mkdir /usr/src/wordpress/wp-content/plugins/really-simple-captcha \
  && wget https://downloads.wordpress.org/plugin/really-simple-captcha.zip \
  && unzip really-simple-captcha.zip \
  && mv really-simple-captcha/* /usr/src/wordpress/wp-content/plugins/really-simple-captcha \
  && rm really-simple-captcha.zip
