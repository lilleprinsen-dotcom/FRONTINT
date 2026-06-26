# Scaffold Health Check

Use this checklist before starting real integration implementation.

- [x] Front OpenAPI file present at `docs/vendor/front-systems/openapi/frontsystems.openapi.json`.
- [x] `scripts/generate-front-client.sh` can find the OpenAPI file and print metadata.
- [x] `scripts/download-front-openapi.sh`, `scripts/generate-front-client.sh`, and `scripts/verify-platform-scaffold.sh` are executable.
- [x] Root `.gitignore` exists and ignores local secrets, dependencies, logs, caches, and SQLite databases.
- [x] `apps/platform/composer.lock` is committed after successful Composer install.
- [x] Minimal GitHub Actions CI exists at `.github/workflows/platform-ci.yml`.
- [x] PHP syntax passes in the current environment.
- [ ] Docker build pending/works.
- [x] Composer install works locally through a temporary Composer phar.
- [ ] Docker Composer install pending/works.
- [ ] Docker Laravel app key generation pending/works.
- [ ] Docker database migrations pending/works.
- [x] Smoke and feature tests exist for health, auth, dashboard, organizations, and webhooks.
- [x] Health endpoints exist for `/health`, `/health/live`, and `/health/ready`.
- [x] Unit/feature tests pass locally with `php artisan test`.
- [ ] Unit/feature tests pending Docker verification.
- [x] Webhook duplicate handling is present.
- [x] Secret redaction tests are present.
- [x] Idempotency tests are present.
- [x] Product mapping uses `gtin`, `external_sku`, and `front_product_ext_id`.
- [x] Basic login and dashboard foundation present.
- [x] Organization and connection setup foundation present.
- [x] Connection credentials use encrypted model casts.
- [x] Connection tests are staging-safe by default.
- [x] WooCommerce read-only connection test client exists.
- [x] Front Systems read-only connection test client exists.
- [x] Front Systems connection type uses `front_systems`.
- [x] Connection tests persist minimal diagnostics without response bodies.
- [x] Front store metadata is limited to store name, store ID, stock ID, currency, and time zone.
- [x] Dashboard shows safe connection status context.
- [x] Connection form shows only the selected connection type fields.
- [x] Duplicate `/api/connections/{connection}/test` route is not present.
- [x] No real credentials are committed.
- [x] No real WooCommerce or Front Systems API writes are implemented yet.

Notes:

- Docker verification is blocked in environments where `docker` is not installed.
- Run `./scripts/verify-platform-scaffold.sh` before starting real integration work.
- Keep production writes disabled unless explicitly approved and audited.
