# Current Progress Review

Last updated: 2026-07-01

This review compares the current OmniBridge implementation with the stored Front Systems OpenAPI file at:

`docs/vendor/front-systems/openapi/frontsystems.openapi.json`

## Built And Working In The Scaffold

- WooCommerce connection checks.
- WooCommerce plugin adapter health checks.
- Front Systems connection checks.
- WooCommerce product and variation discovery.
- WooCommerce readiness reporting.
- Front store discovery.
- Front product discovery through the documented read-only `POST /api/Product` endpoint.
- Product mapping preview and staging batch product sync foundation.
- Limited/staging Front product create/update flow.
- Woo product descriptions, tags, and image URLs included in Front product payload previews and staging writes.
- Sale price sync foundation through `POST /api/PricelistV2`.
- Woo-to-Front stock sync foundation through `POST /api/Stock/adjust`.
- Front POS sale capture.
- Front POS sale stock reduction in WooCommerce.
- Front POS return stock increase in WooCommerce.
- Manual Woo order creation for Front sales only.
- WooCommerce plugin page for manually imported POS orders.

## Useful Front API Features Now Surfaced

The Front OpenAPI includes setup endpoints that were useful but not previously visible in the portal:

- `GET /api/WebhooksTypes`
- `GET /api/WebhooksTypes/{webhookType}/schema`
- `GET /api/WebhooksEvents/{webhookId}`
- `POST /api/WebhooksEvents/{webhookId}/deliver/{webhookEventId}`
- `GET /api/Stock/settings`
- `GET /api/Stock/list`
- `GET /api/Stock/gtin/{id}`
- `GET /api/Stock/identity/{id}`

Implemented now:

- `Check Front setup` on the Front connection discovery page.
- It calls only:
  - `GET /api/WebhooksTypes`
  - `GET /api/Stock/settings`
  - `GET /api/Stock/list`
- It stores only compact, sanitized setup metadata.
- It shows review notes for missing sale, return, stock, and reservation webhook hints.

## Useful Front API Features Still Not Implemented

- Webhook registration through `POST /api/Webhooks`.
- Webhook event replay through `POST /api/WebhooksEvents/{webhookId}/deliver/{webhookEventId}`.
- Webhook schema inspection through `GET /api/WebhooksTypes/{webhookType}/schema`.
- POS sale polling or settlement reconciliation.
- Stock count batch reconciliation from `GET /api/Stock/count/batches`.
- Reservation flows through `/api/Stockreservation`.
- Omnichannel order flows through `/api/OmniChannel`.
- Gift card/voucher compatibility through `/api/Giftcard` and `/api/Voucher`.

## What Needs Front Confirmation

- Exact webhook type for POS sale.
- Exact webhook type for POS return.
- Whether stock changes and stock counts are available as webhooks.
- Whether Front webhook payloads contain GTIN, external SKU, identity, product ID, and quantity consistently.
- Whether webhook signing exists. If not, use secret callback URLs or token headers.
- Which stock ID or external stock ID maps to the Lilleprinsen physical stock.
- Whether sale price list behavior should use `productExtId`, GTIN, or another identifier.
- Whether the POS displays regular price and sale price as desired.
- Where Front POS displays product `description`, whether `internalDescription` is visible, and how image URLs appear for store staff.

## What You Should Review In The Portal

1. Open `Connections`.
2. Open the Front Systems connection.
3. Open `Discovery`.
4. Click `Check Front setup`.
5. Review:
   - Webhook types.
   - Stock locations.
   - Stock settings sample.
   - Any `NEEDS_FRONT_CONFIRMATION` notes.
6. Send the Testing Log results back to Codex after testing real Front credentials.

## Recommended Next Implementation

After `Check Front setup` is tested against real Front staging credentials:

1. Add webhook schema discovery for selected webhook types.
2. Add a guided Front webhook registration page.
3. Add a webhook replay/debug page using Front webhook event endpoints.
4. Add reconciliation views for stock count batches and failed stock movements.
