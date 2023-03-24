FROM wordpress:6.1

# Install required packages.
RUN apt-get update \
    && apt-get install -y \
    g++ \
    libicu-dev \
    unzip \
    wget \
    zlib1g-dev \
    && rm -rf /var/lib/apt/lists/*

# Compile and install PHP transliterator.
RUN docker-php-ext-configure intl \
    && docker-php-ext-install intl

# Install Contact Form 7.
ENV CF7_VERSION="5.7.5.1"
RUN wget -O /tmp/cf7.zip "https://downloads.wordpress.org/plugin/contact-form-7.${CF7_VERSION}.zip" \
    && unzip /tmp/cf7.zip -d /usr/src/wordpress/wp-content/plugins \
    && rm /tmp/cf7.zip

# Install Really Simple CAPTCHA.
ENV RSC_VERSION="2.1"
RUN wget -O /tmp/rsc.zip "https://downloads.wordpress.org/plugin/really-simple-captcha.${RSC_VERSION}.zip" \
    && unzip /tmp/rsc.zip -d  /usr/src/wordpress/wp-content/plugins \
    && rm /tmp/rsc.zip
