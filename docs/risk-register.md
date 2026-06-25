# Risk Register

## Front Module Availability

Risk: BOPIS, ROPIS, BODFS, Endless Aisle, stock count, reservations, or gift card features may depend on contract modules.

Mitigation: Confirm modules and sandbox access before implementation.

## Front UI Limitations

Risk: Front POS may not support every line-level workflow or display need.

Mitigation: Use documented configuration and APIs only. Mark unknowns as `NEEDS_FRONT_CONFIRMATION`.

## Dintero/Stripe Refund Behavior

Risk: Refund behavior through Woo REST-created refunds may differ by gateway setup.

Mitigation: Test Dintero and Stripe in staging before building return workflows.

## WebToffee API Availability

Risk: WebToffee may not expose official REST APIs for all needed gift card operations.

Mitigation: Build a thin WooCommerce adapter plugin using confirmed hooks/functions.

## Product Volume

Risk: 70,000 products require careful batch processing.

Mitigation: Use pagination, queues, checkpoints, validation, and reconciliation.

## WooCommerce Webhook Reliability

Risk: WooCommerce webhooks can fail or become disabled.

Mitigation: Event dashboard, retry tools, daily reconciliation, and webhook health checks.

## Front Webhook Reliability

Risk: Front webhooks may not retry automatically.

Mitigation: Confirm behavior with Front. Use polling/reconciliation where needed.

## Gift Card Concurrency

Risk: Concurrent redemption can double-spend a gift card.

Mitigation: Use idempotency keys, database locks, and adapter-level transaction checks.

## Stock Double Deduction

Risk: POS imports and Woo order creation can both reduce stock.

Mitigation: Explicit order source metadata, stock ledger, and tested stock-control rules.

## GDPR and Customer Data

Risk: Storing too much customer data increases compliance risk.

Mitigation: Minimize PII and prefer hashes/external IDs where possible.

## SMS Costs

Risk: Front SMS pricing or external SMS provider costs may be high.

Mitigation: Keep notifications provider-replaceable and start with email/platform notifications.

## Historic Order Volume

Risk: 80,000 historic orders are too heavy to import everywhere by default.

Mitigation: Prefer on-demand lookup for older WooCommerce orders.

## Rate Limits

Risk: WooCommerce, Front, or plugin endpoints may throttle requests.

Mitigation: Configurable rate limits, backoff, batch windows, and sync checkpoints.

## Product Data Quality

Risk: Missing SKU, EAN, category, price, or variant metadata can block Front sync.

Mitigation: Validation report and merchant-friendly fix list.

