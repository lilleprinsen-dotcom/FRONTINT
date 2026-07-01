<?php

namespace App\Services\ProductSync;

use App\Models\AuditLog;
use App\Models\Connection;
use App\Models\ProductSyncRun;
use App\Models\ProductSyncRunItem;
use App\Models\User;
use App\Services\FrontSystems\FrontSystemsPriceListClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Throwable;

class FrontSalePriceSyncRunner
{
    public const MAX_ITEMS = 100;

    public function __construct(private readonly FrontSystemsPriceListClient $client)
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
        $items = $run->items()
            ->whereIn('id', $selectedIds)
            ->orderBy('id')
            ->get();
        $frontConnection = $this->frontConnection($run);
        $gateErrors = $this->gateErrors($run, $selectedIds, $items, $frontConnection);

        $this->audit($run, $user, [
            'action_status' => $gateErrors === [] ? 'started' : 'blocked',
            'selected_count' => $items->count(),
            'item_ids' => $selectedIds->all(),
            'gate_errors' => $gateErrors,
            'endpoint' => 'POST /api/PricelistV2',
            'writes_performed' => false,
        ]);

        if ($gateErrors !== []) {
            return [
                'status' => 'blocked',
                'gate_errors' => $gateErrors,
                'processed' => 0,
                'succeeded' => 0,
                'failed' => 0,
            ];
        }

        $succeeded = 0;
        $failed = 0;

        foreach ($items as $item) {
            $priceRow = $this->priceRow($item);
            $requestSummary = $this->requestSummary($run, $item, $priceRow);
            $item->update([
                'sale_price_sync_status' => 'running',
                'sale_price_attempt_count' => $item->sale_price_attempt_count + 1,
                'sale_price_last_attempted_at' => now(),
                'sale_price_last_error' => null,
                'sale_price_last_request_summary_json' => $requestSummary,
            ]);

            try {
                $response = $this->client->upsertPriceList(
                    $frontConnection,
                    $this->priceListName($run),
                    [$priceRow],
                );

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
            'endpoint' => 'POST /api/PricelistV2',
            'writes_performed' => $succeeded > 0,
        ]);

        return [
            'status' => $failed > 0 ? 'completed_with_errors' : 'completed',
            'gate_errors' => [],
            'processed' => $items->count(),
            'succeeded' => $succeeded,
            'failed' => $failed,
        ];
    }

    private function gateErrors(ProductSyncRun $run, Collection $selectedIds, Collection $items, ?Connection $frontConnection): array
    {
        $errors = [];

        if ((bool) config('omnibridge.allow_production_writes')) {
            $errors[] = 'Production writes must remain disabled for staging sale price sync.';
        }

        if (! in_array(($run->profile?->mode), ['limited_write_test', 'staging_batch'], true)) {
            $errors[] = 'Product sync profile mode must be limited_write_test or staging_batch.';
        }

        if (! in_array(($run->profile?->price_strategy), ['regular_price_now_sale_price_later', 'pricelist_v2_later'], true)) {
            $errors[] = 'Price strategy must allow sale price PriceListV2 sync.';
        }

        if (trim((string) $run->profile?->sale_price_list_name) === '') {
            $errors[] = 'Front sale price list name is required.';
        }

        if ($selectedIds->isEmpty()) {
            $errors[] = 'Select at least one sale price item.';
        }

        if ($selectedIds->count() > self::MAX_ITEMS) {
            $errors[] = 'Select no more than ' . self::MAX_ITEMS . ' sale price items.';
        }

        if ($items->count() !== $selectedIds->count()) {
            $errors[] = 'One or more selected sale price items were not found in this sync run.';
        }

        if (! $frontConnection) {
            $errors[] = 'A Front Systems connection is required.';
        } elseif (! $this->client->hasApiKey($frontConnection)) {
            $errors[] = 'Front Systems API key is required.';
        }

        if ($items->contains(fn (ProductSyncRunItem $item): bool => $item->sync_status !== 'synced')) {
            $errors[] = 'Sale price can only be sent after the product item is synced to Front.';
        }

        if ($items->contains(fn (ProductSyncRunItem $item): bool => $this->salePrice($item) === null)) {
            $errors[] = 'Each selected item must have a WooCommerce sale price candidate.';
        }

        if ($items->contains(fn (ProductSyncRunItem $item): bool => ! $this->hasFrontIdentifier($item))) {
            $errors[] = 'Each selected item must have a Front product ext id or GTIN.';
        }

        return array_values(array_unique($errors));
    }

    private function priceRow(ProductSyncRunItem $item): array
    {
        $row = [
            'price' => $this->salePrice($item),
        ];

        if ($item->front_product_ext_id) {
            $row['productExtId'] = $item->front_product_ext_id;
        } elseif ($item->detected_gtin) {
            $row['gtin'] = $item->detected_gtin;
        }

        return $row;
    }

    private function requestSummary(ProductSyncRun $run, ProductSyncRunItem $item, array $priceRow): array
    {
        return [
            'endpoint' => 'POST /api/PricelistV2',
            'price_list_name' => $this->priceListName($run),
            'woo_item_key' => $item->woo_item_key,
            'front_product_ext_id' => $item->front_product_ext_id,
            'gtin' => $item->detected_gtin,
            'sale_price' => $priceRow['price'] ?? null,
            'identifier' => isset($priceRow['productExtId']) ? 'productExtId' : 'gtin',
            'writes_stock' => false,
            'writes_woocommerce' => false,
        ];
    }

    private function markSucceeded(ProductSyncRunItem $item, Response $response): void
    {
        $item->update([
            'sale_price_sync_status' => 'synced',
            'sale_price_synced_at' => now(),
            'sale_price_last_error' => null,
            'sale_price_last_response_summary_json' => $this->responseSummary($response),
        ]);
    }

    private function markFailed(ProductSyncRunItem $item, string $error, ?Response $response): void
    {
        $item->update([
            'sale_price_sync_status' => 'failed',
            'sale_price_last_error' => $error,
            'sale_price_last_response_summary_json' => $response ? $this->responseSummary($response) : [
                'error' => $error,
            ],
        ]);
    }

    private function responseSummary(Response $response): array
    {
        $payload = $response->json();
        $payload = is_array($payload) ? $payload : [];

        return [
            'http_status' => $response->status(),
            'pricelistId' => $payload['pricelistId'] ?? null,
            'name' => $payload['name'] ?? null,
            'productCount' => $payload['productCount'] ?? null,
            'lastUpdate' => $payload['lastUpdate'] ?? null,
        ];
    }

    private function salePrice(ProductSyncRunItem $item): int|float|null
    {
        $payload = $item->proposed_front_payload_json ?? [];
        $value = $payload['sale_price_candidate'] ?? null;

        if (! is_numeric($value)) {
            return null;
        }

        return str_contains((string) $value, '.') ? (float) $value : (int) $value;
    }

    private function hasFrontIdentifier(ProductSyncRunItem $item): bool
    {
        return (bool) ($item->front_product_ext_id ?: $item->detected_gtin);
    }

    private function priceListName(ProductSyncRun $run): string
    {
        return trim((string) ($run->profile?->sale_price_list_name ?: 'WooCommerce Sale Prices'));
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
            'action' => 'front_sale_price_sync',
            'subject_type' => ProductSyncRun::class,
            'subject_id' => $run->id,
            'metadata_json' => $metadata + [
                'profile_mode' => $run->profile?->mode,
                'price_strategy' => $run->profile?->price_strategy,
                'production_writes_enabled' => (bool) config('omnibridge.allow_production_writes'),
            ],
        ]);
    }
}
