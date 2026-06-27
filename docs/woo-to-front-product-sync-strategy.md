# WooCommerce to Front Product Sync Strategy

This document defines the production-safe product sync approach for OmniBridge.

## Production Goal

The final production goal is to sync all relevant WooCommerce products and variations to Front Systems. WooCommerce remains the product, price, and stock master. Front remains the in-store POS/work surface.

The catalog is large, approximately 70,000 WooCommerce products plus variations, so the platform must never attempt to sync everything in one request, one browser page, or one uncontrolled job.

Selected products are used for proof-of-concept and limited write tests only. They are not the final production model.

## Sync Lifecycle

### A. Discovery

- Read WooCommerce products in pages.
- Read Front products in pages.
- Build candidate mappings using Woo product/variation IDs, SKU, GTIN/EAN candidates, Front `productExtId`, Front `identity`, Front `externalSKU`, and Front `productid`.
- Detect missing required fields before any write.
- Store local summaries and samples only; do not store full catalog response bodies.

### B. Initial Full Sync

- Create a full catalog sync run with `run_type=initial_full_sync`.
- Process products and variations in small batches.
- Use `max_products_per_batch`, `woo_page_size`, `front_page_size`, and optional runtime/rate limits.
- Store `cursor_json` and `checkpoint_json` so the run can resume.
- Never load 70,000 products into memory or the UI at once.
- Persist product/variation-level status in `product_sync_run_items`.

### C. Incremental Sync

- A WooCommerce product or variation change later creates a `product_sync_events` row.
- Events are deduplicated by `dedupe_key`.
- A queued worker later coalesces/debounces rapid updates where possible.
- Only changed products or variations are revalidated and updated.
- Incremental sync remains disabled by default until write behavior is proven.

### D. Reconciliation

- A scheduled reconciliation job later compares WooCommerce and Front.
- It finds missing, outdated, failed, or unmapped products.
- It creates retry or reconciliation runs without blocking normal portal use.
- Reconciliation must be paginated and rate-limited.

### E. Manual Operations

Planned manual controls:

- Resync one product or variation.
- Retry failed items.
- Pause a run.
- Resume a run.
- Cancel a run.
- View plain-English errors and technical details separately.

## Performance Strategy for 70k Products

- Use pagination and checkpoints everywhere.
- Use queues and background jobs for catalog scanning and future writes.
- Read only required WooCommerce fields where APIs allow it.
- Use `modified_after` for incremental scans where possible.
- Avoid repeated full meta-table reads.
- Avoid heavy WooCommerce admin queries.
- Do not scan the full catalog from the WooCommerce plugin.
- Store local sync status, mappings, and validation results in the Laravel platform.
- Do not show all rows in the portal. Use filters, search, and pagination.
- Add rate limiting, retry with backoff, and idempotency before real writes.
- Write only changed products when payload hashes match.

## Product Sync Profiles

Profiles control safe preparation and future write limits:

- `preview_only`: local validation and planning only.
- `limited_write_test`: future selected product write tests only.
- `initial_full_sync`: future controlled full-catalog run.
- `incremental_sync`: future Woo change processing.
- `production`: disabled unless production write safety is explicitly enabled.

The default profile is safe:

- `mode=preview_only`
- `sync_scope=selected_only`
- `max_products_per_batch=25`
- `max_products_per_run=100`
- `woo_page_size=50`
- `front_page_size=50`
- Variable products and variations are included in planning.
- Incremental sync, webhook updates, and reconciliation are disabled.

## Validation Before Sync

Blockers:

- Missing product name.
- Missing SKU when required.
- Missing GTIN/EAN when required.
- Missing price when required.
- Unsupported product type for the active profile.
- Variation missing parent product context.
- Duplicate GTIN/EAN within the run.
- Duplicate SKU within the run.

Warnings:

- Missing brand.
- Missing category.
- Out of stock.
- `manage_stock=false`.
- No Front match in the current sample.
- Uncertain GTIN/EAN field.
- Sale price not included yet.
- Stock not included yet.

## Portal Visibility

Store-owner pages should show:

- Current sync mode.
- Sync status.
- Products discovered.
- Products ready.
- Products needing attention.
- Failed products.
- Variations discovered.
- Last run and last successful run.
- Incremental updates pending.
- Connection status.

Advanced pages should contain:

- Webhooks.
- Events.
- API settings.
- Raw logs.
- Sync profile technical settings.
- Developer tools.

Normal pages should use plain language such as Ready, Needs attention, Waiting, Synced, Failed, Retry, Safe mode, and Preview only.

Technical terms such as payload, webhook headers, idempotency, API body, queue workers, and raw logs belong in Advanced.

## No-Write Guarantee for This Phase

This phase does not write to WooCommerce or Front. It must not call:

- Front `POST /api/products`
- Front `PUT /api/products/{productId}`
- Front `POST /api/PricelistV2`
- Front `POST /api/Stock/adjust`
- WooCommerce write endpoints

The next phase may prepare limited write-readiness, but actual writes must remain behind disabled-by-default production and limited-test guards.
