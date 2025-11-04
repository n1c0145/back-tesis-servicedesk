# Usamos PHP 8.2 con FPM
FROM php:8.2-fpm

# Instalar dependencias del sistema y extensiones PHP necesarias
RUN apt-get update && apt-get install -y \
    git zip unzip libpq-dev libonig-dev \
    && docker-php-ext-install pdo_pgsql mbstring opcache

# Habilitar OPcache
RUN docker-php-ext-enable opcache

# Copiar configuración personalizada de OPcache
COPY ./docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Establecer directorio de trabajo
WORKDIR /var/www/html

# Copiar archivos del proyecto al contenedor
COPY . .

# Dar permisos de almacenamiento
RUN chown -R www-data:www-data storage bootstrap/cache
