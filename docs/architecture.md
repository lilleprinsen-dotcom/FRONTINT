# Architecture

## Summary

OmniBridge connects WooCommerce and Front Systems through a Laravel SaaS platform and a thin WooCommerce plugin.

WooCommerce remains the master for commerce data and business logic. Front Systems remains the POS and employee-facing work surface in the physical store. The platform receives events, verifies them, maps entities, queues work, retries failures, and reconciles state.

## System Components

### WooCommerce

WooCommerce is the master for:

- Products and variants
- Prices and sale prices
- Stock quantities and availability rules
- Orders and order history
- Customers
- Gift cards through WebToffee WooCommerce Gift Cards
- Payment gateway behavior through Dintero Checkout and Stripe

### Front Systems

Front is the in-store POS and operational surface for employees.

The integration must only rely on documented Front configuration, APIs, webhooks, and enabled contract modules. Custom Front POS UI must not be assumed.

Mark uncertain capabilities as `NEEDS_FRONT_CONFIRMATION`.

### SaaS Platform

The Laravel platform is the integration brain:

- Multi-tenant organization model
- Connection settings
- Encrypted credential storage
- Webhook receivers
- Idempotent event log
- Queue and retry processing
- Product, order, customer, stock, and gift card mappings
- Reconciliation jobs
- Minimal dashboard for non-developer users

### WooCommerce Plugin

The plugin is intentionally small:

- Woo admin settings and metadata
- Signed adapter endpoints
- WebToffee compatibility endpoints if official APIs are insufficient
- Woo-specific hooks for order source, product eligibility, and email suppression settings

Core integration decisions stay in the Laravel platform.

## Why Not Build Everything Inside WooCommerce?

WooCommerce is already responsible for commerce. Putting all integration logic inside WordPress would make long-running syncs, retries, queue processing, tenant isolation, observability, and future SaaS resale harder to maintain.

A separate platform gives cleaner operations, safer credential handling, better queue control, and a path to support more merchants later.

## Why Not Customize Front POS UI?

Front Systems is not open source. The integration must not assume custom POS UI changes beyond documented configuration, APIs, webhooks, and enabled modules. Employee-facing workflows should use Front features that are explicitly available and confirmed.

## Multi-Tenant Design

The first tenant is Lilleprinsen. The platform should still use tenant-aware tables and settings from the start so it can later support other WooCommerce plus Front Systems merchants.

Each tenant needs isolated:

- Users and organization membership
- WooCommerce connection settings
- Front connection settings
- Webhook secrets and callback URLs
- Mapping tables
- Sync settings
- Event logs and retries

## Hosting

Recommended first hosting options:

- Render: simple Laravel deployment, managed PostgreSQL, Redis support, simple environment management.
- DigitalOcean App Platform: simple managed app hosting with managed PostgreSQL and Redis options.

Later scalable target:

- Google Cloud Run: good fit once containerization, stateless workers, secret management, and observability are mature.

See [../infra/hosting.md](../infra/hosting.md).

## Local Development

Local development should use Docker Compose with:

- Laravel app container
- PostgreSQL
- Redis

See [../infra/local-development.md](../infra/local-development.md).

