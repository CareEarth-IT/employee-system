# syntax=docker/dockerfile:1

# --- Frontend assets (Vite) ---
FROM node:22-bookworm-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build

# --- PHP dependencies ---
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative

# --- WordPress + Cocoon ---
FROM debian:bookworm-slim AS wordpress

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl unzip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /tmp

RUN curl -fsSL https://wordpress.org/latest.tar.gz -o wordpress.tar.gz \
    && tar -xzf wordpress.tar.gz \
    && curl -fsSL https://github.com/yhira/cocoon/archive/refs/heads/master.zip -o cocoon.zip \
    && curl -fsSL https://github.com/yhira/cocoon-child/archive/refs/heads/master.zip -o cocoon-child.zip \
    && unzip -q cocoon.zip \
    && unzip -q cocoon-child.zip \
    && mv cocoon-master wordpress/wp-content/themes/cocoon-master \
    && mv cocoon-child-master wordpress/wp-content/themes/cocoon-child \
    && curl -fsSL https://downloads.wordpress.org/plugin/wp-stateless.latest-stable.zip -o wp-stateless.zip \
    && unzip -q wp-stateless.zip -d wordpress/wp-content/plugins \
    && curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o wp-cli.phar \
    && chmod +x wp-cli.phar

# --- Runtime (Cloud Run) ---
FROM php:8.3-fpm-bookworm AS runtime

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx \
        gettext-base \
        openssl \
        libzip-dev \
        libonig-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mysqli \
        gd \
        mbstring \
        zip \
        bcmath \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html
COPY --from=frontend /app/public/build /var/www/html/public/build
COPY --from=wordpress /tmp/wordpress /var/www/html/wordpress
COPY --from=wordpress /tmp/wp-cli.phar /usr/local/bin/wp

COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY docker/start.sh /usr/local/bin/start.sh
COPY docker/wordpress-bootstrap.sh /usr/local/bin/wordpress-bootstrap.sh
COPY docker/wordpress/wp-config-extra.php /var/www/html/wordpress/wp-config-extra.php
COPY docker/wordpress/apply-wp-stateless-adc.php /tmp/apply-wp-stateless-adc.php
COPY docker/wordpress/mu-plugins/ /var/www/html/wordpress/wp-content/mu-plugins/

RUN php /tmp/apply-wp-stateless-adc.php \
      /var/www/html/wordpress/wp-content/plugins/wp-stateless/lib/classes/class-gs-client.php \
      /var/www/html/wordpress/wp-content/plugins/wp-stateless/lib/classes/class-bootstrap.php \
    && rm -f /tmp/apply-wp-stateless-adc.php \
    && sed -i 's/\r$//' /usr/local/bin/start.sh /usr/local/bin/wordpress-bootstrap.sh \
    && chmod +x /usr/local/bin/start.sh /usr/local/bin/wordpress-bootstrap.sh /usr/local/bin/wp \
    && ln -sfn /var/www/html/wordpress /var/www/html/public/wordpress \
    && ln -sfn /var/www/html/apps/finance-hr /var/www/html/public/finance-hr \
    && mkdir -p /var/www/html/wordpress/wp-content/uploads \
    && mkdir -p /var/www/html/apps/finance-hr/uploads \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/wordpress/wp-content /var/www/html/apps/finance-hr/uploads

ENV PORT=8080
ENV APP_ENV=production

EXPOSE 8080

CMD ["/usr/local/bin/start.sh"]
