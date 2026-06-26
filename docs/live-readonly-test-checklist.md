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

## C. Start Portal

```bash
php artisan serve
```

Open:

```text
http://localhost:8000/dashboard
```

## D. Add WooCommerce Staging Connection

In the portal, add the WooCommerce staging/test site URL, consumer key, and consumer secret.

## E. Add Front Connection

In the portal, add the Front Systems base URL and API key.

Use a Front sandbox/test API key if available. If only a production Front API key exists, test only `GET /api/Environment` and `GET /api/Stores` first.

## F. Test With Live HTTP Disabled

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

## G. Enable Live Read-Only Tests

Stop the server.

Set this locally/staging only:

```text
OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=true
```

Keep:

```text
OMNIBRIDGE_ALLOW_PRODUCTION_WRITES=false
```

## H. Restart Server

```bash
php artisan serve
```

## I. Run Preflight Again

```bash
php artisan omnibridge:preflight-readonly
```

Proceed only if production writes are disabled and the environment is local/staging.

## J. Test WooCommerce Connection

Run WooCommerce connection test from the dashboard.

Expected endpoint:

```text
GET /wp-json/wc/v3/system_status
```

## K. Test Front Connection

Run Front connection test from the dashboard.

Expected endpoint:

```text
GET /api/Environment
```

## L. Run Front Stores Discovery

Run Front stores discovery before Front product discovery.

Expected endpoint:

```text
GET /api/Stores
```

## M. Run WooCommerce Product Discovery

Expected endpoint:

```text
GET /wp-json/wc/v3/products?per_page=10&page=1&status=publish
```

## N. Run Front Product Discovery Last

Only after the previous checks work, run Front product discovery.

Expected endpoint:

```text
POST /api/Product
```

This is read-only according to the Front OpenAPI spec. It must use `pageSize=10` and must not call `/api/products`.

## O. Turn Live HTTP Tests Back Off

After testing, set:

```text
OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=false
```

Confirm no write endpoints were called and no product sync, price sync, stock sync, order sync, refunds, gift cards, or omnichannel actions occurred.
