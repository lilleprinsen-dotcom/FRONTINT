# Local Development

## Requirements

- Docker Desktop or compatible Docker runtime.
- Git.
- Composer and PHP locally are helpful, but Docker can run Composer inside the platform container.

This project is still scaffold-first. Local app startup is expected to work after dependencies are installed, but Docker build, Composer install, migrations, and tests must be verified before real integration development.

## Start Services

```bash
docker compose up -d --build postgres redis
```

Services:

- PostgreSQL: localhost:5432
- Redis: localhost:6379

The platform app starts after dependencies and `.env` are ready.

## Install Dependencies

Run Composer inside Docker:

```bash
docker compose run --rm platform composer install
```

## Configure Laravel

Copy the environment file:

```bash
cp apps/platform/.env.example apps/platform/.env
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

## Open Dashboard

Start the platform app:

```bash
docker compose up -d platform
```

```text
http://localhost:8000/dashboard
```

The dashboard is intentionally minimal until authentication and setup wizard work is completed.

## Stop Services

```bash
docker compose down
```

## Local Safety

- Use staging credentials only.
- Keep `OMNIBRIDGE_ALLOW_PRODUCTION_WRITES=false`.
- Do not paste real credentials into docs, issues, commits, or chat.
