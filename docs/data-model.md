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
- `woo_product_id`
- `woo_variation_id`
- `front_product_id`
- `sku`
- `ean`
- `sync_status`
- `last_synced_at`
- `created_at`
- `updated_at`

Indexes:

- Unique `organization_id, woo_product_id, woo_variation_id`
- Unique `organization_id, front_product_id`
- Index `organization_id, sku`
- Index `organization_id, ean`

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

