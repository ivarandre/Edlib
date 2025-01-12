FROM php:7.4-fpm-alpine AS php_base

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/

RUN set -eux; \
    apk add --no-cache bash sudo rclone git unzip; \
    install-php-extensions \
        bcmath \
        gettext \
        igbinary \
        intl \
        memcached \
        mysqli \
        oauth \
        opcache \
        pcntl \
        pdo_mysql \
        redis \
        sockets \
        xmlrpc \
        zip \
    ;

COPY composer.json composer.lock ./

RUN composer install \
    --no-autoloader \
    --no-dev \
    --no-scripts

COPY . .
COPY docker/php.ini $PHP_INI_DIR/conf.d/99-custom.ini
RUN echo "access.log = /dev/null" >> /usr/local/etc/php-fpm.d/www.conf

RUN set -eux; \
    mkdir -v -p \
        storage/app/public \
        storage/app/storage \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
    ; \
    composer install \
        --no-dev \
    ; \
    cp -R /app/vendor/h5p/h5p-editor public/h5p-editor-php-library; \
    cp -R /app/vendor/h5p/h5p-core public/h5p-php-library; \
    chown -R www-data:www-data .


FROM php_base as test
COPY docker/run-phpunit.sh /run-phpunit.sh
RUN composer install
CMD /run-phpunit.sh

FROM node:16-alpine AS jsbuild
WORKDIR /app
RUN apk add --no-cache build-base python2
RUN npm i -g npm node-gyp
COPY package.json package-lock.json ./
RUN npm i
COPY webpack.mix.js ./
COPY --from=php_base /app/vendor/ckeditor/ckeditor ./vendor/ckeditor/ckeditor
COPY --from=php_base /app/vendor/h5p ./vendor/h5p
COPY --from=php_base /app/resources ./resources
COPY --from=php_base /app/public ./public
RUN npm run production
RUN rm -rf node_modules


FROM php_base AS buildresult

COPY --from=jsbuild /app/public /app/public

RUN chown -R www-data:www-data /app/public
# Create symlink from app to shared storage
RUN set -eux; \
    ln -s /storage/h5plibs public/h5pstorage; \
    ln -s /storage/storage/app storage/app; \
    ln -s /storage/storage/logs storage/logs; \
    ln -s /storage/storage/framework storage/framework;


FROM buildresult AS cron
COPY docker/laravel.schedule /laravel.schedule
RUN set -eux; crontab /laravel.schedule; rm /laravel.schedule; crontab -l
CMD [ "bash", "-c", "set -eux; crond -f" ]


FROM buildresult AS deploy
CMD [ "php", "/app/artisan", "migrate", "--force" ]


FROM buildresult AS init
COPY docker/run-init.sh /init.sh
RUN chmod +x /init.sh
CMD /init.sh


FROM buildresult AS horizonqueue
CMD [ "php", "/app/artisan", "horizon" ]


FROM buildresult AS phpfpm
CMD [ "php-fpm", "-R", "-F", "-O" ]


FROM buildresult AS phpfpm-dev
RUN set -eux; \
    install-php-extensions \
        xdebug \
    ;
COPY docker/php/docker-entrypoint-dev.sh /docker-entrypoint-dev.sh
ENTRYPOINT ["/docker-entrypoint-dev.sh"]
CMD [ "php-fpm", "-R", "-F", "-O" ]


FROM nginx:1.19-alpine AS app
ENV PHP_FPM_HOST "localhost:9000"
COPY --from=buildresult /app/public /app/public
RUN apk add --no-cache bash
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/contentAuthor.conf.template /etc/nginx/templates/contentAuthor.conf.template
CMD ["nginx"]
