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

Composer is not installed in this environment, so dependencies were not downloaded here.

## Install Later

When shell access and Composer are available, install Laravel here:

```bash
composer create-project laravel/laravel .
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Keep custom integration code in small, testable service classes. Avoid putting business logic directly in controllers.

If installing Laravel into this existing directory, preserve the custom `app/`, `routes/`, `database/migrations/`, `config/omnibridge.php`, and `tests/` files.
