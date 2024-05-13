FROM php:8.2-fpm-alpine

RUN set -ex \
  && apk --no-cache add \
    postgresql-dev

RUN apk add --no-cache autoconf bash nano freetype libpng libjpeg-turbo freetype-dev libzip-dev libpng-dev libjpeg-turbo-dev musl-dev g++ && \
  docker-php-ext-configure gd \
    --with-freetype=/usr/include/ \
    --with-jpeg=/usr/include/ && \
  pecl install apcu && \
  docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql && \
  docker-php-ext-install -j$(nproc) zip opcache intl pdo_pgsql pgsql pcntl && \
  docker-php-ext-enable apcu pdo_pgsql sodium && \
  echo "date.timezone=Europe/Moscow" >> $PHP_INI_DIR/php.ini && \
  NPROC=$(grep -c ^processor /proc/cpuinfo 2>/dev/null || 1) && \
  docker-php-ext-install -j${NPROC} gd && \
  apk del --no-cache freetype-dev libpng-dev libjpeg-turbo-dev

COPY --from=composer /usr/bin/composer /usr/bin/composer

ARG USER_GID=1000
ARG USER_UID=1000
RUN addgroup -g ${USER_GID} app \
  && adduser -D -s /bin/bash -G app -u ${USER_UID} app \
  && chown -R app:app /opt \
  && echo "alias ll='ls -lah'" > /etc/profile.d/aliases.sh \
  && echo "alias ..='cd ..'" >> /etc/profile.d/aliases.sh

RUN apk add --no-cache nano sudo make build-base bash git \
  && chown -R app:app /mnt \
  && echo 'app ALL=NOPASSWD: ALL' > /etc/sudoers.d/app \
  && chmod 0440 /etc/sudoers.d/app \
  && visudo --check

ENV PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/srv/bin:/opt/

WORKDIR /api
USER app
COPY . .