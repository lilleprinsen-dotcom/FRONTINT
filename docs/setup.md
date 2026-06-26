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

The dashboard is intentionally minimal, but it now includes login, organizations, connection setup, webhook URLs, and connection checks.

Connection status is safe by default. Live API checks are disabled unless explicitly enabled.

Log in with the admin user created in the previous step.

## 9. Create or Review Organization

The first admin command creates the Lilleprinsen organization if it does not already exist.

In the dashboard, confirm:

- Organization name and slug
- Environment is `staging`
- Status is `active`
- WooCommerce and Front webhook URLs use path tokens

## 10. Add Connections

From the dashboard:

1. Click **Add connection**.
2. Choose WooCommerce for the WooCommerce staging connection placeholder.
3. Add the staging base URL, for example `https://store.example.com`.
4. Add staging credentials only.
5. Save the connection.
6. Repeat with Front Systems for the Front staging connection placeholder.

Credentials are encrypted at rest and are not shown again after saving.

For Front Systems REST API V2, the base URL should match the official API server for the tenant, usually:

```text
https://frontsystemsapis.frontsystems.no/restapi/V2
```

## 11. Test Connections

Click **Test** beside a connection.

By default this only verifies required settings are stored. Live HTTP checks are disabled unless:

```text
OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=true
```

Keep this disabled until staging credentials and URLs are confirmed.

When live HTTP checks are enabled, the current read-only probes are:

- WooCommerce: `GET /wp-json/wc/v3/system_status`
- Front Systems: `GET /api/Environment`

These checks do not write data and do not perform product, stock, order, refund, gift card, or omnichannel sync.

## 12. Add WooCommerce Staging Credentials

In the future dashboard:

1. Open the Connections page.
2. Choose WooCommerce.
3. Add the staging store URL.
4. Add staging API credentials.
5. Save and test the connection.

## 13. Add Front Credentials Later

Use Front sandbox/test credentials only until production readiness is explicitly approved.

## 14. Add Webhook URLs

Public webhook URLs use opaque path tokens from `webhook_endpoints.path_token`, not organization slugs:

- WooCommerce: `https://your-platform-domain/webhooks/woocommerce/{pathToken}`
- Front: `https://your-platform-domain/webhooks/front/{pathToken}`

Use staging URLs first.

The dashboard shows the generated webhook URLs under each organization.

## 15. Where to See Logs

Local Laravel logs will be in:

```text
apps/platform/storage/logs/
```

The dashboard should later show failed events and queue status without requiring file access.

## 16. Run Tests

```bash
docker compose run --rm platform php artisan test
```

If PHP and Composer are installed locally, you can also run:

```bash
cd apps/platform
php artisan test
```

## 17. Verification commands

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

The scripts in `scripts/` are executable in git. If a local checkout loses executable bits, run:

```bash
chmod +x scripts/*.sh
```

The repository also includes `.github/workflows/platform-ci.yml`, which runs Composer validation and Laravel tests on pull requests and pushes to `main` without real WooCommerce or Front credentials.

## 18. What Is Still Placeholder

The scaffold does not yet implement:

- Product sync
- Price sync
- Stock sync
- Front sale import
- WooCommerce refund logic
- Gift card redemption
- Omnichannel order creation
- Real Front or WooCommerce API writes

The existing WooCommerce and Front API clients are intentionally read-only and only used for connection status checks.

Production writes remain disabled by default with:

```text
OMNIBRIDGE_ALLOW_PRODUCTION_WRITES=false
```

## 19. Run First Product Sync Test Later

After Phase 1 and the first product sync are implemented:

1. Mark one WooCommerce staging product eligible for Front.
2. Confirm SKU, GTIN, category, price, and stock are valid.
3. Run a single-product sync.
4. Confirm the product appears correctly in Front staging/test.

## 20. Stop Everything

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
