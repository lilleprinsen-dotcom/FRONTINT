<?php

namespace App\Services\ProductSync;

use App\Models\AuditLog;
use App\Models\Connection;
use App\Models\ProductMapping;
use App\Models\ProductSyncRun;
use App\Models\ProductSyncRunItem;
use App\Models\User;
use App\Services\FrontSystems\FrontSystemsProductWriteClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class LimitedFrontProductWriteRunner
{
    public function __construct(private readonly FrontSystemsProductWriteClient $client)
    {
    }

    public function run(ProductSyncRun $run, User $user, array $itemIds): array
    {
        $run->loadMissing(['profile', 'organization.connections.credentials']);
        $selectedIds = collect($itemIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
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
            'endpoint' => 'POST /api/products or PUT /api/products/{productId}',
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
            $item->update([
                'sync_status' => 'running',
                'attempt_count' => $item->attempt_count + 1,
                'last_attempted_at' => now(),
                'last_error' => null,
                'last_request_summary_json' => $this->requestSummary($item),
            ]);

            try {
                $decision = $this->writeDecision($frontConnection, $item);
                $item->update([
                    'last_request_summary_json' => $this->requestSummary($item, $decision),
                ]);
                $response = $decision['method'] === 'update'
                    ? $this->client->updateProduct($frontConnection, (string) $decision['target'], $this->frontPayload($item))
                    : $this->client->createProduct($frontConnection, $this->frontPayload($item));

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

        $run->refresh();
        $failedTotal = $run->items()->where('sync_status', 'failed')->count();
        $pendingTotal = $run->items()->whereIn('sync_status', ['not_started', 'queued', 'running', 'needs_retry'])->count();
        $completedStatus = $pendingTotal > 0
            ? 'running'
            : ($failedTotal > 0 ? 'completed_with_errors' : 'completed');
        $run->update([
            'total_synced' => $run->items()->where('sync_status', 'synced')->count(),
            'total_failed' => $failedTotal,
            'total_pending' => $pendingTotal,
            'status' => $completedStatus,
            'finished_at' => $pendingTotal > 0 ? null : now(),
        ]);

        $this->audit($run, $user, [
            'action_status' => $completedStatus,
            'selected_count' => $items->count(),
            'processed' => $items->count(),
            'succeeded' => $succeeded,
            'failed' => $failed,
            'endpoint' => 'POST /api/products or PUT /api/products/{productId}',
            'writes_performed' => $succeeded > 0,
        ]);

        return [
            'status' => $completedStatus,
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
            $errors[] = 'Production writes must remain disabled for the first limited Front write test.';
        }

        if (! in_array(($run->profile?->mode), ['limited_write_test', 'staging_batch'], true)) {
            $errors[] = 'Product sync profile mode must be limited_write_test or staging_batch.';
        }

        if ($selectedIds->isEmpty()) {
            $errors[] = 'Select at least one product or variation.';
        }

        if ($selectedIds->count() > StagingBatchProductSyncRunBuilder::MAX_ITEMS) {
            $errors[] = 'Select no more than ' . StagingBatchProductSyncRunBuilder::MAX_ITEMS . ' items.';
        }

        if ($items->count() !== $selectedIds->count()) {
            $errors[] = 'One or more selected items were not found in this sync run.';
        }

        if (! $this->wooConnection($run)) {
            $errors[] = 'A WooCommerce staging connection is required.';
        }

        if (! $frontConnection) {
            $errors[] = 'A Front Systems connection is required.';
        } elseif (! $this->client->hasApiKey($frontConnection)) {
            $errors[] = 'Front Systems API key is required.';
        }

        if ($items->contains(fn (ProductSyncRunItem $item): bool => $item->validation_status === 'blocked')) {
            $errors[] = 'Blocked items cannot be written to Front.';
        }

        if ($items->contains(fn (ProductSyncRunItem $item): bool => ! in_array($item->validation_status, ['ready', 'warning'], true))) {
            $errors[] = 'Only ready or warning items can be written.';
        }

        return array_values(array_unique($errors));
    }

    private function frontConnection(ProductSyncRun $run): ?Connection
    {
        return $run->organization
            ?->connections
            ->first(fn (Connection $connection): bool => in_array($connection->type, ['front_systems', 'front'], true));
    }

    private function wooConnection(ProductSyncRun $run): ?Connection
    {
        return $run->organization
            ?->connections
            ->first(fn (Connection $connection): bool => $connection->type === 'woocommerce');
    }

    private function frontPayload(ProductSyncRunItem $item): array
    {
        $payload = $item->proposed_front_payload_json ?? [];
        $size = $payload['productSizes'][0] ?? [];
        $image = $payload['image_candidate']['src'] ?? null;

        return array_filter([
            'createProductSpecificSize' => true,
            'extId' => $this->frontExtId($item),
            'name' => $payload['name'] ?? $item->woo_name,
            'number' => $payload['number'] ?? $item->woo_sku,
            'variant' => $payload['variant'] ?? null,
            'groupName' => $payload['groupName'] ?? null,
            'subgroupName' => $payload['subgroupName'] ?? null,
            'color' => null,
            'season' => null,
            'brand' => $payload['brand'] ?? null,
            'price' => $this->numberOrNull($payload['price_candidate'] ?? null),
            'isStockProduct' => true,
            'isWebAvailable' => true,
            'productSizes' => [[
                'gtin' => $size['gtin'] ?? $item->detected_gtin,
                'label' => $size['label'] ?? null,
                'externalSKU' => $size['externalSKU'] ?? $item->woo_sku,
            ]],
            'images' => $image ? [$image] : null,
            'isNoLabel' => false,
        ], fn (mixed $value): bool => $value !== null);
    }

    private function markSucceeded(ProductSyncRunItem $item, Response $response): void
    {
        DB::transaction(function () use ($item, $response): void {
            $summary = $this->responseSummary($response);
            $size = $summary['product_sizes'][0] ?? [];

            $item->update([
                'sync_status' => 'synced',
                'synced_at' => now(),
                'front_product_id' => $summary['productid'] ?? $item->front_product_id,
                'front_product_ext_id' => $summary['extId'] ?? $this->frontExtId($item),
                'front_identity' => $size['identity'] ?? $item->front_identity,
                'front_external_sku' => $size['externalSKU'] ?? $item->front_external_sku,
                'last_response_summary_json' => $summary,
                'last_error' => null,
            ]);

            ProductMapping::query()->updateOrCreate(
                [
                    'organization_id' => $item->organization_id,
                    'woo_item_key' => $item->woo_item_key,
                ],
                [
                    'woo_product_id' => $item->woo_product_id,
                    'woo_variation_id' => $item->woo_variation_id,
                    'front_product_id' => (string) ($summary['productid'] ?? $item->front_product_id ?? ''),
                    'front_product_ext_id' => (string) ($summary['extId'] ?? $this->frontExtId($item)),
                    'front_identity' => $size['identity'] ?? $item->front_identity,
                    'sku' => $item->woo_sku,
                    'gtin' => $item->detected_gtin,
                    'external_sku' => $size['externalSKU'] ?? $item->front_external_sku,
                    'sync_status' => 'synced',
                    'last_synced_at' => now(),
                ],
            );
        });
    }

    private function markFailed(ProductSyncRunItem $item, string $error, ?Response $response): void
    {
        $item->update([
            'sync_status' => 'failed',
            'last_error' => $error,
            'last_response_summary_json' => $response ? $this->responseSummary($response) : [
                'error' => $error,
            ],
        ]);
    }

    private function writeDecision(Connection $connection, ProductSyncRunItem $item): array
    {
        $mapping = ProductMapping::query()
            ->where('organization_id', $item->organization_id)
            ->where('woo_item_key', $item->woo_item_key)
            ->first();

        if ($mapping && ($mapping->front_product_ext_id || $mapping->front_product_id)) {
            return [
                'method' => 'update',
                'target' => $mapping->front_product_ext_id ?: $mapping->front_product_id,
                'source' => 'product_mapping',
            ];
        }

        $extId = $this->frontExtId($item);
        $extResponse = $this->client->getProduct($connection, $extId);

        if ($extResponse->successful()) {
            return [
                'method' => 'update',
                'target' => $extId,
                'source' => 'front_extid_lookup',
            ];
        }

        if ($item->detected_gtin) {
            $gtinResponse = $this->client->getProductByGtin($connection, $item->detected_gtin);

            if ($gtinResponse->successful()) {
                $summary = $this->responseSummary($gtinResponse);

                return [
                    'method' => 'update',
                    'target' => (string) ($summary['productid'] ?? $summary['extId'] ?? $extId),
                    'source' => 'front_gtin_lookup',
                ];
            }
        }

        return [
            'method' => 'create',
            'target' => null,
            'source' => 'no_existing_front_match',
        ];
    }

    private function requestSummary(ProductSyncRunItem $item, array $decision = ['method' => 'create', 'source' => 'not_checked', 'target' => null]): array
    {
        $payload = $this->frontPayload($item);
        $sourcePayload = $item->proposed_front_payload_json ?? [];

        return [
            'endpoint' => $decision['method'] === 'update' ? 'PUT /api/products/{productId}' : 'POST /api/products',
            'decision' => $decision['method'],
            'decision_source' => $decision['source'],
            'target' => $decision['target'],
            'payload_hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            'extId' => $payload['extId'] ?? null,
            'name' => $payload['name'] ?? null,
            'number' => $payload['number'] ?? null,
            'variant' => $payload['variant'] ?? null,
            'gtin' => $payload['productSizes'][0]['gtin'] ?? null,
            'externalSKU' => $payload['productSizes'][0]['externalSKU'] ?? null,
            'includes_sale_price' => false,
            'regular_price' => $payload['price'] ?? null,
            'sale_price_candidate' => $sourcePayload['sale_price_candidate'] ?? null,
            'sale_price_destination' => 'future PriceListV2 candidate',
            'includes_stock' => false,
        ];
    }

    private function responseSummary(Response $response): array
    {
        $json = $response->json();
        $payload = is_array($json) ? $json : [];
        $sizes = collect($payload['productSizes'] ?? [])
            ->filter(fn (mixed $size): bool => is_array($size))
            ->map(fn (array $size): array => [
                'identity' => $size['identity'] ?? null,
                'gtin' => $size['gtin'] ?? null,
                'externalSKU' => $size['externalSKU'] ?? null,
            ])
            ->values()
            ->all();

        return [
            'http_status' => $response->status(),
            'id' => $payload['id'] ?? null,
            'extId' => $payload['extId'] ?? null,
            'productid' => $payload['productid'] ?? null,
            'number' => $payload['number'] ?? null,
            'variant' => $payload['variant'] ?? null,
            'product_sizes' => $sizes,
        ];
    }

    private function frontExtId(ProductSyncRunItem $item): string
    {
        // WooCommerce IDs are immutable in Woo. SKU and GTIN/EAN are mutable sale identifiers,
        // so they must not be the primary cross-system mapping key.
        return str_replace(':', '-', 'woo-' . $item->woo_item_key);
    }

    private function numberOrNull(mixed $value): int|float|null
    {
        if (! is_numeric($value)) {
            return null;
        }

        return str_contains((string) $value, '.') ? (float) $value : (int) $value;
    }

    private function audit(ProductSyncRun $run, User $user, array $metadata): void
    {
        AuditLog::query()->create([
            'organization_id' => $run->organization_id,
            'user_id' => $user->id,
            'action' => 'limited_front_product_write_test',
            'subject_type' => ProductSyncRun::class,
            'subject_id' => $run->id,
            'metadata_json' => $metadata + [
                'profile_mode' => $run->profile?->mode,
                'production_writes_enabled' => (bool) config('omnibridge.allow_production_writes'),
            ],
        ]);
    }
}
