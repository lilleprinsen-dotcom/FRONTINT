<?php

namespace App\Services\ProductSync;

use App\Models\AuditLog;
use App\Models\Connection;
use App\Models\ProductSyncRun;
use App\Models\ProductSyncRunItem;
use App\Models\User;
use App\Services\FrontSystems\FrontSystemsStockClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Throwable;

class FrontStockSyncRunner
{
    public const MAX_ITEMS = 100;

    public function __construct(private readonly FrontSystemsStockClient $client)
    {
    }

    /**
     * @param array<int, int> $itemIds
     */
    public function run(ProductSyncRun $run, User $user, array $itemIds): array
    {
        $run->loadMissing(['profile', 'organization.connections.credentials']);
        $selectedIds = collect($itemIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->take(self::MAX_ITEMS)
            ->values();
        $items = $run->items()->whereIn('id', $selectedIds)->orderBy('id')->get();
        $frontConnection = $this->frontConnection($run);
        $gateErrors = $this->gateErrors($run, $selectedIds, $items, $frontConnection);

        $this->audit($run, $user, [
            'action_status' => $gateErrors === [] ? 'started' : 'blocked',
            'selected_count' => $items->count(),
            'item_ids' => $selectedIds->all(),
            'gate_errors' => $gateErrors,
            'endpoint' => 'POST /api/Stock/adjust',
            'writes_performed' => false,
        ]);

        if ($gateErrors !== []) {
            return ['status' => 'blocked', 'gate_errors' => $gateErrors, 'processed' => 0, 'succeeded' => 0, 'failed' => 0];
        }

        $succeeded = 0;
        $failed = 0;

        foreach ($items as $item) {
            $payload = $this->payload($run, $item);
            $item->update([
                'stock_sync_status' => 'running',
                'stock_attempt_count' => $item->stock_attempt_count + 1,
                'stock_last_attempted_at' => now(),
                'stock_last_error' => null,
                'stock_last_request_summary_json' => $this->requestSummary($payload, $item),
            ]);

            try {
                $response = $this->client->adjustStock($frontConnection, $payload);

                if ($response->successful()) {
                    $this->markSucceeded($item->fresh(), $response);
                    $succeeded++;
                    continue;
                }

                $this->markFailed($item->fresh(), 'HTTP ' . $response->status(), $response);
                $failed++;
            } catch (Throwable $exception) {
                $this->markFailed($item->fresh(), $exception::class, null);
                $failed++;
            }
        }

        $this->audit($run, $user, [
            'action_status' => $failed > 0 ? 'completed_with_errors' : 'completed',
            'selected_count' => $items->count(),
            'processed' => $items->count(),
            'succeeded' => $succeeded,
            'failed' => $failed,
            'endpoint' => 'POST /api/Stock/adjust',
            'writes_performed' => $succeeded > 0,
        ]);

        return ['status' => $failed > 0 ? 'completed_with_errors' : 'completed', 'gate_errors' => [], 'processed' => $items->count(), 'succeeded' => $succeeded, 'failed' => $failed];
    }

    private function gateErrors(ProductSyncRun $run, Collection $selectedIds, Collection $items, ?Connection $frontConnection): array
    {
        $errors = [];

        if ((bool) config('omnibridge.allow_production_writes')) {
            $errors[] = 'Production writes must remain disabled for staging stock sync.';
        }

        if (! in_array(($run->profile?->mode), ['limited_write_test', 'staging_batch'], true)) {
            $errors[] = 'Product sync profile mode must be limited_write_test or staging_batch.';
        }

        if (($run->profile?->stock_strategy) !== 'stock_sync_later') {
            $errors[] = 'Stock strategy must be stock_sync_later.';
        }

        if (! $run->profile?->front_stock_id && ! $run->profile?->front_stock_ext_id) {
            $errors[] = 'Front stock ID or external stock ID is required.';
        }

        if ($selectedIds->isEmpty()) {
            $errors[] = 'Select at least one stock item.';
        }

        if ($selectedIds->count() > self::MAX_ITEMS) {
            $errors[] = 'Select no more than ' . self::MAX_ITEMS . ' stock items.';
        }

        if ($items->count() !== $selectedIds->count()) {
            $errors[] = 'One or more selected stock items were not found in this sync run.';
        }

        if (! $frontConnection) {
            $errors[] = 'A Front Systems connection is required.';
        } elseif (! $this->client->hasApiKey($frontConnection)) {
            $errors[] = 'Front Systems API key is required.';
        }

        if ($items->contains(fn (ProductSyncRunItem $item): bool => $item->sync_status !== 'synced')) {
            $errors[] = 'Stock can only be sent after the product item is synced to Front.';
        }

        if ($items->contains(fn (ProductSyncRunItem $item): bool => $item->woo_stock_quantity === null)) {
            $errors[] = 'Each selected item must have a WooCommerce stock quantity.';
        }

        if ($items->contains(fn (ProductSyncRunItem $item): bool => ! $item->detected_gtin && ! $item->front_external_sku && ! $item->woo_sku)) {
            $errors[] = 'Each selected item must have GTIN or external SKU for Front stock adjustment.';
        }

        return array_values(array_unique($errors));
    }

    private function payload(ProductSyncRun $run, ProductSyncRunItem $item): array
    {
        $payload = [
            'description' => 'OmniBridge WooCommerce staging stock sync',
            'stockCountTime' => now()->toISOString(),
            'isCompleteStockCount' => false,
            'saveAsStockCount' => true,
            'items' => [[
                'quantity' => $item->woo_stock_quantity,
                'externalSKU' => $item->front_external_sku ?: $item->woo_sku,
            ]],
        ];

        if ($run->profile?->front_stock_id) {
            $payload['stockId'] = $run->profile->front_stock_id;
        }

        if ($run->profile?->front_stock_ext_id) {
            $payload['stockExtId'] = $run->profile->front_stock_ext_id;
        }

        if ($item->detected_gtin) {
            $payload['items'][0]['gtin'] = $item->detected_gtin;
        }

        return $payload;
    }

    private function requestSummary(array $payload, ProductSyncRunItem $item): array
    {
        return [
            'endpoint' => 'POST /api/Stock/adjust',
            'woo_item_key' => $item->woo_item_key,
            'stockId' => $payload['stockId'] ?? null,
            'stockExtId' => $payload['stockExtId'] ?? null,
            'quantity' => $payload['items'][0]['quantity'] ?? null,
            'gtin' => $payload['items'][0]['gtin'] ?? null,
            'externalSKU' => $payload['items'][0]['externalSKU'] ?? null,
            'isCompleteStockCount' => false,
            'saveAsStockCount' => true,
            'writes_woocommerce' => false,
        ];
    }

    private function markSucceeded(ProductSyncRunItem $item, Response $response): void
    {
        $item->update([
            'stock_sync_status' => 'synced',
            'stock_synced_at' => now(),
            'stock_last_error' => null,
            'stock_last_response_summary_json' => $this->responseSummary($response),
        ]);
    }

    private function markFailed(ProductSyncRunItem $item, string $error, ?Response $response): void
    {
        $item->update([
            'stock_sync_status' => 'failed',
            'stock_last_error' => $error,
            'stock_last_response_summary_json' => $response ? $this->responseSummary($response) : ['error' => $error],
        ]);
    }

    private function responseSummary(Response $response): array
    {
        return [
            'http_status' => $response->status(),
            'message' => is_scalar($response->json()) ? $response->json() : null,
        ];
    }

    private function frontConnection(ProductSyncRun $run): ?Connection
    {
        return $run->organization
            ?->connections
            ->first(fn (Connection $connection): bool => in_array($connection->type, ['front_systems', 'front'], true));
    }

    private function audit(ProductSyncRun $run, User $user, array $metadata): void
    {
        AuditLog::query()->create([
            'organization_id' => $run->organization_id,
            'user_id' => $user->id,
            'action' => 'front_stock_sync',
            'subject_type' => ProductSyncRun::class,
            'subject_id' => $run->id,
            'metadata_json' => $metadata + [
                'profile_mode' => $run->profile?->mode,
                'stock_strategy' => $run->profile?->stock_strategy,
                'production_writes_enabled' => (bool) config('omnibridge.allow_production_writes'),
            ],
        ]);
    }
}
