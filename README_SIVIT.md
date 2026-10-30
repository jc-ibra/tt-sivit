# SIVIT · CodeIgniter 4 + MySQL

Mismo sistema de viáticos (formulario, concentrado, indicadores, perfiles, catálogo, correo), pero con los datos en **MySQL**
y las reglas, permisos y contraseñas **en el servidor**. Reemplaza al HTML único (`SIVIT_1_05.html`), a IndexedDB y a la carpeta compartida `SIVIT_datos`.

## Requisitos
PHP 8.1+ (extensiones `intl`, `mbstring`, `mysqli`, `gd`, `zip`, `xml`), MySQL 8 / MariaDB 10.6+, Composer.

## Con Docker (lo más rápido)
```bash
docker compose up -d --build        # MySQL 8.4 + PHP 8.3/Apache; migra y crea el Operador solo
docker compose exec app php spark sivit:importar --carpeta /import/SIVIT_datos --excel /import/SIVIT_DATA.xlsx
open http://localhost:8088          # clave y datos de BD de desarrollo en docker/app.env
docker compose down                 # detiene (los datos quedan en el volumen sivit_mysql); `down -v` borra la BD
```
Pruebas contra MySQL: `docker compose exec -T db mysql -uroot -proot_dev_pass -e "CREATE DATABASE IF NOT EXISTS sivit_test; GRANT ALL ON sivit_test.* TO 'sivit'@'%';"` y luego
`docker run --rm --network sivit-ci_default --entrypoint php -v "$PWD":/app -v "$PWD/docker/app.env":/app/.env:ro -w /app sivit-ci-app vendor/bin/phpunit tests/app`.
Las claves de `docker/app.env` y `docker-compose.yml` son **solo de desarrollo**: cámbialas para cualquier servidor real.

**phpMyAdmin** (ver, importar y exportar la base): http://localhost:8090 · usuario `sivit` / clave `sivit_dev_pass` (o `root` / `root_dev_pass`).
Solo escucha en tu computadora (127.0.0.1). Exportar: base `sivit` → pestaña *Exportar*. En un servidor real no lo expongas a internet.

## Instalar sin terminal (hosting compartido): `/setup.php`
1. Sube el proyecto, apunta el dominio a `public/` y crea el `.env` (BD) con una línea extra: `sivit.setupToken = UNA-CLAVE-LARGA-Y-SECRETA`.
2. Abre `https://tu-dominio/setup.php`: verifica requisitos y conexión, pide el token y corre todas las migraciones + crea el Operador Virtual.
3. Al terminar se bloquea solo (`writable/setup.lock`). **Quita `sivit.setupToken` del `.env`.** Para volver a usarlo tras subir migraciones nuevas, borra ese archivo.
Sin token en el `.env` el instalador está desactivado; el token se compara en tiempo constante y se limita a 5 intentos por minuto.
(Los datos viejos de `SIVIT_datos` se importan con `spark sivit:importar`, que sí requiere terminal; sin ella, sube el catálogo y da de alta perfiles desde la propia app.)

## Instalación (sin Docker)
```bash
composer install --no-dev -o
cp .env.example .env            # edita baseURL y los datos de la BD; en producción CI_ENVIRONMENT = production
mysql -uroot -p -e "CREATE DATABASE sivit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                    CREATE USER 'sivit'@'localhost' IDENTIFIED BY 'UNA-CLAVE-LARGA'; GRANT ALL ON sivit.* TO 'sivit'@'localhost';"
php spark migrate               # crea tablas (usuarios, empleados, centros_costo, solicitudes, bitacora, ci_sessions, …)
php spark db:seed OperadorSeeder
```
Apunta el servidor web (Apache/Nginx) a la carpeta **`public/`**. Para probar: `php spark serve`.
La primera persona que abra SIVIT da clic en **Operador Virtual** y crea la contraseña del administrador (igual que antes).

## Pasar los datos actuales
```bash
php spark sivit:importar --carpeta ../SIVIT_datos --excel ../SIVIT_DATA.xlsx
php spark sivit:importar --respaldo ../SIVIT_respaldo_2026-10-29.json      # respaldos .json (varios separados por coma)
```
Es **idempotente** (se puede repetir sin duplicar): upsert por id original, nómina o cliente; si un registro llega de varias fuentes gana el editado más recientemente;
las marcas de borrado de la carpeta se omiten. Las contraseñas viejas (SHA-256 + sal) siguen funcionando y **se convierten a `password_hash` al primer inicio de sesión**.
Las rutas relativas se toman desde la raíz del proyecto.

## Qué cambió respecto al HTML
| Antes | Ahora |
|---|---|
| IndexedDB + carpeta compartida + sincronización | MySQL (única fuente de verdad), sin sincronizar |
| Contraseña verificada en el navegador | `password_hash` + sesión en BD (`ci_sessions`), 5 intentos/minuto por IP y perfil |
| Filtrar por rol en JavaScript | Filtrado en el servidor; un id ajeno responde 404 |
| Cálculo de total/OK en el navegador | El servidor recalcula y valida todo (`app/Libraries/SolicitudReglas.php`); el navegador solo muestra una vista previa |
| "Datos y respaldo" | Operador: **Descargar respaldo** (mismo formato .json, sin contraseñas) + `sivit:importar` |
| Correo destino por navegador | Guardado por usuario (`usuarios.mail_destino`) |
| Eliminar = borrar | Borrado lógico (`deleted_at`) + `bitacora` con quién/qué/cuándo |

Reglas, textos de error, listas (conceptos, justificaciones, colores) y las pantallas se conservan; las listas están en `app/Config/Sivit.php`.

## Seguridad incluida
CSRF global (token por sesión), `secureheaders` y CSP, `invalidchars`, rutas sin auto-routing, filtros `auth` y `rol:operador`,
consultas con Query Builder, `esc()` en vistas, validación de MIME/tamaño en fotos (≤5 MB) y catálogo (≤10 MB), fotos reprocesadas con GD a 160×160,
transacciones en catálogo e importación, bitácora sin contraseñas ni fotos.
Pendiente de tu lado: HTTPS (`app.forceGlobalSecureRequests = true`), usuario de BD con permisos mínimos y respaldos programados (`mysqldump`).

## Pruebas
`php vendor/bin/phpunit tests/app` (20 pruebas: reglas y mensajes, permisos por rol, depósito, enviado, catálogo completo/parcial, CSV, importación de hash viejo).
Las pruebas usan la BD `tests` del `.env`. Sin MySQL a la mano puedes usar SQLite: copia `.env.sqlite.example` a `.env`, y ajusta las rutas.
(`DatabaseHandler` de sesiones solo existe para MySQL/Postgres: con SQLite se usa el de archivos, ya configurado en ese ejemplo.)
