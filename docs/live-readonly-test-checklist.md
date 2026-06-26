# Live Read-Only Test Checklist

Use this checklist before testing real staging/sandbox credentials.

- Confirm local tests pass with `php artisan test`.
- Confirm scaffold verification passes with `./scripts/verify-platform-scaffold.sh`.
- Confirm `OMNIBRIDGE_ALLOW_PRODUCTION_WRITES=false`.
- Confirm `OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=false` initially.
- Add staging/test WooCommerce credentials only.
- Add Front sandbox/test API key if available.
- If only a production Front API key exists, test only `GET /api/Environment` and `GET /api/Stores` first.
- Enable `OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=true` only locally or in staging.
- Run the connection test first.
- Run Front stores discovery before Front product discovery.
- Run WooCommerce product discovery.
- Verify Front product discovery uses `POST /api/Product` with `pageSize=10` and does not call `/api/products`.
- Verify WooCommerce product discovery uses `GET /wp-json/wc/v3/products` with `per_page=10`.
- Verify no write endpoints were called.
- Verify no product sync, price sync, stock sync, order sync, refunds, gift cards, or omnichannel actions occurred.
- Review detected WooCommerce GTIN/EAN candidates as candidates only.
- Turn live HTTP tests back off after testing with `OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=false`.
