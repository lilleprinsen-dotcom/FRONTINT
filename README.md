# OmniBridge

OmniBridge is a minimalist SaaS integration platform for connecting WooCommerce with Front Systems POS.

The first customer use case is Lilleprinsen, a Norwegian retailer using WooCommerce, Front Systems POS, Dintero Checkout, Stripe, and WebToffee WooCommerce Gift Cards.

## Core Direction

- WooCommerce is the master for products, prices, stock, orders, customers, gift cards, and business logic.
- Front Systems is the in-store POS and employee work surface.
- The SaaS platform is the integration brain: webhooks, queues, mappings, retries, logs, and reconciliation.
- The WooCommerce plugin stays thin and only handles Woo-specific adapter behavior.
- Front capabilities must be confirmed through documented APIs, configuration, webhooks, and the merchant contract. Do not assume custom Front POS UI.

## Repository Structure

```text
apps/
  platform/              Laravel integration platform
  woocommerce-plugin/    Thin WordPress/WooCommerce adapter plugin
docs/                    Architecture, requirements, roadmap, and operating docs
infra/                   Local development and hosting notes
docker-compose.yml       Local Laravel, PostgreSQL, and Redis setup
```

## Current Status

This repository contains the technical specification and a Laravel-style platform scaffold. Phase 1 foundation now includes basic login, organizations, connection setup, encrypted credential storage, connection test actions, and a minimal dashboard.

Phase 2 has started with read-only WooCommerce and Front Systems API clients for connection testing only:

- WooCommerce: `GET /wp-json/wc/v3/system_status`
- Front Systems: `GET /api/Environment`

It is still staging-first: real integration writes are disabled unless explicitly enabled and reviewed.

The scaffold now includes a root `.gitignore`, executable helper scripts, a committed Laravel `composer.lock`, and a minimal GitHub Actions workflow for platform tests.

## Front Systems API documentation

Official Front Systems API specs should go in `docs/vendor/front-systems/openapi/`.

The current stored spec is:

```text
docs/vendor/front-systems/openapi/frontsystems.openapi.json
```

Use `docs/vendor/front-systems/front-api-endpoint-summary.md` for a concise endpoint overview generated from the stored spec.

If Front provides a direct OpenAPI/Swagger URL, download it with:

```bash
./scripts/download-front-openapi.sh "<OFFICIAL_SPEC_URL>"
```

Use this command to verify that a spec file is present:

```bash
./scripts/generate-front-client.sh
```

Do not commit secrets, API keys, tokens, cookies, private links, restricted vendor documentation without permission, or unredacted customer data.

## Webhook URLs

Public webhook URLs use opaque path tokens, not organization slugs:

```text
/webhooks/woocommerce/{pathToken}
/webhooks/front/{pathToken}
```

Duplicate webhook events are accepted but must not dispatch duplicate processing jobs.

## Dashboard

After local setup, open:

```text
http://localhost:8000/dashboard
```

Use the dashboard to create organizations, add WooCommerce/Front connections, view webhook path-token URLs, and run staging-safe connection checks. Connection tests do not perform live HTTP checks unless `OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=true`.

When live HTTP checks are enabled, WooCommerce and Front tests use read-only API endpoints only. They do not sync products, prices, stock, orders, refunds, gift cards, or omnichannel orders.

## Verification commands

Quick scaffold check:

```bash
./scripts/verify-platform-scaffold.sh
```

Laravel checks from `apps/platform` after Composer dependencies are installed:

```bash
php artisan --version
php artisan route:list
php artisan config:clear
php artisan test
```

Docker verification from the repository root:

```bash
docker compose build
docker compose run --rm platform composer install
docker compose run --rm platform cp .env.example .env
docker compose run --rm platform php artisan key:generate
docker compose run --rm platform php artisan migrate
docker compose run --rm platform php artisan test
./scripts/generate-front-client.sh
```

The platform is safe by default: production writes and live HTTP connection checks are disabled unless explicitly enabled.

No real WooCommerce or Front Systems API writes exist yet.

## Next Steps

1. Run the verification commands above in a local Docker environment.
2. Confirm Front Systems API module access, webhook signing/retry behavior, reservation, gift card, and omnichannel capabilities.
3. Complete authentication and the minimal dashboard/setup wizard.
4. Build the first proof of concept tests listed in [docs/first-poc-checklist.md](docs/first-poc-checklist.md).
5. Keep all work staging-first. Do not write to production systems until explicitly enabled and reviewed.
