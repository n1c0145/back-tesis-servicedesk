# Backend ServiceDesk

Servidor para ServiceDesk, desarrollado con Laravel 11 y conectada a base de datos PostgreSQL.

## Requisitos Previos

Tener instalado lo siguiente:

- [Laragon](https://laragon.org/) (versión full o portable)
- [PostgreSQL BIN](https://www.enterprisedb.com/download-postgresql-binaries) (se añade manualmente a Laragon)
- DBeaver o PgAdmin para gestionar la base de datos.
- PHP >= 8.1 (incluido en Laragon)
- Composer (incluido en Laragon)

## 1. Configurar PostgreSQL en Laragon

Extraer el binario de PostgreSQL en en directorio bin de laragon `C:\laragon\bin`

## 2. Reiniciar Laragon y verificar que PostgreSQL aparece en el panel.

## 3. Habilitar extensiones de PostgreSQL `pdo_pgsql` y `pgsql`

## 4. Clonar el repositorio

`git clone https://github.com/n1c0145/back-tesis-servicedesk.git`

## 5. Crear base de datos PostgreSQL

Desde DBeaver o PgAdmin, crear una nueva base de datos llamada `tesis_servicedesk`

## 6. Configurar el archivo .env

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=tesis_servicedesk
DB_USERNAME=postgres
DB_PASSWORD=tucontraseña

## 7. Instalar dependencias

Dentro del directorio del proyecto, ejecutar `composer install`

### Dependencias Utilizadas:

`aws-sdk SDK` oficial para PHP que permite trabajar con servicios como S3 y Cognito.

`firebase/php-jwt` Librería para decodificar y validar tokens JWT generados por AWS Cognito.

## 8. Generar la clave de la aplicación (APP_KEY)

Ejecutar el comando `php artisan key:generate`

## 9. Ejecutar migraciones

Ejecutar el comando `php artisan migrate`

## 10. Levantar el servidor de desarrollo

Ejecutar el comando `php artisan serve`

## Diagrama de Arquitectura

El siguiente diagrama muestra cómo se estructura la arquitectura del backend de Laravel.

![Diagrama de Arquitectura del Backend](./readme-assets/arquitectura-tesis-backend.png)