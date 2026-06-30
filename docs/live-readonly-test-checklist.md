# Live Read-Only Test Checklist

Use this checklist before testing real staging/sandbox credentials. Do not commit credentials.

## A. Run Local Checks

From the repository root:

```bash
./scripts/verify-platform-scaffold.sh
cd apps/platform
php artisan test
php artisan omnibridge:preflight-readonly
```

## B. Confirm Safe Values

Confirm:

```text
OMNIBRIDGE_ALLOW_PRODUCTION_WRITES=false
OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=false
```

Production writes must remain disabled.

## C. Test the WooCommerce Plugin Directly

If the OmniBridge WooCommerce Adapter is installed on the staging WooCommerce site, test it before enabling platform live HTTP checks.

Public plugin health check:

```text
GET /wp-json/omnibridge/v1/health
```

Signed plugin connection test:

```text
GET /wp-json/omnibridge/v1/connection-test
```

The signed test uses `X-Omnibridge-Timestamp` and `X-Omnibridge-Signature` HMAC headers. These plugin endpoints are read-only and do not use the Laravel safe-mode skip, so they can confirm the Woo-side adapter is installed and responding without running sync.

## D. Start Portal

```bash
php artisan serve
```

Open:

```text
http://localhost:8000/dashboard
```

## E. Add WooCommerce Staging Connection

In the portal, add:

- WooCommerce staging/test site URL.
- WooCommerce consumer key and consumer secret for **Test Woo REST**.
- OmniBridge plugin shared secret for **Test Woo plugin**.

Click **Test Woo plugin** to verify the installed WordPress plugin adapter from the portal. This signs and calls only:

```text
GET /wp-json/omnibridge/v1/connection-test
```

## F. Add Front Connection

In the portal, add the Front Systems base URL and API key.

Use a Front sandbox/test API key if available. If only a production Front API key exists, test only `GET /api/Environment` and `GET /api/Stores` first.

## G. Test With Live HTTP Disabled

Keep:

```text
OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=false
```

Press the connection test buttons.

Expected result:

```text
skipped / safe mode
```

No external HTTP calls should be made.

## H. Enable Live Read-Only Tests

Stop the server.

Set this locally/staging only:

```text
OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=true
```

Keep:

```text
OMNIBRIDGE_ALLOW_PRODUCTION_WRITES=false
```

## I. Restart Server

```bash
php artisan serve
```

## J. Run Preflight Again

```bash
php artisan omnibridge:preflight-readonly
```

Proceed only if production writes are disabled and the environment is local/staging.

## K. Test WooCommerce Connection

Run WooCommerce connection test from the Connections page.

Expected endpoint:

```text
GET /wp-json/wc/v3/system_status
```

## L. Test Front Connection

Run Front connection test from the Connections page.

Expected endpoint:

```text
GET /api/Environment
```

## M. Run Front Stores Discovery

Run Front stores discovery before Front product discovery.

Expected endpoint:

```text
GET /api/Stores
```

## N. Run WooCommerce Product Discovery

Expected endpoint:

```text
GET /wp-json/wc/v3/products?per_page=10&page=1&status=publish
```

## O. Run Front Product Discovery Last

Only after the previous checks work, run Front product discovery.

Expected endpoint:

```text
POST /api/Product
```

This is read-only according to the Front OpenAPI spec. It must use `pageSize=10` and must not call `/api/products`.

## P. Turn Live HTTP Tests Back Off

After testing, set:

```text
OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=false
```

Confirm no write endpoints were called and no product sync, price sync, stock sync, order sync, refunds, gift cards, or omnichannel actions occurred.

## Q. Prepare the 10-Product Mapping PoC

After live read-only discovery has succeeded, turn live HTTP tests back off if no more external checks are needed.

Open:

```text
http://localhost:8000/mapping/product-poc
```

Select up to 10 WooCommerce products and generate the preview sync plan.

Review:

- Ready/blocked status.
- Blocking issues for missing SKU, missing GTIN/EAN, duplicate SKU/GTIN, missing variation parent context, or missing price.
- Warnings for missing brand/category, missing sale price, stock status, uncertain mappings, or no Front match.
- `NEEDS_CONFIRMATION` items before any future write test.

This is not product sync. The plan uses stored snapshots only, stores preview data in `product_sync_preview_plans`, does not write `product_mappings`, and does not call WooCommerce or Front APIs.

## R. Create a Product Sync Preview Run

Open:

```text
http://localhost:8000/product-sync
```

Click **Create preview run**.

Expected result:

- A local draft run is created.
- Each selected product has Ready or Needs attention status.
- Product and variation-level status fields are present for future full-catalog sync.
- Run items are paginated and searchable; the portal does not load a full catalog at once.
- No external APIs are called.
- No Front or WooCommerce writes happen.
- This prepares for later limited write tests and a future controlled full-catalog sync.
