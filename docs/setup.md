# Setup Guide

This guide is for non-developer-friendly local setup. The platform is not fully implemented yet, so some commands are placeholders for the next development phase.

## 1. Clone the Repo

```bash
git clone https://github.com/lilleprinsen-dotcom/FRONTINT.git
cd FRONTINT
```

## 2. Copy Environment File

```bash
cp apps/platform/.env.example apps/platform/.env
```

Do not add real production credentials to local files.

## 3. Start Docker

```bash
docker compose up -d
```

This starts the platform placeholder, PostgreSQL, and Redis.

## 4. Run Migrations

After Laravel is installed:

```bash
cd apps/platform
php artisan migrate
```

## 5. Create First Admin User

After authentication is implemented:

```bash
php artisan omnibridge:create-admin
```

## 6. Add WooCommerce Staging Credentials

In the future dashboard:

1. Open the Connections page.
2. Choose WooCommerce.
3. Add the staging store URL.
4. Add staging API credentials.
5. Save and test the connection.

## 7. Add Front Credentials Later

Use Front sandbox/test credentials only until production readiness is explicitly approved.

## 8. Add Webhook URLs

Planned webhook URLs:

- WooCommerce: `https://your-platform-domain/webhooks/woocommerce/{tenant}`
- Front: `https://your-platform-domain/webhooks/front/{tenant}`

Use staging URLs first.

## 9. Where to See Logs

Local Laravel logs will be in:

```text
apps/platform/storage/logs/
```

The dashboard should later show failed events and queue status without requiring file access.

## 10. Run First Product Sync Test

After Phase 1 and the first product sync are implemented:

1. Mark one WooCommerce staging product eligible for Front.
2. Confirm SKU, EAN/GTIN, category, price, and stock are valid.
3. Run a single-product sync.
4. Confirm the product appears correctly in Front staging/test.

## 11. Stop Everything

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

