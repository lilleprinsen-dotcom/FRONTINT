<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Connection;
use App\Models\ConnectionDiscoverySnapshot;
use App\Models\Event;
use App\Models\FrontSaleImport;
use App\Models\ProductSyncRun;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TestingLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $organizations = $request->user()
            ->organizations()
            ->orderBy('name')
            ->get();
        $organizationIds = $organizations->pluck('id');

        $entries = collect()
            ->merge($this->connectionEntries($organizationIds))
            ->merge($this->discoveryEntries($organizationIds))
            ->merge($this->syncRunEntries($organizationIds))
            ->merge($this->frontSaleImportEntries($organizationIds))
            ->merge($this->eventEntries($organizationIds))
            ->merge($this->auditEntries($organizationIds))
            ->sortByDesc('at')
            ->take(80)
            ->values();

        return view('testing-log.index', [
            'organizations' => $organizations,
            'entries' => $entries,
            'copyText' => $this->copyText($entries),
            'productionWritesEnabled' => (bool) config('omnibridge.allow_production_writes'),
            'connectionHttpTestsEnabled' => (bool) config('omnibridge.allow_connection_test_http'),
        ]);
    }

    private function connectionEntries(Collection $organizationIds): Collection
    {
        return Connection::query()
            ->whereIn('organization_id', $organizationIds)
            ->whereNotNull('last_checked_at')
            ->with('organization')
            ->latest('last_checked_at')
            ->limit(30)
            ->get()
            ->map(fn (Connection $connection): array => [
                'at' => $connection->last_checked_at,
                'type' => 'Connection test',
                'system' => $this->systemLabel($connection->type),
                'status' => $this->plainStatus($connection->last_test_status ?: $connection->status),
                'title' => $connection->name,
                'summary' => $connection->last_error
                    ? 'Error: ' . $connection->last_error
                    : 'Last test: ' . ($connection->last_test_status ?: $connection->status),
                'details' => [
                    'Organization' => $connection->organization?->name,
                    'HTTP status' => $connection->last_http_status ?: 'n/a',
                    'Response time' => $connection->last_response_time_ms ? $connection->last_response_time_ms . ' ms' : 'n/a',
                ],
            ]);
    }

    private function discoveryEntries(Collection $organizationIds): Collection
    {
        return ConnectionDiscoverySnapshot::query()
            ->whereIn('organization_id', $organizationIds)
            ->with(['organization', 'connection'])
            ->latest('checked_at')
            ->limit(30)
            ->get()
            ->map(fn (ConnectionDiscoverySnapshot $snapshot): array => [
                'at' => $snapshot->checked_at,
                'type' => 'Discovery',
                'system' => $this->systemLabel($snapshot->source_system),
                'status' => $this->plainStatus($snapshot->status),
                'title' => ucfirst($snapshot->discovery_type) . ' sample',
                'summary' => $snapshot->error_message ?: $this->summaryLine($snapshot->summary_json ?? []),
                'details' => [
                    'Organization' => $snapshot->organization?->name,
                    'Connection' => $snapshot->connection?->name,
                    'Read-only' => data_get($snapshot->summary_json, 'read_only') ? 'yes' : 'not recorded',
                ],
            ]);
    }

    private function syncRunEntries(Collection $organizationIds): Collection
    {
        return ProductSyncRun::query()
            ->whereIn('organization_id', $organizationIds)
            ->with('organization')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (ProductSyncRun $run): array => [
                'at' => $run->updated_at,
                'type' => 'Product sync run',
                'system' => 'Woo to Front',
                'status' => $this->plainStatus($run->status),
                'title' => ucfirst(str_replace('_', ' ', $run->run_type)),
                'summary' => "{$run->total_synced} synced, {$run->total_failed} failed, {$run->total_blocked} blocked",
                'details' => [
                    'Organization' => $run->organization?->name,
                    'Run ID' => $run->id,
                    'Mode' => $run->mode,
                    'Ready' => $run->total_ready,
                    'Pending' => $run->total_pending,
                ],
            ]);
    }

    private function frontSaleImportEntries(Collection $organizationIds): Collection
    {
        return FrontSaleImport::query()
            ->whereIn('organization_id', $organizationIds)
            ->with(['organization', 'orderMapping'])
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (FrontSaleImport $import): array => [
                'at' => $import->updated_at,
                'type' => $import->transaction_type === 'return' ? 'Front return stock' : 'Front sale stock',
                'system' => 'Front to WooCommerce',
                'status' => $this->plainStatus($import->stock_status),
                'title' => $import->front_receipt_id ?: $import->front_sale_id ?: ($import->transaction_type === 'return' ? 'Front return' : 'Front sale'),
                'summary' => $import->stock_error_message
                    ?: 'Type: ' . $import->transaction_type . ', stock: ' . $import->stock_status . ', optional order: ' . $import->order_import_status,
                'details' => [
                    'Organization' => $import->organization?->name,
                    'Import ID' => $import->id,
                    'Type' => $import->transaction_type,
                    'Line count' => count($import->line_items_json ?? []),
                    'Stock status' => $import->stock_status,
                    'Order status' => $import->order_import_status,
                    'Woo order ID' => $import->orderMapping?->woo_order_id ?: 'n/a',
                ],
            ]);
    }

    private function eventEntries(Collection $organizationIds): Collection
    {
        return Event::query()
            ->whereIn('organization_id', $organizationIds)
            ->with('organization')
            ->latest('received_at')
            ->limit(20)
            ->get()
            ->map(fn (Event $event): array => [
                'at' => $event->received_at ?: $event->created_at,
                'type' => 'Webhook event',
                'system' => $this->systemLabel($event->source_system),
                'status' => $this->plainStatus($event->status),
                'title' => $event->event_type,
                'summary' => $event->error_message ?: 'Webhook accepted.',
                'details' => [
                    'Organization' => $event->organization?->name,
                    'Event ID' => $event->id,
                ],
            ]);
    }

    private function auditEntries(Collection $organizationIds): Collection
    {
        return AuditLog::query()
            ->whereIn('organization_id', $organizationIds)
            ->with('organization')
            ->latest('created_at')
            ->limit(30)
            ->get()
            ->map(fn (AuditLog $audit): array => [
                'at' => $audit->created_at,
                'type' => 'Action',
                'system' => 'Portal',
                'status' => $this->plainStatus((string) data_get($audit->metadata_json, 'status', 'recorded')),
                'title' => str_replace('_', ' ', $audit->action),
                'summary' => $this->summaryLine($audit->metadata_json ?? []),
                'details' => [
                    'Organization' => $audit->organization?->name,
                    'Action ID' => $audit->id,
                ],
            ]);
    }

    private function copyText(Collection $entries): string
    {
        $lines = [
            'OmniBridge testing log',
            'Generated at: ' . now()->toDateTimeString(),
            'Production writes: ' . (config('omnibridge.allow_production_writes') ? 'enabled' : 'disabled'),
            'Live HTTP tests: ' . (config('omnibridge.allow_connection_test_http') ? 'enabled' : 'disabled'),
            '',
        ];

        foreach ($entries->take(25) as $entry) {
            $lines[] = sprintf(
                '[%s] %s | %s | %s | %s',
                optional($entry['at'])->toDateTimeString() ?: 'unknown time',
                $entry['type'],
                $entry['system'],
                $entry['status'],
                $entry['title'],
            );
            $lines[] = 'Result: ' . ($entry['summary'] ?: 'No summary saved.');
        }

        return implode("\n", $lines);
    }

    private function plainStatus(?string $status): string
    {
        return match ($status) {
            'success', 'connected', 'active', 'completed', 'synced', 'ready' => 'Worked',
            'imported' => 'Worked',
            'adjusted' => 'Worked',
            'failed', 'error', 'blocked', 'completed_with_errors' => 'Needs attention',
            'skipped', 'safe_mode' => 'Skipped safely',
            'queued', 'running', 'pending' => 'Running or waiting',
            default => $status ? ucfirst(str_replace('_', ' ', $status)) : 'Not checked',
        };
    }

    private function systemLabel(?string $system): string
    {
        return match ($system) {
            'woocommerce' => 'WooCommerce',
            'front', 'front_systems' => 'Front Systems',
            default => $system ? ucfirst(str_replace('_', ' ', $system)) : 'Unknown',
        };
    }

    private function summaryLine(array $data): string
    {
        $allowed = collect($data)
            ->only([
                'count',
                'variation_count',
                'selected_count',
                'status',
                'endpoint',
                'limit',
                'read_only',
                'items_dispatched',
                'synced',
                'failed',
            ])
            ->map(fn ($value, string $key): string => $key . ': ' . (is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value))
            ->values();

        return $allowed->isEmpty() ? 'No short summary saved.' : $allowed->implode(', ');
    }
}
