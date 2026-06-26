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
- Optional Front store discovery: `GET /api/Stores`

Connection tests record only minimal diagnostics: `success`, `failed`, or `skipped`, HTTP status, response time, safe error text, checked time, and safe Front store metadata when available.

Phase 3 adds read-only discovery and mapping preview:

- WooCommerce product sample: `GET /wp-json/wc/v3/products?per_page=10&page=1&status=publish`
- Front stores: `GET /api/Stores`
- Front product sample: `POST /api/Product` with a read-only search body limited to 10 products

Discovery stores only sanitized snapshots in `connection_discovery_snapshots`. It detects likely WooCommerce GTIN/EAN fields such as `Zettle_barcode`, `_iZettle_barcode`, `ean`, `gtin`, and `barcode`, then previews possible Woo ↔ Front matches by GTIN first, SKU to Front `externalSKU` second, and SKU to Front `identity` third. This is not product sync and does not write final product mappings.

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

## Health Endpoints

Hosting platforms should use:

- `GET /health/live` for liveness checks that should not require the database.
- `GET /health/ready` for readiness checks that verify the app can reach the database.

`GET /health` is kept for compatibility and behaves like the readiness check.

## Dashboard

After local setup, open:

```text
http://localhost:8000/dashboard
```

Use the dashboard to create organizations, add WooCommerce/Front connections, view webhook path-token URLs, and run staging-safe connection checks. Connection tests do not perform live HTTP checks unless `OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=true`.

When live HTTP checks are enabled, WooCommerce and Front tests and discovery actions use read-only API endpoints only. They do not sync products, prices, stock, orders, refunds, gift cards, or omnichannel orders.

The dashboard connection test button posts to `/connections/{connection}/test`.
Discovery actions use:

```text
POST /connections/{connection}/discover/stores
POST /connections/{connection}/discover/products
GET /connections/{connection}/discovery
```

Keep `OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=false` for safe local setup with dummy credentials. Set it to `true` only for staging/test credentials.

## Mac local testing with SQLite

If you have PHP 8.3 and Composer installed on your Mac, you can test the scaffold without Docker PostgreSQL:

```bash
cd /Users/petterholm/Documents/Posten\ robo/FRONTINT
chmod +x scripts/*.sh
./scripts/verify-platform-scaffold.sh
cd apps/platform
composer install
cp .env.example .env
php artisan key:generate
```

Edit `apps/platform/.env` and set:

```text
DB_CONNECTION=sqlite
```

Then create the SQLite database and finish setup:

```bash
touch database/database.sqlite
php artisan migrate
php artisan omnibridge:create-admin
php artisan test
php artisan serve
```

Open `http://localhost:8000/dashboard`. Keep `OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=false` unless you are testing read-only staging credentials.

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
./scripts/generate-front-client.sh
./scripts/verify-platform-scaffold.sh
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

No real WooCommerce or Front Systems API writes or sync jobs exist yet.

## Next Steps

1. Run the verification commands above in a local Docker environment.
2. Confirm Front Systems API module access, webhook signing/retry behavior, reservation, gift card, and omnichannel capabilities.
3. Complete authentication and the minimal dashboard/setup wizard.
4. Build the first proof of concept tests listed in [docs/first-poc-checklist.md](docs/first-poc-checklist.md).
5. Keep all work staging-first. Do not write to production systems until explicitly enabled and reviewed.
