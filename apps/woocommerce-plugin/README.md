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

