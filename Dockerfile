FROM php:7.4-apache

RUN apt-get update &&\
    apt-get install --no-install-recommends --assume-yes --quiet ca-certificates curl git &&\
    rm -rf /var/lib/apt/lists/*

RUN pecl install xdebug-3.1.0 && docker-php-ext-enable xdebug
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli
#RUN echo 'zend_extension="/usr/local/lib/php/extensions/no-debug-non-zts-20220829/xdebug.so"' >> /usr/local/etc/php/php.ini
RUN echo 'zend_extension="/usr/local/lib/php/extensions/no-debug-non-zts-20190902/xdebug.so"' >> /usr/local/etc/php/php.ini
RUN echo 'xdebug.client_port=9000' >> /usr/local/etc/php/php.ini
RUN echo 'xdebug.mode=debug' >> /usr/local/etc/php/php.ini
RUN echo 'xdebug.discover_client_host=true' >> /usr/local/etc/php/php.ini
RUN echo 'xdebug.client_host=host.docker.internal' >> /usr/local/etc/php/php.ini
RUN echo 'extension=mysqli' >> /usr/local/etc/php/php.ini