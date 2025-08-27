# Usamos PHP 8.2 con FPM
FROM php:8.2-fpm

# Instalar dependencias de sistema
RUN apt-get update && apt-get install -y \
    git zip unzip libpq-dev libonig-dev \
    && docker-php-ext-install pdo_pgsql mbstring

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Establecer directorio de trabajo
WORKDIR /var/www/html

# Copiar archivos del proyecto al contenedor
COPY . .

# Dar permisos de almacenamiento
RUN chown -R www-data:www-data storage bootstrap/cache
