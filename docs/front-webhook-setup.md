# Front Webhook Setup

Front webhooks are the next operational bridge between Front POS and OmniBridge.

The setup flow is intentionally explicit:

1. Add or verify the Front Systems connection.
2. Enable live staging HTTP calls with `OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=true`.
3. Keep `OMNIBRIDGE_ALLOW_PRODUCTION_WRITES=false`.
4. Open the Front connection discovery page.
5. Click **Check Front setup** to read supported webhook types.
6. Select sale, return, and stock-related webhook types.
7. Click **Register selected Front webhooks**.

This registers Front callbacks to:

```text
/webhooks/front/{pathToken}
```

The public callback URL uses an opaque path token, not the organization slug.

## Front endpoints used

The implementation uses the stored Front OpenAPI spec as the contract source:

- `GET /api/WebhooksTypes` to discover available event types.
- `GET /api/Webhooks` to check existing webhook registrations.
- `POST /api/Webhooks` to create a missing registration.
- `PUT /api/Webhooks/{webhookId}` to update an existing registration.

The documented create/update payload is:

```json
{
  "event": "SaleCreated",
  "url": "https://example.com/webhooks/front/token"
}
```

`storeId` is nullable in the OpenAPI schema. OmniBridge does not set it yet.

## What still needs confirmation

- NEEDS_FRONT_CONFIRMATION: exact event names for POS sales.
- NEEDS_FRONT_CONFIRMATION: exact event names for POS returns.
- NEEDS_FRONT_CONFIRMATION: exact event names for stock count and stock adjustment events.
- NEEDS_FRONT_CONFIRMATION: whether webhook payloads are signed or can include a shared secret header.
- NEEDS_FRONT_CONFIRMATION: whether event types should be registered per store using `storeId`.

## Data stored locally

OmniBridge stores one row per selected Front webhook type in `front_webhook_registrations`.

Stored fields include:

- webhook type
- callback URL
- Front webhook ID
- status
- safe request/response summaries
- last error
- registration timestamp

API keys, secrets, raw response bodies, and customer/order/product payloads are not stored.
