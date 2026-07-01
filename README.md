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
- WooCommerce variation sample for variable products: `GET /wp-json/wc/v3/products/{productId}/variations?per_page=10&page=1`
- Front stores: `GET /api/Stores`
- Front product sample: `POST /api/Product` with a read-only search body limited to 10 products

Front's OpenAPI spec documents `POST /api/Product` as the read-only product listing/search endpoint used for discovery. During discovery it must stay capped at `pageSize <= 10` and must not be confused with `/api/products`, which is the product CRUD endpoint.

Discovery stores only sanitized snapshots in `connection_discovery_snapshots` and keeps only the latest 5 snapshots per connection and discovery type. This table is not long-term product storage. It detects likely WooCommerce GTIN/EAN candidate fields such as `Zettle_barcode`, `iZettle_barcode`, `_Zettle_barcode`, `_iZettle_barcode`, `ean`, `gtin`, and `barcode`, then previews possible Woo ↔ Front matches by GTIN first, SKU to Front `externalSKU` second, and SKU to Front `identity` third. Woo discovery also creates a sample readiness report for products and variations. Candidate GTIN/EAN values must be confirmed before final mapping. This is not product sync and does not write final product mappings.

The **Woo Readiness** page at `/woo-readiness` is the simple owner-facing view for the latest WooCommerce discovery sample. It shows ready SKU+EAN items, SKU-only items, blocked items, duplicate SKUs/GTINs, variable parents, sellable variations, missing SKU cases, and missing price cases. It is read-only and does not require a Front account.

Phase 4 adds controlled 10-item mapping PoC preparation:

- Open `/mapping/product-poc` after WooCommerce product discovery has succeeded.
- Select up to 10 WooCommerce products or variations from the stored discovery snapshot.
- Generate a local preview-only sync plan in `product_sync_preview_plans`.
- Review ready/blocked validation, warnings, proposed Woo to Front fields, and `NEEDS_CONFIRMATION` items.
- Front product discovery is optional at this stage; without it, the plan becomes a Woo-only readiness plan and Front matches are marked as missing.

The PoC plan uses stored snapshots only. It performs no external API calls, does not write products, prices, stock, orders, or final `product_mappings`, and does not call Front or WooCommerce write endpoints. Variation discovery is read-only; variations can be selected as first-class preview candidates, but no variation writes exist yet. GTIN/EAN candidates must be confirmed before any future write test.
Variation preview rows inherit parent product name, category, brand, and image candidates from the same WooCommerce discovery snapshot, while variation attributes become the proposed Front size label.
SKU and Woo product/variation IDs are valid identity inputs for products without GTIN/EAN. Missing GTIN/EAN is a warning by default when SKU exists, not a blocker, though a strict sync profile can still require GTIN/EAN for barcode-specific tests.

Phase 5 adds the WooCommerce to Front product sync foundation for a 70,000-product catalog:

- The production goal is all relevant WooCommerce products and variations, not only a manually selected subset.
- Initial full sync must run in controlled batches with checkpoints and queues.
- Incremental WooCommerce updates will later create deduplicated product sync events.
- Product sync profiles define safe defaults, limits, validation rules, scope, identity strategy, GTIN strategy, and preview/limited/full/incremental/production modes.
- Product sync preview runs convert the latest mapping PoC plan into local run and item status rows from the Testing Lab.
- Sync runs and run items are paginated and searchable so the portal never loads a full catalog at once.
- The Woo Readiness and Product Sync pages show owner-friendly status: Ready, Needs attention, Preview only, Safe mode, and Last checked.
- Advanced technical settings are separated from normal store-owner pages.
- A staging batch write flow now exists for selected products/variations only. It can create or update up to 100 selected items in Front from the latest WooCommerce discovery snapshot when the profile mode is `staging_batch` or `limited_write_test`.
- WooCommerce product/variation ID is used as the stable identity. SKU and EAN/GTIN are synced as mutable fields, so changing them in WooCommerce should update the existing Front product instead of breaking the mapping.
- Regular price is sent as the Front product price.
- Sale price sync now has an explicit Front PriceListV2 staging flow for already-synced products with sale price candidates.
- The staging batch flow does not write to WooCommerce, stock, orders, refunds, gift cards, or omnichannel records.
- Full catalog sync is still not implemented.

See [docs/woo-to-front-product-sync-strategy.md](docs/woo-to-front-product-sync-strategy.md).
See [docs/staging-batch-product-sync.md](docs/staging-batch-product-sync.md) for the current staging batch test flow.

WooCommerce plugin foundation:

- The plugin has a WooCommerce admin settings page.
- The public health endpoint is `GET /wp-json/omnibridge/v1/health`.
- The signed read-only adapter test endpoint is `GET /wp-json/omnibridge/v1/connection-test`.
- Woo-side plugin health checks can be tested directly in WordPress without platform safe-mode skips.
- The Laravel Connections page can also run a signed **Test Woo plugin** check when the WooCommerce connection has the same plugin shared secret saved.
- Woo REST testing uses WooCommerce consumer key/secret; Woo plugin adapter testing uses the OmniBridge plugin shared secret.
- Signed adapter tests use `X-Omnibridge-Timestamp` and `X-Omnibridge-Signature` HMAC headers.
- Product edit pages can show lightweight OmniBridge eligibility/status metadata.
- The plugin still does not run sync logic, scan the catalog, call Front, or write prices/stock/orders.

See [apps/woocommerce-plugin/README.md](apps/woocommerce-plugin/README.md).

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

## Portal Navigation

After local setup, open:

```text
http://localhost:8000/dashboard
```

The normal merchant workflow is intentionally small:

- `Dashboard`: plain-language status, safety state, setup progress, and next steps.
- `Connections`: WooCommerce and Front connection setup, status, and read-only connection tests.
- `Product Sync`: product sync readiness, run status, and production-safe controls.
- `Advanced`: technical settings, webhooks, raw events, and the Testing Lab link.

Testing and experimental flows are kept out of the normal navigation:

- `Testing Lab` at `/lab`: read-only discovery, mapping preview, and preview-run experiments.
- Discovery and mapping PoC pages remain available from the Lab, not as daily merchant pages.

Connection tests do not perform live HTTP checks unless `OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=true`.

When live HTTP checks are enabled, WooCommerce and Front tests and discovery actions use read-only API endpoints only. They do not sync products, prices, stock, orders, refunds, gift cards, or omnichannel orders.

The connection test button posts to `/connections/{connection}/test`.
Discovery actions use:

```text
POST /connections/{connection}/discover/stores
POST /connections/{connection}/discover/products
GET /connections/{connection}/discovery
```

Keep `OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=false` for safe local setup with dummy credentials. Set it to `true` only for staging/test credentials.

Before enabling live read-only HTTP calls, run:

```bash
cd apps/platform
php artisan omnibridge:preflight-readonly
```

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

Open `http://localhost:8000/dashboard`, then use `http://localhost:8000/connections` for connection setup. Keep `OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=false` unless you are testing read-only staging credentials. Use `http://localhost:8000/lab` only for read-only discovery and mapping experiments.

Before using real staging/test credentials, follow [docs/live-readonly-test-checklist.md](docs/live-readonly-test-checklist.md).

After WooCommerce product discovery is complete, open `http://localhost:8000/mapping/product-poc` to prepare the 10-item mapping PoC plan. Front product discovery is only needed for existing Front match preview; without it, the page still supports a Woo-only readiness plan. The page is preview-only and uses stored snapshots, so live HTTP can be turned off again before using it.

Then open `http://localhost:8000/lab` to create a local preview run from the latest mapping PoC plan. This creates local status rows only and performs no API writes.

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
