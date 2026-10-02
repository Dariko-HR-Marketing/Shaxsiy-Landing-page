FROM php:8.2-apache

# PHP va kerakli SQLite/GD kengaytmalarini o'rnatish
RUN apt-get update && apt-get install -y \
    sqlite3 libsqlite3-dev libpng-dev libjpeg-dev libfreetype6-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_sqlite mbstring gd \
    && a2enmod rewrite

# Fayllarni serverga nusxalash
COPY . /var/www/html/

# Fayllarni asosiy ildiz papkaga ko'chirish
RUN if [ -d "/var/www/html/public_html" ]; then cp -r /var/www/html/public_html/* /var/www/html/; fi

# storage papkasini yaratish va ruxsat berish
RUN mkdir -p /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 777 /var/www/html/storage

EXPOSE 80
CMD ["apache2-foreground"]
