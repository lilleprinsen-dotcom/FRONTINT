# Front Systems REST API V2 Endpoint Summary

This summary is derived from `docs/vendor/front-systems/openapi/frontsystems.openapi.json`. It is an orientation document, not a replacement for the OpenAPI file.

Do not assume an endpoint is enabled for Lilleprinsen until Front module access and staging credentials are confirmed. Mark uncertain behavior as `NEEDS_FRONT_CONFIRMATION`.

## API Metadata

- API title: `REST API`
- API version: `V2`
- OpenAPI version: `3.0.1`
- Base server URL: `https://frontsystemsapis.frontsystems.no/restapi/V2`
- Path count: `97`
- Authentication: `x-api-key` header according to the OpenAPI security scheme and endpoint parameters.

## Environment

- `GET /api/Environment`: Returns environment information for the authenticated API context.

## Stores

- `GET /api/Stores`: Lists stores available to the API key.

## Products Read-Only

- `POST /api/Product`: Searches or lists products using the older product query style.
- `POST /api/Product/count`: Counts products matching product query filters.
- `GET /api/Product/productid/{id}`: Fetches a product by Front product ID.
- `GET /api/Product/gtin/{id}`: Fetches product data by GTIN.
- `GET /api/Product/id/{id}`: Fetches product data by Front identity.
- `GET /api/Product/images/{id}`: Fetches product images by product ID.
- `GET /api/Product/tags`: Lists product tags.

## Products CRUD

- `POST /api/products`: Creates a product.
- `GET /api/products/{productId}`: Fetches a product from the newer product endpoint.
- `PUT /api/products/{productId}`: Updates a product.
- `DELETE /api/products/{productId}`: Deletes a product.
- `DELETE /api/products/{productId}/gtin`: Deletes GTINs from a product.
- `DELETE /api/products/{productId}/gtin/{gtin}`: Deletes one GTIN from a product.
- `DELETE /api/products/extid/{extId}/gtin/{gtin}`: Deletes one GTIN using product external ID.
- `POST /api/v2/Products/bulk-insert`: Bulk inserts products.
- `PUT /api/v2/Products/bulk-upsert`: Bulk creates or updates products.
- `GET /api/Product/{number}/{variant}`: Fetches a product by number and variant.

NEEDS_FRONT_CONFIRMATION: Which product endpoint family Front recommends for WooCommerce product sync.

## Price Lists

- `GET /api/Pricelist`: Lists price lists.
- `POST /api/Pricelist`: Creates or updates price list data.
- `GET /api/Pricelist/name/{name}`: Fetches a price list by name.
- `GET /api/Pricelist/{id}`: Fetches a price list by ID.
- `POST /api/Pricelist/cost`: Works with cost price list data.
- `GET /api/Pricelist/cost/stockextid/{stockextid}`: Fetches cost price data by stock external ID.
- `GET /api/Pricelist/cost/stockid/{id}`: Fetches cost price data by stock ID.
- `GET /api/Pricelist/gtin/{gtin}`: Fetches price list data by GTIN.
- `POST /api/Pricelist/buycost`: Works with buy cost data.

## PriceListV2

- `POST /api/PricelistV2`: Creates or updates PriceListV2 data.
- `GET /api/PricelistV2/{pricelistId}/prices`: Lists prices for a PriceListV2 list.
- `DELETE /api/PricelistV2/{pricelistId}/prices`: Deletes prices from a PriceListV2 list.

NEEDS_FRONT_CONFIRMATION: Which price list version should be used for regular price and sale price sync.

## Stock

- `POST /api/Stock`: Searches stock quantities.
- `POST /api/Stock/count`: Counts stock quantity records.
- `GET /api/Stock/product/{id}`: Fetches stock by product ID.
- `GET /api/Stock/identity/{id}`: Fetches stock by Front identity.
- `GET /api/Stock/list`: Lists stock records.
- `GET /api/Stock/gtin/{id}`: Fetches stock by GTIN.
- `PUT /api/Stock/stockshelf`: Sets stock shelf data.
- `GET /api/Stock/stockshelf`: Gets stock shelf data.
- `GET /api/Stock/settings`: Gets stock settings.
- `GET /api/Stock/count/batch/{stockCountBatchId}`: Gets one stock count batch.
- `GET /api/Stock/count/batches`: Lists stock count batches.

## Stock Adjust

- `POST /api/Stock/adjust`: Adjusts stock quantity.

NEEDS_FRONT_CONFIRMATION: How to distinguish full stock counts from partial counts and how Front expects WooCommerce-master stock corrections.

## Sales

- `PUT /api/Sale`: Creates or updates a sale record from external commerce/POS flow.
- `GET /api/Sale/freight`: Gets the freight product identity.

NEEDS_FRONT_CONFIRMATION: Exact semantics for Front POS sales into WooCommerce orders and stock deduction behavior.

## Omnichannel

- `GET /api/OmniChannel`: Gets one omnichannel order.
- `PUT /api/OmniChannel`: Updates an omnichannel order.
- `DELETE /api/OmniChannel`: Deletes an omnichannel order.
- `POST /api/OmniChannel`: Creates an omnichannel order.
- `GET /api/OmniChannel/orders`: Lists omnichannel orders.

NEEDS_FRONT_CONFIRMATION: Module availability and how paid pickup, unpaid reservation, and endless aisle flows appear in POS.

## Webhooks

- `GET /api/Webhooks`: Lists webhooks.
- `POST /api/Webhooks`: Creates a webhook.
- `GET /api/Webhooks/{webhookId}`: Gets one webhook.
- `PUT /api/Webhooks/{webhookId}`: Updates a webhook.
- `DELETE /api/Webhooks/{webhookId}`: Deletes a webhook.
- `GET /api/WebhooksTypes`: Lists webhook types.
- `GET /api/WebhooksTypes/{webhookType}/schema`: Gets a webhook type schema.

NEEDS_FRONT_CONFIRMATION: Webhook signing, retry behavior, and recommended callback authentication.

## Webhook Events/Resend

- `GET /api/WebhooksEvents/{webhookId}/{webhookEventId}`: Gets one webhook event.
- `GET /api/WebhooksEvents/{webhookId}`: Lists webhook events for a webhook.
- `POST /api/WebhooksEvents/{webhookId}/deliver/{webhookEventId}`: Resends a webhook event.

## Gift Cards

- `GET /api/Giftcard/{giftcardToken}/balance`: Gets gift card balance.
- `POST /api/Giftcard/{giftcardToken}`: Inserts a gift card transaction.
- `GET /api/Giftcard`: Lists gift cards.
- `GET /api/Voucher/{code}`: Gets voucher information.
- `POST /api/Voucher`: Creates a voucher.
- `POST /api/Voucher/redeem/{code}`: Redeems a voucher.

NEEDS_FRONT_CONFIRMATION: Whether these endpoints can support WebToffee-master gift cards in store without custom Front POS UI.

## Customers

- `PUT /api/Customer`: Upserts a customer.
- `POST /api/Customer`: Searches customers.
- `POST /api/Customer/count`: Counts customers.
- `DELETE /api/Customer/{customerid}`: Deletes a customer.
- `GET /api/Customer/optin/{customerid}`: Opts customer into communication.
- `GET /api/Customer/optout/{customerid}`: Opts customer out of communication.
- `POST /api/Person`: Creates a person.
- `GET /api/UserAccount/{email}`: Gets a user account by email.
- `GET /api/UserAccount/roles`: Lists user account roles.
- `PUT /api/UserAccount/{id}`: Updates a user account.
- `DELETE /api/UserAccount/{id}`: Deletes a user account.
- `POST /api/UserAccount/invite/{email}`: Sends a user account invitation.
- `GET /api/UserAccount`: Lists user accounts.
- `POST /api/UserAccount`: Creates a user account.

## Brands, Colours, Seasons, SizeSystems

- `GET /api/Brands`, `POST /api/Brands`, `GET/PUT/DELETE /api/Brands/{brandId}`: Brand reference data.
- `GET /api/Colours`, `POST /api/Colours`, `GET/PUT/DELETE /api/Colours/{colourId}`: Colour reference data.
- `GET /api/Seasons`, `POST /api/Seasons`, `GET/PUT/DELETE /api/Seasons/{seasonId}`: Season reference data.
- `GET /api/SizeSystems`: Size system reference data.
- `GET/POST /api/Groups`: Group reference data.
- `GET /api/Groups/{groupName}/{subgroupName}`: Gets a subgroup.
- `POST /api/Groups/subgroup`: Creates a subgroup.
- `PUT/DELETE /api/Groups/{groupName}`: Updates or deletes a group.
- `PUT /api/Groups/subgroup/{name}`: Updates a subgroup.

## Delivery/PurchaseOrder/ProductTransfer

- `PUT /api/ProductTransfer`: Creates product transfer data.
- `PUT /api/PurchaseOrder`: Registers purchase order/delivery data.
- `PUT /api/PurchaseOrder/correction`: Corrects stock quantity through purchase order flow.

NEEDS_FRONT_CONFIRMATION: No separate `/api/Delivery` path appears in the stored OpenAPI file; delivery wording appears tied to purchase order operations.

## POS Settlement

- `GET /api/Possettlement`: Gets POS settlement data.
- `GET /api/Possettlementv2`: Gets POS settlement data from the V2 settlement endpoint.
- `GET /api/PosStatus`: Gets POS status.

## Budget

- `GET /api/Budget`: Gets budget data.
- `POST /api/Budget`: Creates or updates budget data.
- `GET /api/Budget/{Extid}`: Gets budget data by external ID.

## Customer Counter

- `GET /api/CustomerCounter`: Gets customer counter data.
- `DELETE /api/CustomerCounter`: Deletes customer counter data.
- `POST /api/CustomerCounter`: Inserts customer counter data.
- `PUT /api/CustomerCounter`: Updates customer counter data.
