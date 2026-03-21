FROM php:8.2-apache

# Habilita mod_rewrite
RUN a2enmod rewrite

# Copia todos os arquivos para o servidor
COPY . /var/www/html/

# Permissões
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80