# Staging Batch Product Sync v1

This is the first usable WooCommerce to Front Systems product write flow for staging.

It is intentionally limited:

- Maximum 100 selected WooCommerce products or variations per batch.
- Uses the latest local WooCommerce discovery snapshot.
- Variations are first-class sync candidates.
- Validates each item before writing.
- Writes only product create/update payloads to Front.
- Does not write to WooCommerce.
- Does not write stock.
- Writes sale prices only through the separate explicit PriceListV2 action after products are synced.
- Does not create orders, refunds, gift cards, or omnichannel records.
- Does not sync the full catalog.

## Required Settings

Before running a staging batch:

1. WooCommerce staging connection exists.
2. Front staging connection exists with encrypted API key.
3. Product sync profile mode is `staging_batch` or `limited_write_test`.
4. `OMNIBRIDGE_ALLOW_PRODUCTION_WRITES=false`.
5. WooCommerce product discovery has been run.

Production mode is not required and should remain disabled.

## Front Endpoint Decision

For each selected item, OmniBridge decides create vs update in this order:

1. Existing `product_mappings` row for the Woo item key.
2. Front lookup by generated external id using `GET /api/products/{productId}`.
3. Front lookup by GTIN using `GET /api/Product/gtin/{gtin}`.
4. Create new Front product using `POST /api/products`.

If an existing Front product is found, OmniBridge updates it with:

`PUT /api/products/{productId}`

The generated Front external id uses the Woo item key:

- `product:123` -> `woo-product-123`
- `variation:456` -> `woo-variation-456`

WooCommerce product and variation IDs are the stable mapping identity. SKU and GTIN/EAN are sent to Front as product fields and can change later. If SKU or GTIN/EAN changes in WooCommerce, the next staging batch/update should still find the same Front product by mapping or generated `extId` and update those fields.

## Price Behavior

Staging batch v1 sends WooCommerce regular price as the Front product `price`.

WooCommerce sale price is written through the separate sale price action on the run page. That action calls `POST /api/PricelistV2`, uses the configured sale price list name, and only processes already-synced product run items.

Do not overwrite regular price with sale price.

Sale price sync:

- prefers Front `productExtId`
- falls back to GTIN if no Front ext id is stored
- stores separate sale-price status on the run item
- is retryable without rerunning product sync

## How To Test

1. Start the local portal.
2. Confirm WooCommerce staging connection works.
3. Confirm Front staging connection exists with API key.
4. Run WooCommerce product discovery.
5. Open `Product Sync`.
6. Set the sync profile mode to `staging_batch`.
7. Select up to 100 WooCommerce products or variations from the staging batch section.
8. Create a staging batch run.
9. Open the run detail page.
10. Click `Run staging batch sync`.
11. Watch item statuses change to `synced` or `failed`.
12. Use `Retry failed items` after fixing any failed data or Front validation issue.

## Result Storage

Successful items update:

- `product_sync_run_items.sync_status`
- Front identifiers on the run item
- safe request/response summaries
- `product_mappings`
- audit logs

Failed items store:

- `sync_status=failed`
- `last_error`
- sanitized response summary

Raw API keys and full response bodies are not stored.

## Still Not Implemented

- Full catalog sync for 70,000 products.
- Background cursor-based catalog scan.
- Product deletion handling.
- Stock writes.
- WooCommerce writes.
- Order/refund/gift-card/omnichannel sync.
- Automatic retry backoff workers.
