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

# ドキュメントルートを CakePHP の webroot に向ける（http://localhost:<WEB_PORT>/ で直接表示）
ENV APACHE_DOCUMENT_ROOT /var/www/html/product_thing/webroot
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
  && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY cake-override.conf /etc/apache2/conf-available/cake-override.conf
RUN a2enconf cake-override
