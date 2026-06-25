# OmniBridge

OmniBridge is a minimalist SaaS integration platform for connecting WooCommerce with Front Systems POS.

The first customer use case is Lilleprinsen, a Norwegian retailer using WooCommerce, Front Systems POS, Dintero Checkout, Stripe, and WebToffee WooCommerce Gift Cards.

## Core Direction

- WooCommerce is the master for products, prices, stock, orders, customers, gift cards, and business logic.
- Front Systems is the in-store POS and employee work surface.
- The SaaS platform is the integration brain: webhooks, queues, mappings, retries, logs, and reconciliation.
- The WooCommerce plugin stays thin and only handles Woo-specific adapter behavior.
- Front capabilities must be confirmed through documented APIs, configuration, webhooks, and the merchant contract. Do not assume custom Front POS UI.

## Repository Structure

```text
apps/
  platform/              Laravel integration platform
  woocommerce-plugin/    Thin WordPress/WooCommerce adapter plugin
docs/                    Architecture, requirements, roadmap, and operating docs
infra/                   Local development and hosting notes
docker-compose.yml       Local PostgreSQL, Redis, and app placeholder
```

## Current Status

This repository contains the first technical specification and scaffold only. It does not yet contain a fully installed Laravel application or production-ready plugin logic.

## Front Systems API documentation

Official Front Systems API specs should go in `docs/vendor/front-systems/openapi/`.

The current stored spec is:

```text
docs/vendor/front-systems/openapi/frontsystems.openapi.json
```

If Front provides a direct OpenAPI/Swagger URL, download it with:

```bash
./scripts/download-front-openapi.sh "<OFFICIAL_SPEC_URL>"
```

Use this command to verify that a spec file is present:

```bash
./scripts/generate-front-client.sh
```

Do not commit secrets, API keys, tokens, cookies, private links, restricted vendor documentation without permission, or unredacted customer data.

## Next Steps

1. Confirm Front Systems API, webhook, reservation, gift card, and omnichannel module capabilities.
2. Install Laravel in `apps/platform`.
3. Implement authentication, organizations, encrypted connection storage, webhook skeletons, queues, and event logs.
4. Build the first proof of concept tests listed in [docs/first-poc-checklist.md](docs/first-poc-checklist.md).
5. Keep all work staging-first. Do not write to production systems until explicitly enabled and reviewed.
