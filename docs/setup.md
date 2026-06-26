# Setup Guide

This guide is for non-developer-friendly local setup.

The platform is still scaffold-first. It has Laravel-style structure, migrations, and tests, but full local installation must be verified with Docker, Composer, migrations, and unit tests before real integration work starts.

## 1. Clone the Repo

```bash
git clone https://github.com/lilleprinsen-dotcom/FRONTINT.git
cd FRONTINT
```

## 2. Start Docker

```bash
docker compose up -d --build postgres redis
```

This starts PostgreSQL and Redis. The platform app is started after Composer dependencies and `.env` are ready.

## 3. Install Dependencies

Run Composer inside the platform container:

```bash
docker compose run --rm platform composer install
```

If this fails, the project is still inspectable as documentation/scaffold, but the Laravel app cannot run yet.

## 4. Copy Environment File

```bash
cp apps/platform/.env.example apps/platform/.env
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
docker compose up -d
```

Open:

```text
http://localhost:8000/dashboard
```

The dashboard is still minimal and may require authentication scaffolding to be completed before it is useful.

## 9. Add WooCommerce Staging Credentials

In the future dashboard:

1. Open the Connections page.
2. Choose WooCommerce.
3. Add the staging store URL.
4. Add staging API credentials.
5. Save and test the connection.

## 10. Add Front Credentials Later

Use Front sandbox/test credentials only until production readiness is explicitly approved.

## 11. Add Webhook URLs

Public webhook URLs use opaque path tokens from `webhook_endpoints.path_token`, not organization slugs:

- WooCommerce: `https://your-platform-domain/webhooks/woocommerce/{pathToken}`
- Front: `https://your-platform-domain/webhooks/front/{pathToken}`

Use staging URLs first.

## 12. Where to See Logs

Local Laravel logs will be in:

```text
apps/platform/storage/logs/
```

The dashboard should later show failed events and queue status without requiring file access.

## 13. Run First Product Sync Test

After Phase 1 and the first product sync are implemented:

1. Mark one WooCommerce staging product eligible for Front.
2. Confirm SKU, GTIN, category, price, and stock are valid.
3. Run a single-product sync.
4. Confirm the product appears correctly in Front staging/test.

## 14. Stop Everything

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
