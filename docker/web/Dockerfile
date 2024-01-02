FROM php:8.2-apache

RUN apt-get update \
  && apt-get install -y vim \
  # unzipコマンド(composer create-projectで必要)
  && apt-get install -y unzip \
  # PHPのintl拡張(CakePHPで必要)
  && apt-get install -y libicu-dev \
  && docker-php-ext-install intl \
  # PDO MySQL拡張
  && docker-php-ext-install pdo_mysql \
  # mod_rewrite有効化
  && a2enmod rewrite

COPY --from=composer /usr/bin/composer /usr/bin/composer
