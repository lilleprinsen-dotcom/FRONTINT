<?php

namespace App\Services\Sales;

use App\Models\Organization;
use App\Models\ProductMapping;
use App\Support\IdempotencyKey;
use Illuminate\Support\Collection;

class FrontSalePayloadMapper
{
    public function buildImportData(Organization $organization, array $payload, ?string $eventType = null): array
    {
        $lines = $this->extractLines($payload);
        $mappedLines = $this->mappedLines($organization, $lines);
        $transactionType = $this->transactionType($payload, $eventType, $lines);
        $frontSaleId = $this->stringValue(
            data_get($payload, 'saleId')
            ?? data_get($payload, 'sale_id')
            ?? data_get($payload, 'id')
            ?? data_get($payload, 'transactionId')
        );
        $frontReceiptId = $this->stringValue(
            data_get($payload, 'receiptId')
            ?? data_get($payload, 'receipt_id')
            ?? data_get($payload, 'receiptNo')
            ?? data_get($payload, 'receiptNumber')
            ?? data_get($payload, 'receipt.number')
        );
        $sourceId = $frontSaleId ?: $frontReceiptId ?: IdempotencyKey::payloadHash($payload);

        return [
            'front_sale_id' => $frontSaleId,
            'front_receipt_id' => $frontReceiptId,
            'idempotency_key' => IdempotencyKey::build($organization->slug, 'front', 'sale_import', $sourceId),
            'sale_time' => $this->stringValue(
                data_get($payload, 'saleTime')
                ?? data_get($payload, 'createdAt')
                ?? data_get($payload, 'timestamp')
                ?? data_get($payload, 'date')
            ),
            'currency' => $this->stringValue(data_get($payload, 'currency') ?? data_get($payload, 'currencyCode')),
            'total_amount' => $this->numberValue(
                data_get($payload, 'totalAmount')
                ?? data_get($payload, 'total')
                ?? data_get($payload, 'amount')
            ) ?? $mappedLines->sum(fn (array $line): float => (float) ($line['total'] ?? 0)),
            'transaction_type' => $transactionType,
            'payload_summary_json' => [
                'front_sale_id' => $frontSaleId,
                'front_receipt_id' => $frontReceiptId,
                'transaction_type' => $transactionType,
                'line_count' => $mappedLines->count(),
                'unmatched_line_count' => $mappedLines->where('mapping_status', 'missing_product_mapping')->count(),
                'source' => 'front_pos_' . $transactionType,
                'writes_woocommerce' => false,
            ],
            'line_items_json' => $mappedLines->values()->all(),
            'woo_order_payload_json' => $transactionType === 'sale'
                ? $this->wooOrderPayload($frontSaleId, $frontReceiptId, $mappedLines, $this->customerData($payload))
                : null,
        ];
    }

    public function looksLikeSale(array $payload, string $eventType): bool
    {
        $type = strtolower($eventType);

        if (
            str_contains($type, 'sale')
            || str_contains($type, 'receipt')
            || str_contains($type, 'transaction')
            || str_contains($type, 'return')
            || str_contains($type, 'refund')
            || str_contains($type, 'void')
            || str_contains($type, 'cancel')
        ) {
            return true;
        }

        return $this->extractLines($payload)->isNotEmpty()
            && (
                data_get($payload, 'receiptId')
                || data_get($payload, 'receiptNo')
                || data_get($payload, 'saleId')
                || data_get($payload, 'transactionId')
            );
    }

    private function mappedLines(Organization $organization, Collection $lines): Collection
    {
        return $lines->map(function (array $line) use ($organization): array {
            $mapping = $this->findMapping($organization, $line);

            return array_merge($line, [
                'mapping_status' => $mapping ? 'matched' : 'missing_product_mapping',
                'product_mapping_id' => $mapping?->id,
                'woo_product_id' => $mapping?->woo_product_id,
                'woo_variation_id' => $mapping?->woo_variation_id,
                'woo_item_key' => $mapping?->woo_item_key,
            ]);
        });
    }

    private function findMapping(Organization $organization, array $line): ?ProductMapping
    {
        $query = ProductMapping::query()->where('organization_id', $organization->id);

        foreach ([
            'gtin' => $line['gtin'] ?? null,
            'external_sku' => $line['external_sku'] ?? null,
            'sku' => $line['sku'] ?? null,
            'front_identity' => $line['identity'] ?? null,
            'front_product_id' => $line['front_product_id'] ?? null,
        ] as $column => $value) {
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $mapping = (clone $query)->where($column, trim($value))->first();
            if ($mapping) {
                return $mapping;
            }
        }

        return null;
    }

    private function wooOrderPayload(?string $frontSaleId, ?string $frontReceiptId, Collection $mappedLines, array $customer): array
    {
        return array_filter([
            'status' => 'completed',
            'set_paid' => true,
            'payment_method' => 'paid_in_front',
            'payment_method_title' => 'Paid in Front POS',
            'customer_note' => 'Imported from Front POS by OmniBridge.',
            'billing' => $customer,
            'line_items' => $mappedLines
                ->filter(fn (array $line): bool => $line['mapping_status'] === 'matched')
                ->map(function (array $line): array {
                    return array_filter([
                        'product_id' => $line['woo_product_id'],
                        'variation_id' => $line['woo_variation_id'],
                        'quantity' => $line['quantity'],
                        'subtotal' => $this->moneyString($line['total'] ?? null),
                        'total' => $this->moneyString($line['total'] ?? null),
                    ], fn (mixed $value): bool => $value !== null && $value !== '');
                })
                ->values()
                ->all(),
            'meta_data' => [
                ['key' => '_omnibridge_source', 'value' => 'front_pos'],
                ['key' => '_omnibridge_front_sale_id', 'value' => $frontSaleId],
                ['key' => '_omnibridge_front_receipt_id', 'value' => $frontReceiptId],
                ['key' => '_omnibridge_front_stock_already_adjusted', 'value' => 'yes'],
                ['key' => '_order_stock_reduced', 'value' => 'yes'],
                ['key' => '_omnibridge_stock_note', 'value' => 'Front POS sale stock was adjusted before this optional Woo order import. Do not reduce stock again.'],
            ],
        ], fn (mixed $value): bool => $value !== [] && $value !== null);
    }

    private function customerData(array $payload): array
    {
        $customer = data_get($payload, 'customer') ?? data_get($payload, 'person') ?? [];

        if (! is_array($customer)) {
            $customer = [];
        }

        return array_filter([
            'first_name' => $this->stringValue(data_get($customer, 'firstName') ?? data_get($customer, 'first_name')),
            'last_name' => $this->stringValue(data_get($customer, 'lastName') ?? data_get($customer, 'last_name')),
            'email' => $this->stringValue(data_get($customer, 'email') ?? data_get($payload, 'customerEmail')),
            'phone' => $this->stringValue(data_get($customer, 'phone') ?? data_get($customer, 'mobile') ?? data_get($payload, 'customerPhone')),
            'address_1' => $this->stringValue(data_get($customer, 'address1') ?? data_get($customer, 'address_1')),
            'postcode' => $this->stringValue(data_get($customer, 'postcode') ?? data_get($customer, 'zip')),
            'city' => $this->stringValue(data_get($customer, 'city')),
            'country' => $this->stringValue(data_get($customer, 'country')),
        ], fn (?string $value): bool => $value !== null);
    }

    private function extractLines(array $payload): Collection
    {
        $candidates = data_get($payload, 'lines')
            ?? data_get($payload, 'items')
            ?? data_get($payload, 'saleLines')
            ?? data_get($payload, 'productLines')
            ?? data_get($payload, 'receipt.lines')
            ?? [];

        return collect(is_array($candidates) ? $candidates : [])
            ->filter(fn (mixed $line): bool => is_array($line))
            ->map(fn (array $line): array => [
                'name' => $this->stringValue(data_get($line, 'name') ?? data_get($line, 'productName') ?? data_get($line, 'description')),
                'quantity' => abs($this->numberValue(data_get($line, 'quantity') ?? data_get($line, 'qty')) ?: 1),
                'unit_price' => $this->numberValue(data_get($line, 'unitPrice') ?? data_get($line, 'price')),
                'total' => $this->numberValue(data_get($line, 'total') ?? data_get($line, 'lineTotal') ?? data_get($line, 'amount')),
                'gtin' => $this->stringValue(data_get($line, 'gtin') ?? data_get($line, 'ean') ?? data_get($line, 'barcode')),
                'external_sku' => $this->stringValue(data_get($line, 'externalSKU') ?? data_get($line, 'externalSku') ?? data_get($line, 'external_sku')),
                'sku' => $this->stringValue(data_get($line, 'sku') ?? data_get($line, 'number')),
                'identity' => $this->stringValue(data_get($line, 'identity') ?? data_get($line, 'productSizeIdentity')),
                'front_product_id' => $this->stringValue(data_get($line, 'productid') ?? data_get($line, 'productId') ?? data_get($line, 'frontProductId')),
            ])
            ->values();
    }

    private function transactionType(array $payload, ?string $eventType, Collection $lines): string
    {
        $signals = strtolower(implode(' ', array_filter([
            $eventType,
            $this->stringValue(data_get($payload, 'type')),
            $this->stringValue(data_get($payload, 'eventType')),
            $this->stringValue(data_get($payload, 'status')),
            $this->stringValue(data_get($payload, 'reason')),
        ])));

        if (
            str_contains($signals, 'return')
            || str_contains($signals, 'refund')
            || str_contains($signals, 'void')
            || str_contains($signals, 'cancel')
        ) {
            return 'return';
        }

        $rawLines = data_get($payload, 'lines')
            ?? data_get($payload, 'items')
            ?? data_get($payload, 'saleLines')
            ?? data_get($payload, 'productLines')
            ?? data_get($payload, 'receipt.lines')
            ?? [];

        $hasNegativeLine = collect(is_array($rawLines) ? $rawLines : [])
            ->filter(fn (mixed $line): bool => is_array($line))
            ->contains(function (array $line): bool {
                $quantity = $this->numberValue(data_get($line, 'quantity') ?? data_get($line, 'qty'));
                $total = $this->numberValue(data_get($line, 'total') ?? data_get($line, 'lineTotal') ?? data_get($line, 'amount'));

                return ($quantity !== null && $quantity < 0) || ($total !== null && $total < 0);
            });

        $total = $this->numberValue(
            data_get($payload, 'totalAmount')
            ?? data_get($payload, 'total')
            ?? data_get($payload, 'amount')
        );

        if ($hasNegativeLine || ($total !== null && $total < 0)) {
            return 'return';
        }

        return 'sale';
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function numberValue(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function moneyString(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
