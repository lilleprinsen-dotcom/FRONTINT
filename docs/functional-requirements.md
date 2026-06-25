# Functional Requirements

## A. Product, Price, and Stock Sync

- Sync WooCommerce products to Front.
- Support product variants.
- Include SKU, EAN/GTIN, product names, brands, categories, images, regular price, sale price, sale price period where available, active/inactive status, and stock quantity.
- Only sync products marked eligible for Front POS.
- Validate missing SKU, EAN/GTIN, price, category, and variant data.
- Support batch sync for about 70,000 products.
- Support incremental sync through WooCommerce webhooks.
- Run daily reconciliation.

## B. Prices and Discounts

- Map Woo regular price to the Front recommended price list.
- Map Woo sale price to the Front sale price list.
- Support sale start and end dates if both systems support it.
- Test whether Front POS displays discount amount or percentage.
- Do not assume custom UI in Front.

## C. Stock and Reservations

- WooCommerce is the stock master.
- Front sales reduce WooCommerce stock through controlled integration logic.
- Front returns increase WooCommerce stock where appropriate.
- Front stock counts can update WooCommerce after validation.
- Front reservations reduce available WooCommerce stock.
- Reservation expiry releases stock.
- Track physical stock, reserved stock, and available stock.
- Use idempotency to avoid double deductions.

## D. Front Sales Into WooCommerce

- Import Front POS sales as WooCommerce orders.
- Mark order source as `front_pos`.
- Use payment method `paid_in_front`.
- Store Front receipt or transaction ID.
- Link to WooCommerce customer if identifiable.
- Do not send normal WooCommerce transactional emails unless explicitly configured.
- Do not double-deduct stock.

## E. Customer History Both Ways

- Front sales should be visible on the WooCommerce customer profile after import.
- WooCommerce orders should be searchable or visible from the Front flow where supported by API or omnichannel modules.
- Match customers by email, phone, and mapping table.
- Handle anonymous POS sales.
- Avoid unsafe automatic merges when email or phone conflicts exist.

## F. Returns and Exchanges

- Customers can return WooCommerce online orders in store using Front where supported.
- Refund through WooCommerce payment gateway when possible:
  - Dintero Checkout
  - Stripe
- Support exchange in store without WooCommerce refund.
- Support exchange with price difference:
  - Customer pays extra in Front if the new item costs more.
  - Difference can be refunded through Woo/Dintero/Stripe or issued as gift card/store credit if the new item costs less.
- Store clear WooCommerce order notes and metadata:
  - Returned in store
  - Exchanged in store
  - Front transaction ID
  - Employee/cashier if available
  - Return reason
  - Refund method
  - Whether Woo refund was created
- Support partial returns.
- Support return item status:
  - Back to stock
  - Needs inspection
  - Damaged
  - Missing packaging
  - Outlet
  - Supplier return
  - Write-off

## G. Click and Collect / BOPIS

- WooCommerce order is paid online.
- Send order to Front as pickup order when supported.
- Employee must see clearly: paid online, do not take payment.
- Employee can mark picking, ready for pickup, and collected where supported.
- WooCommerce order status updates accordingly.
- Customer notification should come from WooCommerce or the platform.
- Optional QR or pickup code.

## H. Reserve Online, Pay in Store / ROPIS

- Customer reserves in WooCommerce.
- No online payment is taken.
- Reservation is sent to Front.
- Front reserves stock where supported.
- Employee must see clearly: unpaid, take payment in Front.
- When paid in Front, WooCommerce order becomes paid/completed.
- Reservation expiry releases stock.
- Customer notifications cover confirmation and expiry.

## I. Endless Aisle / Sell in Store, Send to Customer

- Employee sells in Front POS.
- Customer pays in Front.
- Item is shipped through WooCommerce fulfillment.
- Front endless aisle webhook creates WooCommerce order.
- Woo order source is `front_pos_endless_aisle`.
- Payment method is `paid_in_front`.
- Shipping address comes from Front payload if available.
- WooCommerce handles shipping, fulfillment emails, and tracking.
- Partial sale with take-now and ship-to-customer lines is supported only if Front provides reliable line-level metadata.
- Fallback: separate transactions or split only by confirmed metadata.

## J. Ship From Store / BODFS

- WooCommerce order may be fulfilled from store/local stock.
- Front can be used for picking and packing if supported.
- WooCommerce remains the master order system.
- Front fulfillment updates should update WooCommerce order statuses.

## K. Stock Count and Logistics

- Front or FrontZapp may be used for stock count.
- Stock count results update WooCommerce after validation.
- Distinguish partial count from full count.
- Avoid zeroing inventory from incomplete counts.
- Later support receiving goods and label printing if Front APIs and flows support it.

## L. Gift Cards / WebToffee Compatibility

- WebToffee WooCommerce Gift Cards is the gift card master.
- Platform and Woo plugin should expose adapter endpoints for:
  - Check gift card balance
  - Reserve/redeem amount
  - Reverse redemption
  - Credit gift card/store credit
  - Transaction log
- Front gift card compatibility depends on Front support for external gift card, voucher, or payment integrations.
- Do not assume custom Front gift card UI.
- Test WebToffee programmatic operations.
- Prevent concurrent double redemption.
- Gift cards can be bought online, bought in store if supported, used online, used in store, used for partial payment, and used for return to store credit.

## M. SMS/Email Notifications

- Do not depend on Front SMS pricing initially.
- Prefer platform or WooCommerce-controlled notifications for click and collect, reservations, shipment, returns, and refunds.
- Front SMS or receipt features can be used if economical and technically appropriate.
- Keep SMS provider replaceable.

## N. Admin Dashboard

The dashboard should be simple for non-developer merchant admins:

- Connection status
- Last product sync
- Last stock sync
- Failed events
- Queue status
- Product validation issues
- Stock mismatches
- Recent Front sales
- Recent returns/exchanges
- Gift card transaction errors
- Manual retry and resync actions

## O. Resale/SaaS Readiness

- Multi-tenant organization/account model.
- OAuth or API-key setup wizard later.
- Billing is not needed now, but architecture should not block it.
- Tenant-isolated data.
- Minimal onboarding checklist per merchant.
- Clear per-merchant connection settings.
- Product sync rules per merchant.
- Webhook callback URLs per merchant.

