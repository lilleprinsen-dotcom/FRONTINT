<?php

namespace App\Services\Sales;

use App\Models\AuditLog;
use App\Models\Connection;
use App\Models\FrontSaleImport;
use App\Models\StockLedger;
use App\Models\User;
use App\Services\WooCommerce\WooCommerceStockWriteClient;
use Illuminate\Support\Facades\DB;
use Throwable;

class FrontSaleStockAdjustmentRunner
{
    public function __construct(private readonly WooCommerceStockWriteClient $client)
    {
    }

    public function run(FrontSaleImport $import, ?User $user = null): array
    {
        $import->loadMissing('organization.connections.credentials');
        $wooConnection = $this->wooConnection($import);
        $gateErrors = $this->gateErrors($import, $wooConnection);

        $this->audit($import, $user, [
            'status' => $gateErrors === [] ? 'started' : 'blocked',
            'gate_errors' => $gateErrors,
            'transaction_type' => $import->transaction_type,
            'writes_woocommerce_stock' => false,
            'creates_woocommerce_order' => false,
            'writes_front' => false,
        ]);

        if ($gateErrors !== []) {
            $import->update([
                'stock_status' => 'blocked',
                'stock_error_message' => implode(' ', $gateErrors),
            ]);

            return ['status' => 'blocked', 'gate_errors' => $gateErrors];
        }

        $import->update([
            'stock_status' => 'running',
            'stock_error_message' => null,
            'stock_attempt_count' => $import->stock_attempt_count + 1,
            'stock_request_summary_json' => $this->requestSummary($import),
        ]);

        try {
            $adjustments = [];

            foreach (collect($import->line_items_json ?? []) as $line) {
                $adjustments[] = $this->adjustLine($wooConnection, $import, $line);
            }

            $import->update([
                'status' => 'stock_adjusted',
                'stock_status' => 'adjusted',
                'stock_adjusted_at' => now(),
                'stock_response_summary_json' => [
                    'adjusted_lines' => count($adjustments),
                    'adjustments' => $adjustments,
                    'transaction_type' => $import->transaction_type,
                ],
                'stock_error_message' => null,
            ]);

            $this->audit($import->fresh(), $user, [
                'status' => 'adjusted',
                'transaction_type' => $import->transaction_type,
                'adjusted_lines' => count($adjustments),
                'writes_woocommerce_stock' => true,
                'creates_woocommerce_order' => false,
                'writes_front' => false,
            ]);

            return ['status' => 'adjusted', 'gate_errors' => []];
        } catch (Throwable $exception) {
            $import->update([
                'stock_status' => 'failed',
                'stock_error_message' => $exception->getMessage() ?: $exception::class,
                'stock_response_summary_json' => ['error' => $exception::class],
            ]);

            $this->audit($import->fresh(), $user, [
                'status' => 'failed',
                'error' => $exception::class,
                'writes_woocommerce_stock' => false,
                'creates_woocommerce_order' => false,
                'writes_front' => false,
            ]);

            return ['status' => 'failed', 'gate_errors' => []];
        }
    }

    private function gateErrors(FrontSaleImport $import, ?Connection $wooConnection): array
    {
        $errors = [];

        if ($import->stock_status === 'adjusted') {
            $errors[] = 'Stock has already been adjusted for this Front sale.';
        }

        if (! $wooConnection) {
            $errors[] = 'A WooCommerce staging connection is required.';
        } elseif (! $this->client->hasCredentials($wooConnection)) {
            $errors[] = 'WooCommerce consumer key and consumer secret are required.';
        }

        $lines = collect($import->line_items_json ?? []);

        if ($lines->isEmpty()) {
            $errors[] = 'Front sale has no line items.';
        }

        if ($lines->contains(fn (array $line): bool => ($line['mapping_status'] ?? null) !== 'matched')) {
            $errors[] = 'All Front sale lines must match synced WooCommerce products before stock can be changed.';
        }

        return array_values(array_unique($errors));
    }

    private function adjustLine(Connection $connection, FrontSaleImport $import, array $line): array
    {
        $productId = (int) ($line['woo_product_id'] ?? 0);
        $variationId = (int) ($line['woo_variation_id'] ?? 0);
        $quantity = max(1, (int) round(abs((float) ($line['quantity'] ?? 1))));

        $readResponse = $variationId > 0
            ? $this->client->getVariation($connection, $productId, $variationId)
            : $this->client->getProduct($connection, $productId);

        if (! $readResponse->successful()) {
            throw new \RuntimeException('Could not read WooCommerce stock before adjustment. HTTP ' . $readResponse->status());
        }

        $currentStock = $readResponse->json('stock_quantity');

        if (! is_numeric($currentStock)) {
            throw new \RuntimeException('WooCommerce stock quantity is missing for ' . ($line['woo_item_key'] ?? 'unknown item') . '.');
        }

        $delta = $this->stockDelta($import, $quantity);
        $newStock = max(0, (int) $currentStock + $delta);
        $writeResponse = $variationId > 0
            ? $this->client->updateVariationStock($connection, $productId, $variationId, $newStock)
            : $this->client->updateProductStock($connection, $productId, $newStock);

        if (! $writeResponse->successful()) {
            throw new \RuntimeException('Could not update WooCommerce stock. HTTP ' . $writeResponse->status());
        }

        $this->recordStockLedger($import, $line, $delta, (int) $currentStock, $newStock);

        return [
            'woo_item_key' => $line['woo_item_key'] ?? null,
            'transaction_type' => $import->transaction_type,
            'quantity' => $quantity,
            'quantity_delta' => $delta,
            'stock_before' => (int) $currentStock,
            'stock_after' => $newStock,
        ];
    }

    private function stockDelta(FrontSaleImport $import, int $quantity): int
    {
        return $import->transaction_type === 'return' ? $quantity : -1 * $quantity;
    }

    private function recordStockLedger(FrontSaleImport $import, array $line, int $quantityDelta, int $stockBefore, int $stockAfter): void
    {
        $productMappingId = (int) ($line['product_mapping_id'] ?? 0);

        if ($productMappingId <= 0) {
            return;
        }

        StockLedger::query()->firstOrCreate(
            [
                'organization_id' => $import->organization_id,
                'idempotency_key' => $import->idempotency_key . ':' . ($line['woo_item_key'] ?? $productMappingId),
            ],
            [
                'product_mapping_id' => $productMappingId,
                'source_system' => 'front',
                'movement_type' => $import->transaction_type === 'return' ? 'front_pos_return' : 'front_pos_sale',
                'quantity_delta' => $quantityDelta,
                'physical_quantity_after' => $stockAfter,
                'available_quantity_after' => $stockAfter,
                'source_reference' => $import->front_receipt_id ?: $import->front_sale_id,
            ],
        );
    }

    private function requestSummary(FrontSaleImport $import): array
    {
        return [
            'operation' => 'front_sale_stock_adjustment',
            'transaction_type' => $import->transaction_type,
            'line_count' => count($import->line_items_json ?? []),
            'writes_woocommerce_stock' => true,
            'creates_woocommerce_order' => false,
            'writes_front' => false,
        ];
    }

    private function wooConnection(FrontSaleImport $import): ?Connection
    {
        return $import->organization
            ?->connections
            ->first(fn (Connection $connection): bool => $connection->type === 'woocommerce');
    }

    private function audit(FrontSaleImport $import, ?User $user, array $metadata): void
    {
        AuditLog::query()->create([
            'organization_id' => $import->organization_id,
            'user_id' => $user?->id,
            'action' => 'front_sale_stock_adjustment',
            'subject_type' => FrontSaleImport::class,
            'subject_id' => $import->id,
            'metadata_json' => array_merge([
                'front_sale_import_id' => $import->id,
                'front_sale_id' => $import->front_sale_id,
                'front_receipt_id' => $import->front_receipt_id,
                'transaction_type' => $import->transaction_type,
            ], $metadata),
        ]);
    }
}
