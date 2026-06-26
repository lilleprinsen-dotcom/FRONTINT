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
- Stop retries after a configured maximum and expose the failure in the dashboard.
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
- Phase 1 connection setup stores only redacted credential hints in the dashboard.
- WooCommerce and Front Systems connection tests must remain read-only.
- WooCommerce connection testing uses `GET /wp-json/wc/v3/system_status`.
- Front Systems connection testing uses `GET /api/Environment`.
- Front Systems may optionally call `GET /api/Stores` after a successful environment check.
- Connection test states are `success`, `failed`, and `skipped`.
- Store only minimal diagnostics: HTTP status, response time, safe error text, checked timestamp, and safe Front store metadata.
- Do not store full connection test response bodies.
- Safe Front store metadata is limited to store name, store ID, stock ID, currency, and time zone.
- Connection test actions must not print or return secret values.

## Read-Only Discovery

- Discovery actions use the same safety gate as live connection tests: `OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP`.
- When the flag is `false`, discovery must return `skipped` and make no HTTP requests.
- Discovery actions are dashboard-authenticated and tenant-scoped.
- WooCommerce product discovery uses `GET /wp-json/wc/v3/products` with `per_page=10`, `page=1`, and `status=publish`.
- Front store discovery uses `GET /api/Stores`.
- Front product discovery uses `POST /api/Product` as a read-only search endpoint with `pageSize=10`, `pageSkip=0`, `isWebAvailable=true`, `isDiscontinued=false`, `excludeDeleted=true`, `includeEmptyGTINs=false`, `includeStockQuantity=false`, and `includeAlternativeIdentifiers=true`.
- Store only sanitized discovery snapshots in `connection_discovery_snapshots`.
- Do not store full product descriptions, customer data, order data, cost prices, raw response bodies, API keys, or Authorization headers.
- Keep only the latest few snapshots per connection/discovery type.
- Discovery does not write to WooCommerce or Front and does not trigger sync jobs.

## Mapping Preview

- Mapping preview compares sanitized discovery samples only.
- Match priority is Woo detected GTIN/EAN to Front product size GTIN, then Woo SKU to Front `externalSKU`, then Woo SKU to Front `identity`.
- Woo GTIN/EAN detection should mark confidence as `exact_known_field`, `common_field`, or `none`.
- Mapping preview rows must not be written to the final `product_mappings` table until a separate explicit sync/mapping feature is implemented.

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

## Health Checks

- `GET /health/live` returns application liveness without requiring database access.
- `GET /health/ready` verifies application and database readiness.
- `GET /health` is kept for compatibility and behaves like readiness.
- Hosted platforms should generally use `/health/ready` for traffic readiness and `/health/live` for process liveness.

## Observability

- Structured logs with tenant ID, event ID, job ID, and correlation ID.
- Dashboard views for failed events, stale syncs, and queue health.
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
