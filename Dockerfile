FROM alpine:3.6

ENV TIMEZONE "Asia/Singapore"
ENV COMPOSER_ALLOW_SUPERUSER 1
ENV SYMFONY_LOG "php://stderr"

# Copy configuration files to root
COPY app.php /srv/
COPY composer.json /srv/

RUN apk add --update \
    tzdata\
    ca-certificates \
    openssl \
    curl \
    php7-intl \
    php7-openssl \
    php7-pdo_mysql \
    php7-xsl \
    php7-pspell \
    php7-snmp \
    php7-mbstring \
    php7-xmlreader \
    php7-opcache \
    php7-posix \
    php7-session \
    php7-gd \
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

    # Symlink
    && ln -sf /usr/sbin/php-fpm7 /usr/bin/php-fpm \
    && ln -sf /usr/bin/php7 /usr/bin/php \

    # Setting timezone
    && apk add tzdata \
    && cp /usr/share/zoneinfo/"${TIMEZONE}" /etc/localtime \
    && echo ${TIMEZONE} >  /etc/timezone \

    # Ensure ldap is configure with valid ca cert
    &&  echo 'TLS_CACERT /etc/ssl/certs/ca-certificates.crt' >> /etc/openldap/ldap.conf \

    # Install: Composer
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer --version \
    && composer global require hirak/prestissimo


    # Install S6
RUN wget -q https://github.com/just-containers/s6-overlay/releases/download/v${S6VERSION}/s6-overlay-amd64.tar.gz --no-check-certificate -O /tmp/s6-overlay.tar.gz \
    && tar xfz /tmp/s6-overlay.tar.gz -C / \
    && rm -f /tmp/s6-overlay.tar.gz \

    # Cleanup

    && apk del wget \
    && rm -rf /var/cache/apk/* \
    && rm -rf /tmp/* \

    # Fix permissions
    && rm -r /var/www/localhost \
    && chown -Rf nginx:www-data /var/www/

# Set working directory
WORKDIR /srv

ENTRYPOINT [ "/srv/" ]
