# Staging Batch Product Sync v1

This is the first usable WooCommerce to Front Systems product write flow for staging.

It is intentionally limited:

- Maximum 100 selected WooCommerce products or variations per batch.
- Uses the latest local WooCommerce discovery snapshot.
- Variations are first-class sync candidates.
- Selecting a WooCommerce variable parent writes one Front product payload with discovered Woo variations as `productSizes`.
- Validates each item before writing.
- Writes only product create/update payloads to Front.
- Does not write to WooCommerce.
- Does not write stock as part of this product-write test.
- Does not write sale prices as part of this product-write test.
- Does not create orders, refunds, gift cards, or omnichannel records.
- Does not sync the full catalog.

## Required Settings

Before running a staging batch:

1. WooCommerce staging connection exists.
2. Front staging connection exists with encrypted API key.
3. Product sync profile mode is `staging_batch` or `limited_write_test`. Creating a staging batch from the Product Sync page will move the default `preview_only` profile to `staging_batch`.
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

WooCommerce sale price is not part of this fast product-write test. It stays visible as a future PriceListV2 candidate only.

Do not overwrite regular price with sale price.

## Stock Behavior

Stock sync is not part of this fast product-write test.

Do not use `POST /api/Stock/adjust` while testing the product payloads unless you are explicitly testing the separate stock flow.

## How To Test

1. Start the local portal.
2. Confirm WooCommerce staging connection works.
3. Confirm Front staging connection exists with API key.
4. Run WooCommerce product discovery.
5. Open `Product Sync`.
6. Select WooCommerce products or variations from the staging batch section.
7. Use `Select first 10`, `Select first 25`, or `Select max 100` for quick batches.
8. Create a staging batch run.
9. Open the run detail page.
10. Click `Run staging batch sync`.
11. Watch item statuses change to `synced` or `failed`.
12. Open `Inspect Front request/response` on each row to copy the safe request/response summary.
13. Use `Retry failed items` after fixing any failed data or Front validation issue.
14. Use `Resync this item` on a mapped row to manually send the product to Front again.

## Specific Staging Tests

### 1 simple product

1. Open `Product Sync`.
2. Select one simple product row with a SKU and price.
3. Create the staging batch run.
4. Open the run.
5. Click `Run staging batch sync`.
6. Confirm the row becomes `synced` or inspect the Front error summary.

### 1 variable product

1. Open `Product Sync`.
2. Select one variable parent product row.
3. Create the staging batch run.
4. Open the run.
5. Confirm the row says multiple sizes in `Inspect Front request/response` after sync.
6. Click `Run staging batch sync`.

The variable parent payload should include the discovered Woo variations as Front `productSizes`.

### 25 products

1. Open `Product Sync`.
2. Click `Select first 25`.
3. Create the staging batch run.
4. Open the run.
5. Click `Run staging batch sync`.
6. Review synced and failed rows. Failed rows should not stop the rest of the batch.

### Manual resync

1. Open a run with a previously synced row.
2. Click `Resync this item`.
3. OmniBridge should use the existing `product_mappings` row and update Front with `PUT /api/products/{productId}`.

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

The run page includes an expandable `Inspect Front request/response` section per row so staging feedback can be copied into bug reports.

Raw API keys and full response bodies are not stored.

## Still Not Implemented

- Full catalog sync for 70,000 products.
- Background cursor-based catalog scan.
- Product deletion handling.
- WooCommerce writes.
- Order/refund/gift-card/omnichannel sync.
- Automatic retry backoff workers.
