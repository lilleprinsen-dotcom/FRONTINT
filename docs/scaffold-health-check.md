# Scaffold Health Check

Use this checklist before starting real integration implementation.

- [x] Front OpenAPI file present at `docs/vendor/front-systems/openapi/frontsystems.openapi.json`.
- [x] `scripts/generate-front-client.sh` can find the OpenAPI file and print metadata.
- [x] PHP syntax passes in the current environment.
- [ ] Docker build pending/works.
- [ ] Composer install pending/works.
- [ ] Laravel app key generation pending/works.
- [ ] Database migrations pending/works.
- [x] Smoke and feature tests exist for health, auth, dashboard, organizations, and webhooks.
- [ ] Unit/feature tests pending local Docker verification.
- [x] Webhook duplicate handling is present.
- [x] Secret redaction tests are present.
- [x] Idempotency tests are present.
- [x] Product mapping uses `gtin`, `external_sku`, and `front_product_ext_id`.
- [x] Basic login and dashboard foundation present.
- [x] Organization and connection setup foundation present.
- [x] Connection credentials use encrypted model casts.
- [x] Connection tests are staging-safe by default.
- [x] No real credentials are committed.

Notes:

- This repository remains scaffold-first until Docker build, Composer install, migrations, and unit tests are verified in a complete local or CI environment.
- Keep production writes disabled unless explicitly approved and audited.
