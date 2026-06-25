# Local Development

## Requirements

- Docker Desktop or compatible Docker runtime.
- Git.
- Composer and PHP locally are helpful, but the initial scaffold can be inspected without them.

## Start Services

```bash
docker compose up -d
```

Services:

- Platform placeholder: http://localhost:8000
- PostgreSQL: localhost:5432
- Redis: localhost:6379

## Stop Services

```bash
docker compose down
```

## Laravel Installation

When ready to install Laravel:

```bash
cd apps/platform
composer create-project laravel/laravel .
cp .env.example .env
php artisan key:generate
php artisan migrate
```

The current `docker-compose.yml` is a placeholder. After Laravel is installed, replace the PHP CLI image with a proper application image or Dockerfile.

## Local Safety

- Use staging credentials only.
- Keep `OMNIBRIDGE_ALLOW_PRODUCTION_WRITES=false`.
- Do not paste real credentials into docs, issues, commits, or chat.

