# First Proof of Concept Checklist

Run all tests in staging or sandbox only.

## 1. One Woo Product to Front

- Pick one simple WooCommerce product.
- Confirm SKU, EAN/GTIN, category, price, and stock.
- Mark eligible for Front POS.
- Sync to Front.
- Confirm name, SKU/EAN, price, status, and stock in Front.

## 2. One Front Sale to Woo Order

- Sell one staging/test product in Front.
- Receive Front sale webhook or fetch sale through API.
- Create WooCommerce order with source `front_pos`.
- Store Front transaction ID.
- Confirm stock is not double-deducted.
- Confirm Woo emails are controlled as configured.

## 3. One Woo Refund Through Dintero/Stripe Test

- Create a staging Woo order paid through Dintero or Stripe.
- Create a WooCommerce refund through the supported API path.
- Confirm gateway refund result.
- Store refund metadata and order notes.

## 4. One Pickup/Reservation to Front and Status Back

- Create a WooCommerce pickup or reservation order.
- Send it to Front.
- Confirm employee-facing state for paid vs unpaid.
- Update status in Front.
- Confirm WooCommerce status updates correctly.

## 5. One WebToffee Gift Card Test

- Create or use a staging gift card.
- Check balance through plugin adapter.
- Redeem/debit a small amount.
- Reverse or credit the amount.
- Confirm transaction log and concurrency protection approach.

