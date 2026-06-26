# WooCommerce to Front Product Sync Strategy

This document defines the production-safe product sync approach for OmniBridge.

## Why Not Sync 70,000 Products Blindly

Lilleprinsen has approximately 70,000 WooCommerce products. A blind full-catalog sync would risk heavy WooCommerce load, bad mappings reaching Front, API throttling, and confusing troubleshooting for a non-technical store owner.

The platform must prepare, validate, and sync selected products in controlled batches.

## Recommended Sync Model

- Opt-in selected products first.
- Batch-based sync with small visible groups.
- Queue-based processing for all future writes.
- Incremental changes only after selected products are proven.
- Nightly reconciliation for selected products, not blind full-catalog writes.
- Manual retry for failed items.
- Validation before every write.

## WooCommerce Load Reduction

- Fetch only required fields.
- Use pagination and small query limits.
- Use `modified_after` where possible.
- Avoid loading all meta for all products repeatedly.
- Cache discovery snapshots and preview plans.
- Use WooCommerce webhooks for product updates later.
- Avoid heavy Woo admin page queries.
- Run large catalog scanning as background jobs only.
- Keep the WooCommerce plugin thin with lightweight product meta.

The UI must never load all Woo products.

## Front API Load Strategy

- Use small write batches.
- Add rate limiting before real writes are enabled.
- Retry with backoff.
- Use idempotency keys for every write attempt.
- Write only changed products.
- Keep production writes disabled by default.

## Product Selection Strategy

Initial selection is manual through discovery and mapping preview. Later options:

- `_omnibridge_sync_to_front` / `sync_to_front` flag.
- Include by category, brand, or tag.
- Exclude discontinued or out-of-stock products if configured.
- Enforce max batch size and max products per run.

## Mapping Strategy

Use Woo product ID / variation ID, Woo SKU, GTIN/EAN candidate, Front `productExtId`, Front `identity`, Front `externalSKU`, and Front `productid` after creation.

Do not create final `product_mappings` until a selected-product write test is explicitly enabled.

## Validation Before Sync

Block products when required fields are missing:

- Product name.
- SKU.
- GTIN/EAN candidate when required.
- Price when required.
- Variable product when variations are not enabled.
- Duplicate selected SKU or GTIN.

Warn, but do not always block:

- Missing brand.
- Missing category.
- Missing sale price.
- Out-of-stock products.
- No Front match in the current sample.
- Unconfirmed category/group or brand strategy.

## Rollback and Disable Strategy

- Keep product sync profiles in `preview_only` by default.
- Disable production mode unless `OMNIBRIDGE_ALLOW_PRODUCTION_WRITES=true`.
- Provide a clear pause path with `draft`, `cancelled`, or `failed` run statuses.
- Keep all write attempts itemized so failed products can be retried individually later.
- Never use preview plans as final sync history.

## Portal Visibility

Store-owner pages must show current mode, selected products, last discovery, last mapping preview, latest sync run, ready products, needs-attention products, failed products, last successful sync, and clear warnings.

Advanced pages should contain webhook URLs, API/schema notes, raw event logs, audit logs, queue and retry details, and technical sync profile settings.

Normal pages should avoid jargon such as idempotency, payload, queue worker, API body, and webhook headers.

## Current Phase

Phase 5 creates product sync profiles, preview runs, run items, owner-friendly status pages, and advanced technical pages.

It does not write to WooCommerce or Front. It does not sync prices, stock, orders, refunds, gift cards, or omnichannel flows.
