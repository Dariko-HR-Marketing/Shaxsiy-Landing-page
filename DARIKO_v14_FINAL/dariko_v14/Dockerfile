FROM php:8.2-apache

# PHP kengaytmalari va mod_rewrite ni o'rnatish
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_sqlite mbstring gd \
    && a2enmod rewrite

# Fayllarni nusxalash (public_html bo'lsa ham, root bo'lsa ham)
COPY . /var/www/html/

# Root papkada bo'lsa public_html ga o'tish yoki to'g'ridan-to'g'ri ishlatish
RUN if [ -d "/var/www/html/public_html" ]; then cp -r /var/www/html/public_html/* /var/www/html/; fi

# storage papkasini yaratish va ruxsatlarni berish
RUN mkdir -p /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 777 /var/www/html/storage

EXPOSE 80
CMD ["apache2-foreground"]
