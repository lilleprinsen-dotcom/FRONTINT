# First Proof of Concept Checklist

Run all tests in staging or sandbox only.

## 0. Read-Only Connection Verification

- Keep `OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=false`.
- Create WooCommerce and Front Systems connections with dummy values.
- Confirm both connection tests return skipped/safe mode and no external HTTP requests are made.
- Enable `OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=true` only with staging/test credentials.
- Confirm WooCommerce responds through `GET /wp-json/wc/v3/system_status`.
- Confirm Front responds through `GET /api/Environment`.
- If available, confirm Front store metadata from `GET /api/Stores` shows store name, store ID, stock ID, currency, and time zone.
- Confirm no product, price, stock, order, refund, gift card, or omnichannel writes occur.

## 0.1 Read-Only Product Discovery and Mapping Preview

- Keep `OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=false` and confirm WooCommerce/Front discovery actions return skipped/safe mode.
- Enable `OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=true` only with staging/test credentials.
- Confirm WooCommerce product discovery calls only `GET /wp-json/wc/v3/products` with a 10-product limit.
- Confirm WooCommerce variation discovery calls only `GET /wp-json/wc/v3/products/{productId}/variations` for variable products in the capped product sample.
- Confirm the Woo readiness report shows Ready, Needs attention, and Blocked rows for sampled products/variations.
- Confirm Front store discovery calls only `GET /api/Stores`.
- Confirm Front product discovery calls only `POST /api/Product` with a 10-product read-only search body.
- Confirm `POST /api/Product` is treated as the Front OpenAPI read-only product listing endpoint and is not confused with `/api/products` CRUD.
- Confirm no writes, sync jobs, stock updates, price updates, order creation, refunds, gift card operations, or omnichannel actions occur.
- Review detected WooCommerce GTIN/EAN candidate fields.
- Confirm candidate fields such as `Zettle_barcode`, `iZettle_barcode`, `_Zettle_barcode`, and `_iZettle_barcode` before final mapping.
- Review mapping preview matches by GTIN first, then Woo SKU to Front `externalSKU`, then Woo SKU to Front `identity`.
- Confirm preview rows are not saved as final `product_mappings`.

## 0.2 10-Product Mapping PoC Preparation

- Open `/mapping/product-poc` after WooCommerce and Front product discovery have both succeeded.
- Confirm the page shows the safety banner: preview only, no products, prices, stock, or orders are written.
- Select no more than 10 WooCommerce products.
- Generate the preview sync plan.
- Confirm blocked products show missing SKU, missing GTIN/EAN, duplicate SKU/GTIN, missing variation parent context, or missing price issues.
- Confirm warnings show missing brand/category, missing sale price, stock status concerns, uncertain category/brand mapping, and no Front sample match.
- Confirm product and variation handling is preview-only. Variation writes are not implemented yet.
- Confirm GTIN/EAN candidates, including `Zettle_barcode`, `iZettle_barcode`, `_Zettle_barcode`, and `_iZettle_barcode`, are treated as candidates requiring confirmation.
- Confirm the generated plan is stored only in `product_sync_preview_plans`.
- Confirm no rows are written to `product_mappings`.
- Confirm no WooCommerce or Front API write endpoints are called.

## 0.3 Product Sync Preview Run

- Open `/product-sync`.
- Confirm the current mode is Preview only.
- Confirm production writes are disabled.
- Create a preview run from the latest mapping PoC plan.
- Confirm run items are created locally with Ready or Needs attention status.
- Confirm the run structure can track products and variations without loading a full catalog into the UI.
- Confirm sync runs are viewed through paginated/filterable pages.
- Confirm future full-catalog sync will be batched and checkpointed, not run as one uncontrolled 70,000-product job.
- Confirm no external APIs are called.
- Confirm no rows are written to final `product_mappings`.
- Confirm Advanced contains technical settings and normal pages stay store-owner friendly.

## 1. One Woo Product to Front

- Pick one simple WooCommerce product.
- Confirm SKU, GTIN, category, price, stock, and the intended `woo_item_key`.
- Mark eligible for Front POS.
- Sync to Front.
- Confirm name, SKU/GTIN, price, status, stock, and any Front `productExtId` or `externalSKU` mapping in Front.

## 2. One Front Sale to Woo Order

- Sell one staging/test product in Front.
- Receive Front sale webhook or fetch sale through API.
- Confirm the public webhook URL uses an opaque path token, not an organization slug.
- Create WooCommerce order with source `front_pos`.
- Store Front transaction ID.
- Confirm stock is not double-deducted.
- Confirm Woo emails are controlled as configured.

## 3. One Woo Refund Through Dintero/Stripe Test

- Create a staging Woo order paid through Dintero or Stripe.
- Create a WooCommerce refund through the supported API path.
- Confirm gateway refund result.
- Store refund metadata and order notes.

## 4. One Pickup/Reservation to Front and Status Back

- Create a WooCommerce pickup or reservation order.
- Send it to Front.
- Confirm employee-facing state for paid vs unpaid.
- Update status in Front.
- Confirm WooCommerce status updates correctly.

## 5. One WebToffee Gift Card Test

- Create or use a staging gift card.
- Check balance through plugin adapter.
- Redeem/debit a small amount.
- Reverse or credit the amount.
- Confirm transaction log and concurrency protection approach.
