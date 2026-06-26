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
- [x] No real credentials are committed.
- [x] No real WooCommerce or Front Systems API writes are implemented yet.

Notes:

- Docker verification is blocked in environments where `docker` is not installed.
- Run `./scripts/verify-platform-scaffold.sh` before starting real integration work.
- Keep production writes disabled unless explicitly approved and audited.
