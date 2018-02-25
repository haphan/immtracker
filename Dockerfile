FROM alpine:3.6

ENV TIMEZONE "Asia/Singapore"
ENV COMPOSER_ALLOW_SUPERUSER 1
ENV SYMFONY_LOG "php://stderr"

ARG REPO="immtracker"
ARG SHA1="master"

RUN apk add --update \
    tzdata\
    ca-certificates \
    openssl \
    curl \
    php7-intl \
    php7-openssl \
    php7-snmp \
    php7-mbstring \
    php7-opcache \
    php7-posix \
    php7-gettext \
    php7-json \
    php7-xml \
    php7-iconv \
    php7-sysvshm \
    php7-curl \
    php7-phar \
    php7-zip \
    php7-ctype \
    php7-mcrypt \
    php7-bcmath \
    php7-dom \
    php7-sockets \
    php7-soap \
    php7-zlib \
    php7-pdo \
    && ln -sf /usr/bin/php7 /usr/bin/php \

    # Setting timezone
    && cp /usr/share/zoneinfo/"${TIMEZONE}" /etc/localtime \
    && echo ${TIMEZONE} >  /etc/timezone \

    # Install: Composer
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer --version \
    && composer global require hirak/prestissimo \
    && curl -LSs "https://github.com/haphan/${REPO}/archive/${SHA1}.tar.gz" | tar xz -C / ${REPO}-${SHA1} \
    && rm -rf /srv \
    && mv /${REPO}-${SHA1} /srv \
    && cd /srv \
    && composer install --no-interaction -vvv \

    # Cleanup
    && apk del wget \
    && rm -rf /var/cache/apk/* \
    && rm -rf /tmp/*

# Set working directory
WORKDIR /srv

ENTRYPOINT [ "php", "/srv/app.php" ]
