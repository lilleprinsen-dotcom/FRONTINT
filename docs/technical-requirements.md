# Technical Requirements

## Idempotency

Every inbound webhook, manual sync, refund, stock movement, and gift card operation must have an idempotency key.

Recommended key format:

```text
{tenant}:{source_system}:{event_type}:{source_event_id_or_hash}
```

Store the key on `events`, `stock_ledger`, `gift_card_transactions`, and mapping records where relevant.

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

- Verify WooCommerce webhooks when signatures/secrets are configured.
- Verify Front webhooks if Front supports signing/secrets.
- If Front cannot sign webhooks, use secret callback URLs or token headers and document the limitation.

## Encrypted Credentials

- Store credentials encrypted at rest.
- Use Laravel encryption or a dedicated secret manager later.
- Do not store secrets in code, docs, commits, logs, or screenshots.
- Rotate credentials per tenant.

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

