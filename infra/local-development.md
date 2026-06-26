# Local Development

## Requirements

- Docker Desktop or compatible Docker runtime.
- Git.
- Composer and PHP locally are helpful, but Docker can run Composer inside the platform container.

This project is staging-safe by default. Local app startup should work after dependencies are installed and the database is migrated.

The repository includes a root `.gitignore`, executable scripts in `scripts/`, and a committed Laravel dependency lockfile at `apps/platform/composer.lock`.

## Build Images

```bash
docker compose build
```

## Install Dependencies

Run Composer inside Docker:

```bash
docker compose run --rm platform composer install
```

## Configure Laravel

Copy the environment file:

```bash
docker compose run --rm platform cp .env.example .env
```

Generate the app key:

```bash
docker compose run --rm platform php artisan key:generate
```

Run migrations:

```bash
docker compose run --rm platform php artisan migrate
```

Create the first admin user:

```bash
docker compose run --rm platform php artisan omnibridge:create-admin
```

The command also provisions default WooCommerce and Front webhook endpoint path tokens for the first organization.

## Open Dashboard

Start the platform app:

```bash
docker compose up
```

```text
http://localhost:8000/dashboard
```

Phase 1 includes basic login, organization editing, connection setup, encrypted credential storage, and safe connection test actions. Phase 2 adds read-only WooCommerce and Front Systems connection test clients.

Live HTTP checks remain disabled by default. Enable them only with staging credentials:

```text
OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=true
```

Read-only connection probes:

- WooCommerce: `GET /wp-json/wc/v3/system_status`
- Front Systems: `GET /api/Environment`

## Run Tests

```bash
docker compose run --rm platform php artisan test
```

With local PHP and Composer dependencies installed, you can run:

```bash
cd apps/platform
php artisan test
```

## Verification commands

Quick scaffold verification:

```bash
./scripts/verify-platform-scaffold.sh
```

Docker verification:

```bash
docker compose build
docker compose run --rm platform composer install
docker compose run --rm platform cp .env.example .env
docker compose run --rm platform php artisan key:generate
docker compose run --rm platform php artisan migrate
docker compose run --rm platform php artisan test
./scripts/generate-front-client.sh
```

If Docker is not installed, Docker verification is blocked until Docker Desktop or another Docker Compose-compatible runtime is installed.

GitHub Actions runs the platform CI workflow on pull requests and pushes to `main`.

## What Is Still Placeholder

- Product, price, stock, order, refund, gift card, and omnichannel sync are not implemented yet.
- Front and WooCommerce API clients are read-only connection test clients only.
- Connection tests are read-only and live HTTP checks are disabled by default.
- No real API writes exist yet.

## Stop Services

```bash
docker compose down
```

## Local Safety

- Use staging credentials only.
- Keep `OMNIBRIDGE_ALLOW_PRODUCTION_WRITES=false`.
- Keep `OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=false` unless you intentionally want read-only base URL reachability checks.
- Do not paste real credentials into docs, issues, commits, or chat.
