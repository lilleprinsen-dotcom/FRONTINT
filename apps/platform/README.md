# OmniBridge Platform

This directory is reserved for the main Laravel integration platform.

## Intended Stack

- Laravel 11 or newer
- PostgreSQL
- Redis or database queues
- Laravel Horizon later if Redis queue visibility becomes useful
- Blade or Filament for a minimal admin dashboard
- API-first controllers for webhooks, sync jobs, gift card adapter calls, and manual retry actions

## Install Later

When shell access and Composer are available, install Laravel here:

```bash
composer create-project laravel/laravel .
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Keep custom integration code in small, testable service classes. Avoid putting business logic directly in controllers.

