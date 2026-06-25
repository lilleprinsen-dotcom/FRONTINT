# API Contracts

These endpoints are planned contracts. They are not implemented yet.

## Platform Endpoints

### POST /webhooks/woocommerce/{tenant}

Receives WooCommerce webhooks.

Requirements:

- Verify WooCommerce signature when configured.
- Store raw event in `events`.
- Use idempotency key from Woo event ID, resource ID, and payload hash.
- Queue processing.

### POST /webhooks/front/{tenant}

Receives Front Systems webhooks.

Requirements:

- Verify Front signature if supported.
- If signing is unavailable, use a secret URL token or token header.
- Store raw event in `events`.
- Mark uncertain payload semantics as `NEEDS_FRONT_CONFIRMATION`.

### GET /health

Returns app, database, queue, and dependency health.

### GET /dashboard

Returns or renders the minimal admin dashboard.

### POST /sync/products/run

Starts a batch product sync for the authenticated organization.

### POST /sync/products/{id}

Syncs one WooCommerce product or variation.

### POST /sync/stock/reconcile

Starts stock reconciliation between WooCommerce and Front.

### POST /orders/{id}/resync

Resyncs one order or mapping.

### POST /gift-cards/check

Checks WebToffee gift card balance through the WooCommerce plugin adapter.

### POST /gift-cards/redeem

Redeems or reserves a gift card amount.

Must include:

- Tenant
- Gift card code
- Amount
- Currency
- Idempotency key
- Source reference

### POST /gift-cards/reverse

Reverses a previous redemption.

### POST /gift-cards/credit

Credits a gift card or store credit balance.

## External API Clients

### WooCommerce REST API Client

Used for products, variations, orders, customers, refunds, metadata, stock updates, and webhook setup.

### Front Systems API Client

Used for products, POS sales, reservations, pickup orders, stock counts, fulfillment status, and gift card compatibility only where Front supports it.

All unclear Front behavior must be marked `NEEDS_FRONT_CONFIRMATION`.

### Dintero/Stripe Refunds

Prefer refunding through WooCommerce gateway behavior where possible. Direct provider API access should only be added if Woo gateway refund behavior is insufficient and confirmed.

### WebToffee Adapter

The Laravel platform calls signed WooCommerce plugin endpoints for gift card balance, redeem, reverse, credit, and transaction log behavior if no official WebToffee REST API is suitable.

