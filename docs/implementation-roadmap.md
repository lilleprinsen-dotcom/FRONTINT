# Implementation Roadmap

## Phase 0: Discovery and Technical Proof of Concept

- Sync one WooCommerce product to Front.
- Import one Front sale as a WooCommerce order.
- Test one WooCommerce refund through Dintero/Stripe in staging.
- Send one Woo pickup/reservation to Front and receive status back.
- Test one WebToffee gift card balance, debit, and credit operation.

## Phase 1: SaaS Platform Foundation

- Laravel app.
- Authentication.
- Organization model.
- Connection settings.
- Encrypted credential storage.
- Webhook receiver skeleton.
- Event log.
- Queue.
- Minimal dashboard.
- Docker local setup.

## Phase 2: Product/Price/Stock Sync

- Batch product sync.
- Incremental webhooks.
- Product validation.
- Regular price and sale price mapping.
- Stock sync.
- Daily reconciliation.

## Phase 3: Front Sales and Customer History

- Front POS sale to Woo order.
- Customer matching.
- POS order source.
- Avoid Woo emails and double stock.

## Phase 4: Returns and Exchanges

- Return with refund.
- Exchange without refund.
- Exchange with price difference.
- Return reasons.
- Return item stock status.

## Phase 5: Click and Collect and Reservations

- BOPIS paid online.
- ROPIS pay in store.
- Reservation expiry.
- Pickup notifications.

## Phase 6: Endless Aisle and Ship From Store

- Front sale shipped through Woo.
- Shipping address.
- Fulfillment statuses.
- Partial fulfillment only if Front supports line-level metadata.

## Phase 7: WebToffee Gift Cards

- Gift card adapter.
- Redeem, reverse, and credit.
- Store credit from returns.
- Concurrency protection.

## Phase 8: Logistics, Stock Count, and Labels

- Front stock count to Woo.
- Receiving goods.
- Label printing if supported.
- Outlet and inspection workflows.

