<laravel-boost-guidelines>
# Laravel Application

## Cloud Agent environment

The React client lives in `client/` and talks to this API. Local development uses PostgreSQL 16 with PostGIS, not the SQLite default in `.env.example`: `ship_operations_perimeter.perimeter` is a `geography(Polygon, 4326)` column, so `php artisan migrate` fails on SQLite.

- PHP must be 8.4.1 or newer. `composer.json` allows `^8.3`, but the lockfile pulls Symfony 8 packages that require 8.4.1.
- API: `php artisan serve --host 0.0.0.0 --port 8000` from `api/`. Health check: `GET /api/health`.
- Client: `npm run dev -- --host 0.0.0.0 --port 5173` from `client/`, with `VITE_API_URL=http://127.0.0.1:8000/api`.
- Database `tracking` on `127.0.0.1:5432`, user `tracking`, password `tracking`. Enable the `postgis` extension before migrating.
- `php artisan test` uses in-memory SQLite and does not run the geography migration.
- The seeded system admin comes from `database/factories/UserFactory.php`.
- `VITE_MAPBOX_ACCESS_TOKEN` is only needed for the tracking map. Login and registry screens work without it.



This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>
