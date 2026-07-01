# Staging Front Sale Import

This feature imports paid Front POS sales into WooCommerce as paid orders.

It is a staging workflow. It is meant to prove that Front sales can become WooCommerce order history and reduce WooCommerce stock through normal Woo order behavior.

## What It Does

- Captures Front sale-like webhook events.
- Creates a local Front sale import record.
- Matches sale lines to existing `product_mappings`.
- Shows matched and unmatched sale lines in the portal.
- Creates a WooCommerce order only when the user presses import.
- Uses payment method `paid_in_front` and title `Paid in Front POS`.
- Stores an `order_mapping` after success.
- Prevents duplicate order creation with an idempotency key.
- Shows results in Testing Log.

## What It Does Not Do Yet

- Does not write anything to Front.
- Does not handle refunds.
- Does not handle exchanges.
- Does not handle gift cards.
- Does not handle omnichannel pickup/reservation orders.
- Does not automatically import every sale without review.
- Does not merge customers yet.

## Matching Rules

Sale lines are matched against synced product mappings using:

1. GTIN/EAN
2. external SKU
3. Woo SKU
4. Front identity
5. Front product ID

If any line cannot be matched, the sale import is blocked until product mappings are fixed.

## WooCommerce Order Behavior

The Woo order payload is created with:

- `status=completed`
- `set_paid=true`
- `payment_method=paid_in_front`
- line item `product_id` and `variation_id` from product mappings
- metadata linking the order to Front sale and receipt IDs

WooCommerce remains the master for orders and stock history. The imported order is expected to reduce WooCommerce stock through normal Woo order stock behavior.

## How To Test

1. Sync at least one Woo product or variation to Front so `product_mappings` exists.
2. Send or simulate a Front sale webhook payload containing matching GTIN/SKU/identity.
3. Open `Front Sales`.
4. Open the captured sale.
5. Confirm all sale lines are matched.
6. Press `Import to WooCommerce`.
7. Open `Testing Log`.
8. Confirm the import says `Worked`.
9. Check WooCommerce for a paid order marked `Paid in Front POS`.

## Front Confirmation Still Needed

`NEEDS_FRONT_CONFIRMATION`: Exact Front webhook event name and sale payload shape still need confirmation from the real Front staging account. The current mapper supports several likely field names so staging can move forward.
