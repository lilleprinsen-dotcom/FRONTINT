# Data Model

This is the initial table design. Field names may change during Laravel implementation, but the purposes and indexes should remain.

## organizations

Purpose: tenant/account records.

Fields:

- `id`
- `name`
- `slug`
- `environment` (`staging`, `production`)
- `status`
- `created_at`
- `updated_at`

Indexes:

- Unique `slug`
- Index `status`

## users

Purpose: platform admin users.

Fields:

- `id`
- `name`
- `email`
- `password`
- `created_at`
- `updated_at`

Indexes:

- Unique `email`

## organization_users

Purpose: user membership and role per organization.

Fields:

- `id`
- `organization_id`
- `user_id`
- `role`
- `created_at`
- `updated_at`

Indexes:

- Unique `organization_id, user_id`

## connections

Purpose: external system connection records.

Fields:

- `id`
- `organization_id`
- `type` (`woocommerce`, `front`, `dintero`, `stripe`, `webtoffee_adapter`)
- `name`
- `base_url`
- `status`
- `last_checked_at`
- `created_at`
- `updated_at`

Indexes:

- Index `organization_id, type`

## connection_credentials

Purpose: encrypted credential storage.

Fields:

- `id`
- `connection_id`
- `credential_type`
- `encrypted_payload`
- `redacted_hint`
- `rotated_at`
- `created_at`
- `updated_at`

Indexes:

- Index `connection_id, credential_type`

## webhook_endpoints

Purpose: configured inbound webhook routes and secrets.

Fields:

- `id`
- `organization_id`
- `source_system`
- `path_token`
- `encrypted_secret`
- `status`
- `created_at`
- `updated_at`

Indexes:

- Unique `path_token`
- Index `organization_id, source_system`

## events

Purpose: durable event log and idempotency registry.

Fields:

- `id`
- `organization_id`
- `source_system`
- `event_type`
- `source_event_id`
- `idempotency_key`
- `payload_json`
- `status`
- `received_at`
- `processed_at`
- `error_class`
- `error_message`
- `created_at`
- `updated_at`

Indexes:

- Unique `organization_id, idempotency_key`
- Index `organization_id, status, received_at`
- Index `source_system, event_type`

## job_runs

Purpose: sync and queue job execution history.

Fields:

- `id`
- `organization_id`
- `event_id`
- `job_type`
- `status`
- `attempt_count`
- `started_at`
- `finished_at`
- `error_message`
- `created_at`
- `updated_at`

Indexes:

- Index `organization_id, job_type, status`
- Index `event_id`

## product_mappings

Purpose: map WooCommerce products/variants to Front product records.

Fields:

- `id`
- `organization_id`
- `woo_item_key`
- `woo_product_id`
- `woo_variation_id`
- `front_product_id`
- `front_product_ext_id`
- `front_identity`
- `sku`
- `gtin`
- `external_sku`
- `front_stock_id`
- `sync_status`
- `last_synced_at`
- `created_at`
- `updated_at`

Indexes:

- Unique `organization_id, woo_item_key`
- Index `organization_id, front_product_ext_id`
- Index `organization_id, sku`
- Index `organization_id, gtin`
- Index `organization_id, external_sku`

Notes:

- `woo_item_key` should be stable and explicit, for example `product:123` or `variation:456`.
- Do not rely on unique indexes that include nullable `woo_variation_id`.
- Use `gtin` terminology instead of old `ean` naming.
- `front_product_ext_id`, `front_identity`, `external_sku`, and `front_stock_id` map to Front API terminology and must be confirmed per endpoint before real sync writes.

## product_sync_preview_plans

Purpose: local preview-only 10-product mapping PoC plans generated from stored discovery snapshots.

Fields:

- `id`
- `organization_id`
- `created_by_user_id`
- `woo_connection_id`
- `front_connection_id`
- `status` (`draft`, `ready`, `blocked`)
- `selected_count`
- `summary_json`
- `plan_json`
- `validation_json`
- `created_at`
- `updated_at`

Indexes:

- Index `organization_id, created_at`
- Index `organization_id, status`

Notes:

- This table is not final sync history.
- Do not use this table as the source of truth for product mappings.
- Generated rows must remain preview-only until a separate explicit sync feature writes final `product_mappings`.

## product_sync_profiles

Purpose: per-organization product sync safety and validation configuration.

Fields:

- `id`
- `organization_id`
- `name`
- `is_active`
- `mode`
- `max_products_per_batch`
- `max_products_per_run`
- `woo_query_limit`
- `front_write_limit`
- `sync_only_opted_in_products`
- `include_simple_products`
- `include_variable_products`
- `include_variations`
- `require_sku`
- `require_gtin`
- `require_price`
- `require_brand`
- `require_category`
- `default_front_group_strategy`
- `default_front_subgroup_strategy`
- `default_front_brand_strategy`
- `price_strategy`
- `stock_strategy`
- `created_at`
- `updated_at`

Indexes:

- Index `organization_id, is_active`
- Index `organization_id, mode`

Notes:

- Default mode is `preview_only`.
- Production mode must remain disabled unless production writes are explicitly enabled.

## product_sync_runs

Purpose: local planning and future operational status for selected-product sync runs.

Fields:

- `id`
- `organization_id`
- `product_sync_profile_id`
- `created_by_user_id`
- `status`
- `mode`
- `total_candidates`
- `total_ready`
- `total_blocked`
- `total_synced`
- `total_failed`
- `total_skipped`
- `started_at`
- `finished_at`
- `summary_json`
- `created_at`
- `updated_at`

Indexes:

- Index `organization_id, status, created_at`
- Index `product_sync_profile_id, created_at`

## product_sync_run_items

Purpose: per-product validation and future sync status for selected products only.

Fields:

- `id`
- `organization_id`
- `product_sync_run_id`
- `woo_product_id`
- `woo_variation_id`
- `woo_item_key`
- `woo_name`
- `woo_sku`
- `detected_gtin`
- `detected_gtin_key`
- `front_match_status`
- `proposed_front_product_ext_id`
- `proposed_front_identity`
- `proposed_front_external_sku`
- `proposed_front_payload_json`
- `validation_status`
- `sync_status`
- `validation_errors_json`
- `validation_warnings_json`
- `last_error`
- `last_attempted_at`
- `synced_at`
- `created_at`
- `updated_at`

Indexes:

- Index `organization_id, woo_item_key`
- Index `product_sync_run_id, sync_status`
- Index `product_sync_run_id, validation_status`
- Index `organization_id, detected_gtin`
- Index `organization_id, woo_sku`

## customer_mappings

Purpose: map WooCommerce customers to Front customers.

Fields:

- `id`
- `organization_id`
- `woo_customer_id`
- `front_customer_id`
- `email_hash`
- `phone_hash`
- `match_confidence`
- `created_at`
- `updated_at`

Indexes:

- Unique nullable `organization_id, woo_customer_id`
- Unique nullable `organization_id, front_customer_id`
- Index `organization_id, email_hash`
- Index `organization_id, phone_hash`

## order_mappings

Purpose: map WooCommerce orders to Front receipts, orders, reservations, and returns.

Fields:

- `id`
- `organization_id`
- `woo_order_id`
- `front_order_id`
- `front_receipt_id`
- `source`
- `status`
- `idempotency_key`
- `created_at`
- `updated_at`

Indexes:

- Unique `organization_id, idempotency_key`
- Index `organization_id, woo_order_id`
- Index `organization_id, front_order_id`

## stock_ledger

Purpose: append-only stock movements and reservation effects.

Fields:

- `id`
- `organization_id`
- `product_mapping_id`
- `source_system`
- `movement_type`
- `quantity_delta`
- `physical_quantity_after`
- `reserved_quantity_after`
- `available_quantity_after`
- `source_reference`
- `idempotency_key`
- `created_at`

Indexes:

- Unique `organization_id, idempotency_key`
- Index `organization_id, product_mapping_id, created_at`

## stock_reservations

Purpose: active and historical reservations.

Fields:

- `id`
- `organization_id`
- `product_mapping_id`
- `woo_order_id`
- `front_reservation_id`
- `quantity`
- `status`
- `expires_at`
- `released_at`
- `idempotency_key`
- `created_at`
- `updated_at`

Indexes:

- Unique `organization_id, idempotency_key`
- Index `organization_id, status, expires_at`

## product_validation_issues

Purpose: product data quality issues before sync.

Fields:

- `id`
- `organization_id`
- `woo_product_id`
- `woo_variation_id`
- `issue_type`
- `severity`
- `message`
- `resolved_at`
- `created_at`
- `updated_at`

Indexes:

- Index `organization_id, issue_type, resolved_at`

## gift_card_transactions

Purpose: gift card balance, redeem, reverse, and credit operations.

Fields:

- `id`
- `organization_id`
- `gift_card_code_hash`
- `operation`
- `amount`
- `currency`
- `source_system`
- `source_reference`
- `status`
- `idempotency_key`
- `created_at`
- `updated_at`

Indexes:

- Unique `organization_id, idempotency_key`
- Index `organization_id, gift_card_code_hash, created_at`

## sync_runs

Purpose: batch sync and reconciliation checkpoints.

Fields:

- `id`
- `organization_id`
- `sync_type`
- `status`
- `cursor`
- `started_at`
- `finished_at`
- `items_seen`
- `items_succeeded`
- `items_failed`
- `created_at`
- `updated_at`

Indexes:

- Index `organization_id, sync_type, status`

## audit_logs

Purpose: human and system action history.

Fields:

- `id`
- `organization_id`
- `user_id`
- `action`
- `subject_type`
- `subject_id`
- `metadata_json`
- `created_at`

Indexes:

- Index `organization_id, action, created_at`
- Index `user_id, created_at`

## settings

Purpose: per-tenant configuration.

Fields:

- `id`
- `organization_id`
- `key`
- `value_json`
- `created_at`
- `updated_at`

Indexes:

- Unique `organization_id, key`
