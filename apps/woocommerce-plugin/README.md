# OmniBridge WooCommerce Plugin

This directory contains the thin WordPress/WooCommerce plugin for OmniBridge.

## Responsibilities

- Provide a small admin settings surface inside WooCommerce.
- Expose adapter endpoints for WebToffee gift card operations when official APIs are not available.
- Add WooCommerce order/product metadata needed by the platform.
- Help suppress or control WooCommerce behavior for POS-imported orders where safe and configurable.

## Non-Responsibilities

- Do not place core integration business logic here.
- Do not store platform secrets in plugin code.
- Do not assume Front Systems behavior from this plugin.
- Do not directly replace WooCommerce or payment gateway business logic.

## First Implementation Later

The first real plugin version should add:

- Settings page for platform URL and tenant token.
- Signed request validation for platform-to-plugin calls.
- Gift card balance, redeem, reverse, and credit adapter endpoints.
- WooCommerce hooks needed for product eligibility metadata and POS order source metadata.
- Tests for signature validation and WebToffee adapter behavior where possible.

## Product Sync Planning

The plugin should stay lightweight when product sync is added later.

Planned product metadata:

- `_omnibridge_sync_to_front`
- `_omnibridge_last_sync_status`
- `_omnibridge_last_synced_at`
- `_omnibridge_last_sync_error`

Planned admin features:

- Lightweight product admin panel showing sync eligibility and last status.
- Bulk action to mark selected products for Front sync.
- Webhook registration/helper for product update notifications.

Do not implement heavy WooCommerce admin queries. Do not scan the full catalog from the plugin. Do not implement actual sync inside the plugin; product sync orchestration belongs in the Laravel platform.
