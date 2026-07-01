# Setup Guide

This guide is for non-developer-friendly local setup.

The platform is still scaffold-first. It has Laravel-style structure, migrations, and tests, but full local installation must be verified with Docker, Composer, migrations, and unit tests before real integration work starts.

## Prerequisites

- Git
- Docker Desktop or another Docker Compose-compatible runtime
- No real production credentials

Optional local tools:

- PHP 8.3+
- Composer

Docker is the preferred path for non-developers. Local PHP/Composer are useful for faster checks.

## Mac local testing with SQLite

If you have PHP 8.3 and Composer installed on your Mac, you can run the scaffold with a local SQLite database:

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

Then continue:

```bash
touch database/database.sqlite
php artisan migrate
php artisan omnibridge:create-admin
php artisan test
php artisan serve
```

Open:

```text
http://localhost:8000/dashboard
```

Keep `OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=false` for dummy credentials. Enable live checks only with staging/test WooCommerce or Front Systems credentials.

## WooCommerce Plugin Setup and Direct Test

The WooCommerce plugin can be tested directly inside a WooCommerce staging site. This does not use the Laravel platform safe-mode skip.

1. Copy `apps/woocommerce-plugin` into the staging WordPress plugins folder as:

```text
wp-content/plugins/omnibridge-woocommerce-adapter/
```

2. Activate **OmniBridge WooCommerce Adapter** in WordPress admin.
3. Open **WooCommerce > OmniBridge**.
4. Keep environment set to `staging`.
5. Add the OmniBridge platform URL if available.
6. Add a tenant key if available.
7. Add a shared secret for signed endpoint tests.
8. Keep signed endpoints enabled.
9. Keep product fields enabled if you want to mark products for future sync readiness.

Public health check:

```text
https://your-staging-store.example/wp-json/omnibridge/v1/health
```

Signed read-only connection test:

```bash
SECRET="replace-with-shared-secret"
TS="$(date +%s)"
SIG="$(printf "GET\n/omnibridge/v1/connection-test\n${TS}" | openssl dgst -sha256 -hmac "${SECRET}" -binary | xxd -p -c 256)"

curl \
  -H "X-Omnibridge-Timestamp: ${TS}" \
  -H "X-Omnibridge-Signature: ${SIG}" \
  "https://your-staging-store.example/wp-json/omnibridge/v1/connection-test"
```

Expected result:

- The plugin responds with `status: success`.
- The response says `read_only: true` and `writes_performed: false`.
- No secrets, customer data, order data, or product payloads are returned.
- No WooCommerce, Front, stock, price, order, refund, gift card, or customer writes occur.

Portal-side plugin adapter test:

1. Open the local OmniBridge portal.
2. Edit the WooCommerce connection.
3. Set **WooCommerce site URL** to the staging store URL.
4. Save the same shared secret in **OmniBridge plugin shared secret**.
5. Open **Connections**.
6. Click **Test Woo plugin**.

This signs and calls:

```text
GET /wp-json/omnibridge/v1/connection-test
```

This is separate from **Test Woo REST**, which uses WooCommerce consumer key/secret and `GET /wp-json/wc/v3/system_status`.

## 1. Clone the Repo

```bash
git clone https://github.com/lilleprinsen-dotcom/FRONTINT.git
cd FRONTINT
```

## 2. Build Docker Images

```bash
docker compose build
```

This builds the local platform image. It does not start the app yet.

## 3. Install Dependencies

Run Composer inside the platform container:

```bash
docker compose run --rm platform composer install
```

If this fails, the project is still inspectable as documentation/scaffold, but the Laravel app cannot run yet.

The Laravel dependency lockfile is committed at:

```text
apps/platform/composer.lock
```

## 4. Copy Environment File

```bash
docker compose run --rm platform cp .env.example .env
```

Do not add real production credentials to local files.

## 5. Generate App Key

After dependencies are installed:

```bash
docker compose run --rm platform php artisan key:generate
```

## 6. Run Migrations

```bash
docker compose run --rm platform php artisan migrate
```

## 7. Create First Admin User

```bash
docker compose run --rm platform php artisan omnibridge:create-admin
```

## 8. Open Dashboard

Start the app server:

```bash
docker compose up
```

Open:

```text
http://localhost:8000/dashboard
```

The dashboard is intentionally minimal. It shows plain-language status, setup progress, safety state, and next steps.

Use:

- `/dashboard` for everyday status.
- `/connections` for WooCommerce and Front connection setup and read-only connection tests.
- `/product-sync` for sync readiness and status.
- `/advanced` for technical settings.
- `/lab` for testing-only discovery, mapping preview, and preview-run experiments.

Connection status is safe by default. Live API checks are disabled unless explicitly enabled.

Log in with the admin user created in the previous step.

## 9. Create or Review Organization

The first admin command creates the Lilleprinsen organization if it does not already exist.

In the dashboard, confirm:

- Organization name and slug
- Environment is `staging`
- Status is `active`

Webhook URLs are technical details and are shown in Advanced.

## 10. Add Connections

From the Connections page:

1. Click **Add connection**.
2. Choose WooCommerce for the WooCommerce staging connection placeholder.
3. Add the WooCommerce site URL, for example `https://store.example.com`.
4. Add staging consumer key and consumer secret only.
5. Save the connection.
6. Repeat with Front Systems for the Front staging connection placeholder.

Credentials are encrypted at rest and are not shown again after saving.

For Front Systems REST API V2, the base URL should match the official API server for the tenant, usually:

```text
https://frontsystemsapis.frontsystems.no/restapi/V2
```

## 11. Test Connections

Click **Test** beside a connection.

The connection test action uses:

```text
POST /connections/{connection}/test
```

By default this only verifies required settings are stored. Live HTTP checks are disabled unless:

```text
OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=true
```

Keep this disabled until staging credentials and URLs are confirmed.

When live HTTP checks are enabled, the current read-only probes are:

- WooCommerce: `GET /wp-json/wc/v3/system_status`
- Front Systems: `GET /api/Environment`
- Optional Front store metadata: `GET /api/Stores`

These checks do not write data and do not perform product, stock, order, refund, gift card, or omnichannel sync.

There is no separate `/api/connections/{connection}/test` route in the scaffold. Keep connection testing in the authenticated portal flow until a public API use case is intentionally designed.

Connection test results are stored as minimal diagnostics only:

- `success`, `failed`, or `skipped`
- HTTP status code when an HTTP call is made
- Response time
- Safe error text
- Checked timestamp
- Safe Front store metadata when `/api/Stores` succeeds: store name, store ID, stock ID, currency, and time zone

Full API response bodies are not stored.

## 12. Read-Only Discovery and Mapping Preview

Discovery is available from the separate **Testing Lab** at `/lab`, or from each connection's discovery page. It is intentionally not part of the normal merchant dashboard.

Keep safe mode enabled for dummy values:

```text
OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=false
```

In safe mode, discovery actions return `skipped` and do not make HTTP calls.

Enable live discovery only with staging/test credentials:

```text
OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=true
```

Read-only discovery endpoints:

- WooCommerce product sample: `GET /wp-json/wc/v3/products?per_page=10&page=1&status=publish`
- WooCommerce variation sample for variable products: `GET /wp-json/wc/v3/products/{productId}/variations?per_page=10&page=1`
- Front stores: `GET /api/Stores`
- Front product sample: `POST /api/Product` with `pageSize=10` and read-only search filters

Front's OpenAPI spec documents `POST /api/Product` as the read-only product listing/search endpoint used here. Keep `pageSize <= 10` during discovery. Do not confuse this endpoint with `/api/products`, which is the product CRUD endpoint.

Stored discovery data is intentionally small and sanitized:

- Latest store metadata: store ID, store no, store name, stock ID, external stock ID, currency, and time zone.
- WooCommerce product sample metadata: ID, name, SKU, type, status, price, stock fields, categories, brand names if present, variation count, and likely GTIN/EAN candidate.
- WooCommerce variation sample metadata: parent product ID, variation ID, name, SKU, attributes, price, stock fields, and likely GTIN/EAN candidate.
- WooCommerce readiness report: sampled products and variations marked Ready, Needs attention, or Blocked with missing SKU, GTIN/EAN, price, stock, and variation-readiness notes.
- Front product sample metadata: product ID, name, number, variant, brand, group/subgroup, web/discontinued flags, and safe product size identifiers.

GTIN/EAN candidate detection checks Lilleprinsen-relevant WooCommerce meta keys first:

- `Zettle_barcode`
- `iZettle_barcode`
- `_Zettle_barcode`
- `_iZettle_barcode`

It also checks common keys such as `ean`, `_ean`, `gtin`, `_gtin`, `barcode`, and `_barcode`.

Detected GTIN/EAN values are candidates only. Confirm them against product data and Front results before final mapping. The Woo readiness report is a preview helper, not approval to sync.

## Woo Readiness Dashboard

Open:

```text
http://localhost:8000/woo-readiness
```

This is the easiest page to use before the Front account is ready. It uses only the latest stored WooCommerce discovery snapshot and does not call WooCommerce or Front.

It shows:

- Ready items with SKU and EAN/GTIN.
- SKU-only items that can use SKU fallback.
- Blocked items that need SKU, price, or duplicate cleanup.
- Duplicate SKUs and duplicate EAN/GTIN values.
- Variable parent products versus sellable variation rows.
- Missing SKU and missing price counts.

Use this page to decide what product data to fix in WooCommerce before any future Front write test.

When a Front product sample exists, the mapping preview compares the latest WooCommerce and Front product samples for the same organization:

1. Woo detected GTIN/EAN equals Front product size GTIN.
2. Woo SKU equals Front product size `externalSKU`.
3. Woo SKU equals Front product size `identity`.

If the Front product sample is missing, `/mapping/product-poc` still allows a Woo-only readiness plan from the latest WooCommerce discovery snapshot. Front match status will show as missing until Front product discovery is available. This preview is not final mapping and does not save rows to `product_mappings`.

Discovery snapshots keep only the latest 5 rows per connection and discovery type. The table is not long-term product storage.

## 10-Product Mapping PoC

After WooCommerce product discovery exists, open:

```text
http://localhost:8000/mapping/product-poc
```

Use this page to select up to 10 WooCommerce products or variations from the stored discovery snapshot and generate a local preview sync plan.

The plan shows:

- Proposed WooCommerce to Front product fields.
- Ready or blocked status per selected product or variation.
- Blocking validation issues such as missing SKU, missing both SKU and GTIN/EAN, duplicate SKU/GTIN, missing variation parent context, or missing price.
- Non-blocking warnings such as missing GTIN/EAN when SKU exists, missing brand/category, missing sale price, out-of-stock status, `manage_stock=false`, or no current Front sample match.
- Variable parent products can be previewed, but sellable variation rows are usually the better candidates for Front POS.
- `NEEDS_CONFIRMATION` items for category/group mapping, brand source, size label, product number/variant strategy, sale price handling, and primary identifier strategy.

The plan is stored in `product_sync_preview_plans` only. It is not final sync history, does not write to `product_mappings`, and does not call WooCommerce or Front APIs. Variation discovery is read-only; variations can be selected as first-class preview candidates, but no variation writes exist yet. Variation preview rows inherit parent product name, category, brand, and image candidates from the stored WooCommerce discovery snapshot. Variation attributes are shown as the proposed Front size label. Confirm GTIN/EAN mapping before any future write test.

## Product Sync Foundation

Open:

```text
http://localhost:8000/product-sync
```

This page prepares WooCommerce products and variations for Front. The production goal is all relevant WooCommerce products and variations, but the system must process them later in controlled batches with checkpoints and queues.

Current behavior:

- Uses the latest mapping preview plan when a lab user creates a preview run.
- Creates local preview runs only from the Testing Lab.
- Can create a staging batch run from the latest WooCommerce discovery snapshot.
- Can write up to 100 selected ready/warning products or variations to Front when the profile mode is `staging_batch` or `limited_write_test`.
- Can write WooCommerce sale prices to Front PriceListV2 for already-synced products when the price strategy allows it.
- Stores per-product status in `product_sync_run_items`.
- Stores product and variation-level run structure for future batched full catalog sync.
- Tracks future incremental product update events in `product_sync_events`.
- Provides paginated sync run views with filters/search so the portal never loads all products at once.
- Shows Ready, Needs attention, Failed, Preview only, and Last checked status in plain language.
- Does not write to WooCommerce.
- Does not write stock, orders, refunds, gift cards, or omnichannel data.
- Does not sync the full catalog.

For the current staging batch flow, see [staging-batch-product-sync.md](staging-batch-product-sync.md).

Sync profile settings are available at:

```text
http://localhost:8000/product-sync/profile
```

Advanced technical settings and lab/test workflows are grouped away from normal store-owner pages. Production mode is unavailable unless `OMNIBRIDGE_ALLOW_PRODUCTION_WRITES=true`.

Before using real staging/test credentials, follow [live-readonly-test-checklist.md](live-readonly-test-checklist.md).

Run the preflight command before and after enabling live read-only HTTP:

```bash
php artisan omnibridge:preflight-readonly
```

## Manual Safe Connection Test Flow

A. Keep safe mode enabled:

```text
OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=false
```

B. Create a WooCommerce connection with dummy values. Click **Test** and confirm the result is skipped/safe mode, not failed.

C. Create a Front Systems connection with dummy values. Click **Test** and confirm the result is skipped/safe mode, not failed.

D. Enable read-only live tests only when staging/test credentials are ready:

```text
OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=true
```

E. Add real staging/test credentials only. Do not use production credentials until staging is verified.

F. Run the connection test from the Connections page.

G. Confirm only read-only endpoints are called and no product, stock, order, refund, gift card, or omnichannel sync is performed.

## 13. Health Checks

Use these URLs for local and hosted health checks:

- `GET /health/live`: app liveness only, no database check.
- `GET /health/ready`: app and database readiness.
- `GET /health`: compatibility endpoint, currently same as readiness.

For Render, DigitalOcean App Platform, or similar hosting, use `/health/ready` when the service should only receive traffic after the database is reachable. Use `/health/live` for process liveness checks.

## 14. Add WooCommerce Staging Credentials

In the future dashboard:

1. Open the Connections page.
2. Choose WooCommerce.
3. Add the staging store URL.
4. Add staging API credentials.
5. Save and test the connection.

## 15. Add Front Credentials Later

Use Front sandbox/test credentials only until production readiness is explicitly approved.

## 16. Add Webhook URLs

Public webhook URLs use opaque path tokens from `webhook_endpoints.path_token`, not organization slugs:

- WooCommerce: `https://your-platform-domain/webhooks/woocommerce/{pathToken}`
- Front: `https://your-platform-domain/webhooks/front/{pathToken}`

Use staging URLs first.

Advanced shows the generated webhook URLs under each organization.

## 17. Where to See Logs

Local Laravel logs will be in:

```text
apps/platform/storage/logs/
```

The dashboard should later show failed events and queue status without requiring file access.

## 18. Run Tests

```bash
docker compose run --rm platform php artisan test
```

If PHP and Composer are installed locally, you can also run:

```bash
cd apps/platform
php artisan test
```

## 19. Verification commands

Run the quick scaffold check first:

```bash
./scripts/verify-platform-scaffold.sh
```

Then run these Docker commands after cloning the repository:

```bash
docker compose build
docker compose run --rm platform composer install
docker compose run --rm platform cp .env.example .env
docker compose run --rm platform php artisan key:generate
docker compose run --rm platform php artisan migrate
docker compose run --rm platform php artisan test
./scripts/generate-front-client.sh
```

If local PHP and Composer are available, these non-Docker commands should also pass from `apps/platform`:

```bash
composer install
php artisan --version
php artisan route:list
php artisan config:clear
php artisan test
```

The scripts in `scripts/` are executable in git. If a local checkout loses executable bits, run:

```bash
chmod +x scripts/*.sh
```

The repository also includes `.github/workflows/platform-ci.yml`, which runs Composer validation and Laravel tests on pull requests and pushes to `main` without real WooCommerce or Front credentials.

## 20. What Is Still Placeholder

The scaffold does not yet implement:

- Product sync
- Price sync
- Stock sync
- Front sale import
- WooCommerce refund logic
- Gift card redemption
- Omnichannel order creation
- Real Front or WooCommerce API writes

The existing WooCommerce and Front API clients are intentionally read-only and only used for connection status checks, discovery samples, and mapping preview.

Production writes remain disabled by default with:

```text
OMNIBRIDGE_ALLOW_PRODUCTION_WRITES=false
```

## 21. Run First Product Sync Test Later

After Phase 1 and the first product sync are implemented:

1. Mark one WooCommerce staging product eligible for Front.
2. Confirm SKU, GTIN, category, price, and stock are valid.
3. Run a single-product sync.
4. Confirm the product appears correctly in Front staging/test.

## 22. Stop Everything

```bash
docker compose down
```

## Glossary

- BOPIS: Buy online, pick up in store.
- ROPIS: Reserve online, pay in store.
- BODFS: Buy online, deliver from store.
- Tenant: one merchant/account in OmniBridge.
- Idempotency: a safety pattern that prevents the same event from being processed twice.
- Reconciliation: a scheduled check that compares system state and detects mismatches.
