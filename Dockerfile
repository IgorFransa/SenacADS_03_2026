FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite

COPY . /var/www/html/
COPY docker/frontend-backend.conf /etc/apache2/conf-available/frontend-backend.conf

RUN sed -i 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/frontend#' /etc/apache2/sites-available/000-default.conf \
    && a2enconf frontend-backend \
    && sed -i 's/\r$//' /var/www/html/docker/entrypoint.sh \
    && chmod +x /var/www/html/docker/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
