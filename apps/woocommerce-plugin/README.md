# OmniBridge WooCommerce Plugin

This directory contains the thin WordPress/WooCommerce plugin for OmniBridge.

The plugin is no longer only a placeholder. It now provides a production-oriented adapter foundation that can be installed in WooCommerce and tested directly.

## Responsibilities

- Provide a small WooCommerce admin settings page.
- Expose a public read-only health endpoint.
- Expose a signed read-only connection test endpoint.
- Add lightweight product sync eligibility/status fields.
- Add simple product bulk actions for future sync readiness.
- Keep WooCommerce-specific behavior close to WooCommerce.

## Non-Responsibilities

- Do not place core integration business logic here.
- Do not call Front Systems from the plugin.
- Do not run catalog sync jobs inside WordPress.
- Do not scan the full catalog from the plugin.
- Do not store platform secrets in code.
- Do not assume undocumented Front Systems behavior.
- Do not directly replace WooCommerce or payment gateway business logic.

## Install Locally in WordPress

Copy this folder into a WooCommerce test/staging site:

```text
wp-content/plugins/omnibridge-woocommerce-adapter/
```

Then activate **OmniBridge WooCommerce Adapter** in WordPress admin.

The plugin requires WooCommerce for product fields and WooCommerce-specific status. The public health endpoint still responds if WooCommerce is not active, but reports that WooCommerce needs attention.

## Admin Settings

After activation, open:

```text
WooCommerce > OmniBridge
```

Settings:

- Environment: staging or production label for this Woo site.
- OmniBridge platform URL: stored for later lightweight callbacks. This version does not send callbacks.
- Tenant key: non-secret tenant identifier.
- Shared secret: used for signed adapter endpoint tests.
- Enable signed adapter endpoints.
- Show lightweight product sync fields in WooCommerce admin.

Existing shared secrets are never displayed again. Leave the field blank to keep the existing secret.

## Test the Plugin Without Platform Safe-Mode Skips

The Laravel platform still keeps live external tests behind `OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP` for safety.

For Woo-side plugin verification, test the plugin directly from the WooCommerce staging site. These plugin endpoints do not use the platform safe-mode skip.

### 1. Public Health Check

Open in a browser or with curl:

```text
https://example.test/wp-json/omnibridge/v1/health
```

Expected result:

- Plugin version.
- WooCommerce active/inactive status.
- Endpoint URLs.
- No secrets.
- No customer/order/product payloads.
- No writes.

### 2. Signed Connection Test

Configure a shared secret in **WooCommerce > OmniBridge**.

Generate a signature:

```bash
SECRET="replace-with-shared-secret"
TS="$(date +%s)"
SIG="$(printf "GET\n/omnibridge/v1/connection-test\n${TS}" | openssl dgst -sha256 -hmac "${SECRET}" -binary | xxd -p -c 256)"

curl \
  -H "X-Omnibridge-Timestamp: ${TS}" \
  -H "X-Omnibridge-Signature: ${SIG}" \
  "https://example.test/wp-json/omnibridge/v1/connection-test"
```

Expected result:

- `status: success`
- `read_only: true`
- `writes_performed: false`
- WooCommerce version/currency when WooCommerce is active.
- Capabilities for signed connection tests and product sync flags.
- No secrets.

The signature payload is:

```text
HTTP_METHOD
/omnibridge/v1/connection-test
UNIX_TIMESTAMP
```

The timestamp must be within 5 minutes of the WooCommerce server time.

## Product Sync Fields

When product fields are enabled, WooCommerce product edit pages show:

- `_omnibridge_sync_to_front`
- `_omnibridge_exclude_from_front`
- `_omnibridge_last_sync_status`
- `_omnibridge_last_synced_at`
- `_omnibridge_last_sync_error`

Bulk actions:

- OmniBridge: mark for Front sync
- OmniBridge: exclude from Front sync
- OmniBridge: request resync

These fields and actions only store local metadata for later platform-driven sync. They do not call Front and do not sync products.

Product edit saves use the normal WooCommerce save nonce and per-product edit permission checks. Bulk actions also check edit permission for each product before changing OmniBridge metadata.

## Safety Rules

- The plugin does not call Front Systems.
- The plugin does not call the Laravel platform yet.
- The plugin does not import/export products.
- The plugin does not modify prices, stock, orders, refunds, gift cards, or customers.
- The plugin does not run heavy queries such as full catalog scans.
- The platform remains the integration brain.

## Planned Later

- Platform-to-plugin signed adapter calls for WebToffee gift card balance, redeem, reverse, and credit.
- Lightweight WooCommerce product/variation webhook callbacks to the platform.
- Admin display of last platform sync results.
- Tests in a real WordPress/WooCommerce test harness.
