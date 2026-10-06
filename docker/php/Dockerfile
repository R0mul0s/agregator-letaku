# Agregátor letáků — PHP 8.4 FPM image pro vývoj
#
# PHP 8.4 jako ostatní projekty (produkce zatím neurčena, O1 v docs/PLAN.md).
# Součástí je i Node.js — npm se pouští v tomto kontejneru, lokálně se nic neinstaluje.
#
# @author Roman Hlaváček
# @created 2026-10-02

FROM php:8.4-fpm-alpine

# poppler-utils: pdftotext pro text s polohou z PDF letáků (R86), na hostingu je taky
RUN apk add --no-cache \
        poppler-utils \
        git \
        unzip \
        curl \
        nodejs \
        npm \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        autoconf \
        g++ \
        make \
        linux-headers \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        intl \
        bcmath \
        zip \
        opcache \
        pcntl \
    && apk del --no-cache autoconf g++ make linux-headers \
    && rm -rf /tmp/* /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# OPcache: revalidate_freq=2 — na Windows bind mountu je stat souborů pomalý,
# ale změny v PHP kódu se mají projevit prakticky hned.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=1'; \
        echo 'opcache.revalidate_freq=2'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.memory_consumption=256'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'realpath_cache_size=4096k'; \
        echo 'realpath_cache_ttl=600'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

RUN { \
        echo 'memory_limit=512M'; \
        echo 'max_execution_time=300'; \
        echo 'date.timezone=UTC'; \
    } > /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
