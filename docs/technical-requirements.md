# Technical Requirements

## Idempotency

Every inbound webhook, manual sync, refund, stock movement, and gift card operation must have an idempotency key.

Recommended key format:

```text
{tenant}:{source_system}:{event_type}:{source_event_id_or_hash}
```

Store the key on `events`, `stock_ledger`, `gift_card_transactions`, and mapping records where relevant.

Inbound webhook events must be stored once and processed once. If the same idempotency key is received again, the platform should return `202 Accepted` with `duplicate_accepted` and must not dispatch another processing job.

Payload hashes must sort nested arrays recursively before JSON encoding so logically identical payloads produce the same idempotency hash.

## Queue Design

- Webhook controllers should validate and store events quickly.
- Heavy processing must run in queues.
- Queue jobs should be tenant-aware.
- Jobs must be safe to retry.
- Use Redis queues first, with database queues as acceptable local fallback.

## Retry and Backoff

- Retry transient API failures with exponential backoff.
- Do not retry validation errors without a data change.
- Stop retries after a configured maximum and expose the failure in the portal with merchant-friendly language.
- Keep manual retry available for failed events.

## Rate Limiting and Batch Processing

- Respect WooCommerce, Front, and plugin endpoint rate limits.
- Product sync for 70,000 products must be paginated and checkpointed.
- Historic order handling for 80,000 orders should default to on-demand lookup, not full import into Front.

## API Error Handling

- Store external request correlation IDs if available.
- Store sanitized error messages.
- Never store raw secrets, tokens, or full Authorization headers.
- Classify errors as transient, permanent, validation, authentication, or vendor limitation.

## Webhook Verification

- Public webhook URLs use opaque path tokens from `webhook_endpoints.path_token`, not organization slugs.
- Verify WooCommerce webhooks when signatures/secrets are configured.
- Verify Front webhooks if Front supports signing/secrets.
- If Front cannot sign webhooks, use secret callback URLs or token headers and document the limitation.
- Store sanitized webhook metadata for debugging, including relevant headers, source IP, received time, event type, and source event ID.
- Redact authorization headers, API keys, cookies, signatures, tokens, and secrets before storing payloads or metadata.

## Front API Schema Source

- The primary Front endpoint schema source is `docs/vendor/front-systems/openapi/frontsystems.openapi.json`.
- Use `docs/vendor/front-systems/front-api-endpoint-summary.md` for a human-readable overview.
- Do not assume an endpoint is enabled for Lilleprinsen until the relevant Front module and staging access are confirmed.

## Product Mapping Terms

- Product mappings use `woo_item_key`, `gtin`, `external_sku`, `front_product_ext_id`, `front_identity`, and `front_stock_id`.
- Prefer `gtin` over legacy `ean` naming.
- Do not rely on unique indexes that contain nullable Woo variation IDs.

## Encrypted Credentials

- Store credentials encrypted at rest.
- Use Laravel encryption or a dedicated secret manager later.
- Do not store secrets in code, docs, commits, logs, or screenshots.
- Rotate credentials per tenant.
- Phase 1 connection setup stores only redacted credential hints in the authenticated portal.
- WooCommerce and Front Systems connection tests must remain read-only.
- WooCommerce connection testing uses `GET /wp-json/wc/v3/system_status`.
- Front Systems connection testing uses `GET /api/Environment`.
- Front Systems may optionally call `GET /api/Stores` after a successful environment check.
- Connection test states are `success`, `failed`, and `skipped`.
- Store only minimal diagnostics: HTTP status, response time, safe error text, checked timestamp, and safe Front store metadata.
- Do not store full connection test response bodies.
- Safe Front store metadata is limited to store name, store ID, stock ID, currency, and time zone.
- Connection test actions must not print or return secret values.

## WooCommerce Plugin Adapter

- The WooCommerce plugin must remain thin.
- The plugin may expose read-only diagnostics and signed adapter endpoints.
- The plugin health endpoint is `GET /wp-json/omnibridge/v1/health` and must not require secrets.
- The signed connection test endpoint is `GET /wp-json/omnibridge/v1/connection-test`.
- Signed plugin requests use:
  - `X-Omnibridge-Timestamp`
  - `X-Omnibridge-Signature`
  - HMAC-SHA256 over `METHOD + "\n" + ROUTE + "\n" + TIMESTAMP`
- The signed endpoint must reject missing secrets, missing signature headers, expired timestamps, and invalid signatures.
- Plugin test endpoints must return no secrets, customer data, order data, full product payloads, or raw credentials.
- Plugin test endpoints must report `read_only=true` and `writes_performed=false`.
- The Laravel portal may run a signed WooCommerce plugin adapter test from the WooCommerce connection using the encrypted `plugin_shared_secret` credential.
- The portal plugin adapter test must call only `GET /wp-json/omnibridge/v1/connection-test`.
- The portal plugin adapter test must not call WooCommerce write endpoints, Front endpoints, product sync endpoints, stock endpoints, order endpoints, refund endpoints, or gift card endpoints.
- Portal-side plugin adapter test results may store safe metadata such as plugin version, WooCommerce version, currency, and boolean capability flags.
- The plugin may store lightweight product metadata for future platform-driven sync eligibility and status.
- Product meta saves must use WordPress/WooCommerce permission and nonce checks.
- Product bulk actions must check per-product edit capability before updating local metadata.
- The plugin must not call Front Systems.
- The plugin must not run catalog sync jobs.
- The plugin must not scan the full product catalog.
- The plugin must not update prices, stock, orders, refunds, gift cards, customers, or Front data.

## Read-Only Discovery

- Discovery actions use the same safety gate as live connection tests: `OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP`.
- When the flag is `false`, discovery must return `skipped` and make no HTTP requests.
- Discovery actions are authenticated, tenant-scoped, and grouped under the Testing Lab rather than normal owner navigation.
- Before the first live read-only test, run `php artisan omnibridge:preflight-readonly`.
- WooCommerce product discovery uses `GET /wp-json/wc/v3/products` with `per_page=10`, `page=1`, and `status=publish`.
- WooCommerce variation discovery uses `GET /wp-json/wc/v3/products/{productId}/variations` with `per_page=10` and `page=1` for variable products in the capped product sample.
- WooCommerce variation discovery is capped to a small number of variable parents and must never become a full catalog scan.
- Front store discovery uses `GET /api/Stores`.
- Front product discovery uses `POST /api/Product` as a read-only search/listing endpoint according to the Front OpenAPI spec, with `pageSize=10`, `pageSkip=0`, `isWebAvailable=true`, `isDiscontinued=false`, `excludeDeleted=true`, `includeEmptyGTINs=false`, `includeStockQuantity=false`, and `includeAlternativeIdentifiers=true`.
- Front product discovery must keep `pageSize <= 10` and must not accept UI/request overrides in this phase.
- Do not confuse Front `POST /api/Product` discovery with `/api/products`, which is the product CRUD endpoint.
- WooCommerce product discovery must keep `per_page <= 10` and must not accept UI/request overrides in this phase.
- Woo readiness reports may use sanitized Woo product and variation samples to mark rows as Ready, Needs attention, or Blocked.
- Woo readiness reports are advisory only and must not create sync runs or final mappings by themselves.
- Store only sanitized discovery snapshots in `connection_discovery_snapshots`.
- Keep only the latest 5 snapshots per connection and discovery type. Delete older snapshots.
- `connection_discovery_snapshots` is not long-term product storage.
- Do not store full product descriptions, customer data, order data, cost prices, raw response bodies, API keys, or Authorization headers.
- Discovery does not write to WooCommerce or Front and does not trigger sync jobs.
- Live read-only connection tests and discovery actions must create an audit log with user ID, organization ID, connection ID, action type, source system, endpoint group, status, checked timestamp, live HTTP flag, and production-write flag.
- Audit logs must not include credentials, full secret URLs, or response bodies.

## Mapping Preview

- Mapping preview compares sanitized discovery samples only.
- Match priority is Woo detected GTIN/EAN to Front product size GTIN, then Woo SKU to Front `externalSKU`, then Woo SKU to Front `identity`.
- Woo GTIN/EAN detection should mark confidence as `exact_known_field`, `common_field`, or `none`.
- Known Lilleprinsen candidate fields are `Zettle_barcode`, `iZettle_barcode`, `_Zettle_barcode`, and `_iZettle_barcode`.
- Detected GTIN/EAN values are candidates only and must be confirmed before final mapping. Multiple GTIN/EAN candidates must be shown as a warning instead of silently resolving the conflict.
- Mapping preview rows must not be written to the final `product_mappings` table until a separate explicit sync/mapping feature is implemented.

## 10-Product Mapping PoC

- The `/mapping/product-poc` page must be authenticated.
- The page and plan action must use stored `connection_discovery_snapshots` only and must not make external HTTP calls.
- A WooCommerce product discovery snapshot is required. A Front product discovery snapshot is optional for Woo-only readiness planning; if missing, Front match status must be clearly marked as `front_sample_missing`.
- The plan action must reject more than 10 selected WooCommerce products or variations.
- Selection keys must be explicit Woo item keys such as `product:123` or `variation:456`.
- Variation preview rows should inherit parent product name, category, brand, and image candidates from the same stored WooCommerce discovery snapshot. Variation attributes should become the proposed Front size label.
- Generated plans are stored in `product_sync_preview_plans`, not final mapping or sync history.
- The preview plan must not write to `product_mappings`.
- Block selected products or variations with missing name, missing SKU, missing both SKU and GTIN/EAN candidate, duplicate selected SKU/GTIN, missing variation parent context, or no price candidate.
- Missing GTIN/EAN should be a warning when SKU exists unless the active sync profile explicitly requires GTIN/EAN.
- Variable parent products may be previewed but should warn that sellable variation rows are usually better Front POS candidates.
- Warn, but do not block, for missing brand, missing category, missing sale price, out-of-stock status, `manage_stock=false`, no Front sample match, uncertain category mapping, or uncertain brand mapping.
- Proposed Front fields are candidates only. Group/subgroup, brand source, size label, product number/variant strategy, sale price handling, and primary identifier strategy must be marked `NEEDS_CONFIRMATION`.
- This phase must not use Front `/api/products`, `POST /api/PricelistV2`, `POST /api/Stock/adjust`, `PUT /api/Sale`, `POST /api/OmniChannel`, or any WooCommerce write endpoint.

## Product Sync Foundation

- Product sync must be selected/opt-in first. Do not blindly sync the full 70,000-product catalog.
- Product sync profiles define mode, limits, inclusion rules, required fields, price strategy, and stock strategy.
- Default mode is `preview_only`.
- Production mode must not be selectable unless `OMNIBRIDGE_ALLOW_PRODUCTION_WRITES=true`.
- Preview runs are local planning records only and must not call external APIs.
- Sync run items store selected products or variations only, not the whole catalog.
- Future large-catalog scanning must be background-job based, paginated, and incremental.
- Owner pages should use plain-language status. Testing workflows belong in the Testing Lab. Technical details belong in Advanced.

## Audit Trail

Audit these actions:

- Credential changes
- Webhook endpoint changes
- Manual retries
- Manual resyncs
- Production write enablement
- Refund, stock, and gift card operations

## PII Minimization

- Store only customer data needed for matching and order history.
- Prefer WooCommerce IDs and external IDs over full duplicated profiles.
- Redact unnecessary personal data in logs.
- Define retention rules before production launch.

## Staging-First Policy

- All development and first proof of concept work must use staging/test credentials.
- Production writes are disabled unless `OMNIBRIDGE_ALLOW_PRODUCTION_WRITES=true`.
- Any production enablement must be explicit, audited, and reversible.
- Live HTTP connection checks are disabled unless `OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=true`.
- Connection checks must be read-only. They must not create, update, refund, sync, reserve, redeem, or delete data.
- Even when live HTTP connection checks are enabled, no product sync, stock sync, order import, refund, gift card, or omnichannel write may be triggered by a connection test.
- Do not use production credentials until staging credentials and read-only tests are verified.

## WooCommerce to Front Product Sync Foundation

- The production goal is all relevant WooCommerce products and variations synced to Front Systems.
- Initial full sync must be batched, queue-based, resumable, and checkpointed.
- The UI must not load all products or variations at once.
- Sync runs must store product/variation-level status in `product_sync_run_items`.
- Incremental WooCommerce changes should later create deduplicated `product_sync_events`.
- Failed items must be retryable without rerunning a whole catalog.
- Reconciliation runs should later detect missing, outdated, or failed products.
- Product sync profiles must stay `preview_only` by default.
- Production mode and actual writes remain disabled until a separate launch checklist and write implementation exist.
- This phase must not call Front product CRUD, price list, stock adjust, or WooCommerce write endpoints.

## Health Checks

- `GET /health/live` returns application liveness without requiring database access.
- `GET /health/ready` verifies application and database readiness.
- `GET /health` is kept for compatibility and behaves like readiness.
- Hosted platforms should generally use `/health/ready` for traffic readiness and `/health/live` for process liveness.

## Observability

- Structured logs with tenant ID, event ID, job ID, and correlation ID.
- Portal views for failed events, stale syncs, and queue health.
- Later: external error reporting and uptime checks.

## Backup and Restore

- PostgreSQL backups are required in hosted environments.
- Redis queues should be treated as recoverable from persisted events where possible.
- Document restore testing before production.

## Data Retention

Initial recommendation:

- Keep audit logs for at least 12 months.
- Keep event payloads only as long as operationally needed.
- Redact or archive old payloads with PII.
- Keep mapping records while tenant remains active.
