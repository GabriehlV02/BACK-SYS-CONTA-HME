# Sistema Contable HME · API

API del Sistema Contable HME, construida con Laravel. Gestiona autenticación, inventario, existencias, almacenes, adquisiciones, ventas y configuración para el [frontend contable](https://github.com/GabriehlV02/FRONT-SYS-CONTA-HME). También expone la integración autenticada utilizada por el Sistema Clínico HME.

[![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-base_de_datos-4169E1?logo=postgresql&logoColor=white)](https://www.postgresql.org/)

## Responsabilidades

- Autenticación y autorización basada en permisos.
- Catálogos de productos, insumos, servicios y profesionales.
- Inventario, stock por almacén, lotes y movimientos.
- Adquisiciones, ventas, caja y reportes operativos.
- Sincronización autenticada de clientes con el sistema clínico.

Las rutas se encuentran en `routes/api.php`; las reglas de dominio y persistencia se organizan bajo `app/Flows/` y `app/`.

## Requisitos

- PHP 8.3 o posterior y Composer.
- PostgreSQL compatible con la configuración del proyecto.
- Node.js y npm solo si vas a compilar recursos frontend empaquetados por Laravel.

## Instalación local

1. Crea una base PostgreSQL local y configura las variables `DB_*` de `.env`.
2. Instala las dependencias y prepara la aplicación:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --host 127.0.0.1 --port 5055
```

La API quedará disponible en `http://127.0.0.1:5055`, que es el destino esperado por el frontend contable en desarrollo. Ajusta `VITE_API_TARGET` si utilizas otro puerto.

Configura `CLINICO_API_URL` y `CLINICO_INTEGRACION_TOKEN` únicamente cuando habilites la integración clínica. Los tokens, claves y credenciales deben permanecer fuera del repositorio. En entornos publicados, configura `APP_DEBUG=false`.

## Comandos útiles

```powershell
php artisan route:list --path=api
php artisan migrate:status
php artisan test
```

## Estructura

```text
app/          # flujos de dominio, controladores, middleware y servicios
routes/       # rutas HTTP
database/     # migraciones, factories y seeders
tests/        # pruebas automatizadas
config/       # configuración de Laravel
```

## Seguridad

No compartas archivos `.env`, tokens de integración, credenciales de base de datos ni información de pacientes o transacciones reales. Revisa los permisos de cada cuenta antes de habilitar acceso a módulos operativos.
