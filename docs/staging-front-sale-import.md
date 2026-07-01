# Staging Front Sale and Return Handling

Front POS sales should not flood WooCommerce orders.

The default production-minded behavior is:

1. Front sale or return arrives.
2. OmniBridge matches each line to a synced Woo product or variation.
3. Sales reduce WooCommerce stock immediately.
4. Returns add WooCommerce stock back immediately.
5. The transaction stays visible in the OmniBridge portal and Woo plugin POS Sales page foundation.
6. A WooCommerce order is created only if an admin manually chooses it for a sale.

## What It Does

- Captures Front sale-like and return-like webhook events.
- Creates a local Front POS transaction record.
- Detects sale vs return from event type, payload type/status/reason, negative totals, or negative line quantities.
- Matches lines to existing `product_mappings`.
- Automatically queues WooCommerce stock adjustment for matched sales and returns.
- Reduces Woo stock by the quantity sold in Front.
- Increases Woo stock by the quantity returned in Front.
- Records stock movements in `stock_ledger`.
- Shows Front transactions in the portal without creating Woo orders by default.
- Allows a manual `Create Woo order` action for selected sales only.
- Imports customer billing data into the optional Woo order when Front payload provides it.
- Marks optional Woo orders with stock already adjusted metadata to avoid double stock reduction.
- Shows results in Testing Log.

## What It Does Not Do Yet

- Does not write anything to Front.
- Does not auto-create Woo orders for every POS sale.
- Does not create Woo refunds.
- Does not handle exchanges.
- Does not handle gift cards.
- Does not handle omnichannel pickup/reservation orders.
- Does not merge customers yet.

## Matching Rules

Transaction lines are matched against synced product mappings using:

1. GTIN/EAN
2. external SKU
3. Woo SKU
4. Front identity
5. Front product ID

If any line cannot be matched, stock adjustment is blocked until product mappings are fixed.

## WooCommerce Stock Behavior

For each matched line:

1. Read current Woo product or variation stock.
2. Subtract the Front sold quantity for sales.
3. Add the returned quantity for returns.
4. Write the new stock quantity back to WooCommerce.
5. Record the movement in `stock_ledger`.

The same Front transaction cannot adjust stock twice.

## Optional Woo Order Behavior

If an admin manually creates a Woo order for a Front sale:

- `status=completed`
- `set_paid=true`
- `payment_method=paid_in_front`
- line item `product_id` and `variation_id` from product mappings
- billing/customer data if present in the Front sale payload
- metadata linking the order to Front sale and receipt IDs
- `_omnibridge_front_stock_already_adjusted=yes`
- `_order_stock_reduced=yes`

The Woo plugin also marks OmniBridge Front POS orders as stock already handled when it sees the OmniBridge metadata.

Front returns are not imported as WooCommerce orders in this flow. They only adjust WooCommerce stock.

## How To Test

1. Sync at least one Woo product or variation to Front so `product_mappings` exists.
2. Send or simulate a Front sale webhook payload containing matching GTIN/SKU/identity.
3. Open `Front Sales`.
4. Confirm the sale appears.
5. Confirm stock status becomes `adjusted`.
6. Confirm no Woo order exists unless you press `Create Woo order manually`.
7. Open `Testing Log`.
8. Confirm the stock adjustment says `Worked`.
9. If needed, manually create the Woo order and confirm it is marked `Paid in Front POS`.
10. Send or simulate a Front return webhook payload with a matching line.
11. Confirm Woo stock increases and no Woo order button is offered for the return.

## Front Confirmation Still Needed

`NEEDS_FRONT_CONFIRMATION`: Exact Front webhook event names and sale/return payload shapes still need confirmation from the real Front staging account. The current mapper supports several likely field names so staging can move forward.
