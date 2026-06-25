# OmniBridge Minimal Product Spec

## Product Name

OmniBridge

## Purpose

OmniBridge connects WooCommerce and Front Systems so retailers can keep WooCommerce as the commerce master while using Front as the in-store operational tool.

The product gives retailers unified stock, orders, customers, returns, and gift card behavior without requiring store owners to understand integration internals.

## Target Customer

Retailers with:

- WooCommerce webshop
- Front Systems POS
- Shared physical stock between webshop and store
- Need for in-store returns, pickup, reservations, and POS sales visibility

## Product Principles

- Simple setup.
- One clear dashboard.
- Clear sync status.
- Clear error handling.
- Staging first, production later.
- Practical and maintainable over feature-heavy.
- Built for non-technical store owners.

## First Commercial Experience

The merchant should be able to:

1. Log in.
2. See connection status for WooCommerce and Front.
3. See whether products, stock, orders, and gift cards are healthy.
4. Read plain-language errors.
5. Retry failed events.
6. Run a safe single-product sync test.
7. Understand what still needs setup.

## Out of Scope for First Version

- Billing.
- Custom Front POS UI.
- Full historic order import into Front.
- Production writes without explicit approval.
- Advanced analytics.

