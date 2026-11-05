# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

SIVIT is a travel-expense (viáticos) request system for Trantor Technologies, ported from a single-file HTML app (`SIVIT_1_05.html`, IndexedDB + shared folder `SIVIT_datos`) to **CodeIgniter 4 + MySQL**. The port's goal is behavioral parity: same screens, rules, error texts and lists — but data, permissions, validation and passwords now live on the server. Code, identifiers, UI text, comments and commit messages are in **Spanish**; keep it that way. `README_SIVIT.md` is the real project README (`README.md` is the CI4 starter boilerplate).

## Commands

```bash
# Docker (MySQL 8.4 + PHP 8.3/Apache on :8088, phpMyAdmin on 127.0.0.1:8090)
docker compose up -d --build     # entrypoint runs `spark migrate` + OperadorSeeder automatically
docker compose exec app php spark sivit:importar --carpeta /import/SIVIT_datos --excel /import/SIVIT_DATA.xlsx

# Local (XAMPP / php spark serve)
composer install
php spark migrate
php spark db:seed OperadorSeeder
php spark serve

# Tests (app suite; uses the `tests` DB group from .env)
php vendor/bin/phpunit tests/app
php vendor/bin/phpunit tests/app --filter testLoginMigraHashViejoSha256   # single test
```

Tests need a database: either MySQL (create `sivit_test`, see README_SIVIT.md for the Docker-network phpunit command) or SQLite by copying `.env.sqlite.example` to `.env` (that example also switches sessions to the file handler, since `DatabaseHandler` doesn't support SQLite). No linter is configured.

## Architecture

- **Routing**: auto-routing is off; every route is explicit in `app/Config/Routes.php`. Access control is path-based in `app/Config/Filters.php` (`$filters`): `auth` (logged in) and `rol:operador` (operator only). Adding a new route usually means adding its path to those filter lists too. CSRF (session token), `secureheaders`/CSP and `invalidchars` are global.
- **Roles** (`operador`, `coordinador`, `viatico`): row-level visibility is enforced in `SolicitudModel` — `veTodo()`, `puedeBorrar()`, `visiblesPara($u)` and `visible($u, $id)`. Controllers must load solicitudes through these so a coordinator only sees their own; an id the user can't see returns 404, not 403.
- **Auth** (`app/Libraries/Auth.php`): the user is reloaded from the DB on every request (role/deletion changes apply immediately). Sessions are stored in DB (`ci_sessions`). Legacy SHA-256+salt hashes (`legacy_hash`/`legacy_salt`) are still accepted and upgraded to `password_hash` on first login. The first visitor creates the "Operador Virtual" password (`Auth::crearOperador`).
- **Business rules** (`app/Libraries/SolicitudReglas.php`): the server recomputes totals and the `ok` flag and validates everything; `public/assets/sivit.js` only renders a preview. Rule changes belong here, not in JS. Business lists/constants (money fields, concepts, taxi justifications, theme colors, roles, default email) are in `app/Config/Sivit.php`.
- **Catalog import** (`app/Libraries/CatalogoImport.php`, PhpSpreadsheet): upload → `leerTablas`/`detectar` → `plan` → `comparar` (preview at `catalogo/previa`) → `aplicar` in a transaction (full or partial mode). Employees (`empleados`, keyed by nómina) and cost centers (`centros_costo`, keyed by cliente).
- **Legacy data import**: `app/Commands/SivitImportar.php` (`spark sivit:importar`) ingests `SIVIT_datos`, `SIVIT_DATA.xlsx` and `.json` backups. It must stay **idempotent** (upsert by original id / nómina / cliente; most recently edited wins; tombstones skipped). `Respaldo::descargar` exports the same JSON format (without passwords).
- **Email** (`app/Libraries/CorreoSolicitudes.php`, `Solicitudes::enviarCorreo`): the server sends the request table via SMTP configured in `.env` (`email.*`, see `.env.example`) and only marks requests as sent if the send succeeded. Without SMTP config it answers 503 `sinConfigurar` and `sivit.js` falls back to the old `mailto:` + clipboard flow. Tests use CI4's `MockEmail`.
- **Soft deletes + audit**: deletes set `deleted_at`; meaningful actions go to `bitacora` via `BitacoraModel::registrar()` (never log passwords or photos).
- **Views**: server-rendered PHP in `app/Views/` with `layout.php` + `partials/`; formatting helpers (`dinero`, `dmy`, `avatar_html`, table column/cell helpers) are in `app/Helpers/sivit_helper.php`. Always `esc()` output.
- **Web installer**: `/setup.php` (`Controllers/Setup.php`) runs migrations on shared hosting; enabled only when `sivit.setupToken` is set in `.env`, locks itself via `writable/setup.lock`.

## Tests

`tests/app/` holds the real suite (`ReglasTest` for rule validation/messages, `FlujoTest` for feature flows: permissions by role, deposit, sent marking, catalog, CSV, legacy hash). Both extend `tests/_support/SivitTestCase.php`, which refreshes the DB and seeds four users (`$this->u['op'|'c1'|'c2'|'vi']`, password `clave1234`), two employees and two cost centers. CSRF is disabled under `CIUnitTestCase`.

## Notes

- Credentials in `docker/app.env` and `docker-compose.yml` are development-only.
- `docker-compose.yml` mounts `../SIVIT_datos` and `../SIVIT_DATA.xlsx` from the parent directory for imports.
