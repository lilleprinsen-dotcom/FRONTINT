# OmniBridge Platform

This directory contains the Laravel integration platform foundation.

## Intended Stack

- Laravel 11 or newer
- PostgreSQL
- Redis or database queues
- Laravel Horizon later if Redis queue visibility becomes useful
- Blade or Filament for a minimal admin dashboard
- API-first controllers for webhooks, sync jobs, gift card adapter calls, and manual retry actions

## Current Foundation

The repository now includes:

- Tenant-aware models and migrations for organizations, connections, events, mappings, inventory, gift cards, sync runs, audit logs, and settings.
- Auth-ready `User` model and membership table.
- Encrypted credential storage model and service skeleton.
- Webhook receiver controllers for WooCommerce and Front.
- Event recorder and idempotency key helper.
- Queue job skeleton for deferred event processing.
- Minimal health, dashboard, sync, order, and gift card routes.
- PHPUnit tests for idempotency key behavior.

The app now has a local Dockerfile. Run Composer before serving the app:

```bash
docker compose run --rm platform composer install
```

## Local Setup

From the repository root:

```bash
docker compose up -d --build postgres redis
docker compose run --rm platform composer install
cp apps/platform/.env.example apps/platform/.env
docker compose run --rm platform php artisan key:generate
docker compose run --rm platform php artisan migrate
docker compose run --rm platform php artisan omnibridge:create-admin
docker compose up -d platform
```

Keep custom integration code in small, testable service classes. Avoid putting business logic directly in controllers.

This is still scaffold-first. Verify Docker build, Composer install, migrations, and tests before adding real integration features.
