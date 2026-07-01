<?php

namespace App\Services\Sales;

use App\Models\AuditLog;
use App\Models\Connection;
use App\Models\FrontSaleImport;
use App\Models\OrderMapping;
use App\Models\User;
use App\Services\WooCommerce\WooCommerceOrderWriteClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Throwable;

class FrontSaleImportRunner
{
    public function __construct(private readonly WooCommerceOrderWriteClient $client)
    {
    }

    public function run(FrontSaleImport $import, User $user): array
    {
        $import->loadMissing('organization.connections.credentials', 'orderMapping');
        $wooConnection = $this->wooConnection($import);
        $gateErrors = $this->gateErrors($import, $wooConnection);

        $this->audit($import, $user, [
            'status' => $gateErrors === [] ? 'started' : 'blocked',
            'gate_errors' => $gateErrors,
            'endpoint' => 'POST /wp-json/wc/v3/orders',
            'writes_woocommerce' => false,
            'writes_front' => false,
        ]);

        if ($gateErrors !== []) {
            $import->update([
                'order_import_status' => 'blocked',
                'error_message' => implode(' ', $gateErrors),
            ]);

            return ['status' => 'blocked', 'gate_errors' => $gateErrors];
        }

        $import->update([
            'order_import_status' => 'running',
            'attempt_count' => $import->attempt_count + 1,
            'error_message' => null,
            'last_request_summary_json' => $this->requestSummary($import),
        ]);

        try {
            $response = $this->client->createOrder($wooConnection, $import->woo_order_payload_json ?? []);

            if ($response->successful()) {
                $this->markImported($import->fresh(), $response);

                $this->audit($import->fresh(), $user, [
                    'status' => 'imported',
                    'endpoint' => 'POST /wp-json/wc/v3/orders',
                    'writes_woocommerce' => true,
                    'writes_front' => false,
                    'woo_order_id' => $import->fresh()->orderMapping?->woo_order_id,
                ]);

                return ['status' => 'imported', 'gate_errors' => []];
            }

            $this->markFailed($import->fresh(), 'HTTP ' . $response->status(), $response);
        } catch (Throwable $exception) {
            $this->markFailed($import->fresh(), $exception::class, null);
        }

        $this->audit($import->fresh(), $user, [
            'status' => 'failed',
            'endpoint' => 'POST /wp-json/wc/v3/orders',
            'writes_woocommerce' => false,
            'writes_front' => false,
            'error' => $import->fresh()->error_message,
        ]);

        return ['status' => 'failed', 'gate_errors' => []];
    }

    private function gateErrors(FrontSaleImport $import, ?Connection $wooConnection): array
    {
        $errors = [];

        if ((bool) config('omnibridge.allow_production_writes')) {
            $errors[] = 'Production writes must remain disabled for staging Front sale import.';
        }

        if ($import->orderMapping?->woo_order_id) {
            $errors[] = 'This Front sale is already linked to a WooCommerce order.';
        }

        if (! in_array($import->order_import_status, ['not_imported', 'failed', 'needs_retry', 'blocked'], true)) {
            $errors[] = 'Only not imported or failed Front sale imports can be imported as Woo orders.';
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
            $errors[] = 'All Front sale lines must match synced WooCommerce products before import.';
        }

        if (empty($import->woo_order_payload_json['line_items'] ?? [])) {
            $errors[] = 'WooCommerce order payload has no matched line items.';
        }

        return array_values(array_unique($errors));
    }

    private function wooConnection(FrontSaleImport $import): ?Connection
    {
        return $import->organization
            ?->connections
            ->first(fn (Connection $connection): bool => $connection->type === 'woocommerce');
    }

    private function markImported(FrontSaleImport $import, Response $response): void
    {
        DB::transaction(function () use ($import, $response): void {
            $summary = $this->responseSummary($response);
            $mapping = OrderMapping::query()->updateOrCreate(
                [
                    'organization_id' => $import->organization_id,
                    'idempotency_key' => $import->idempotency_key,
                ],
                [
                    'woo_order_id' => $summary['id'] ?? null,
                    'front_order_id' => $import->front_sale_id,
                    'front_receipt_id' => $import->front_receipt_id,
                    'source' => 'front_pos',
                    'status' => 'imported',
                ],
            );

            $import->update([
                'order_mapping_id' => $mapping->id,
                'order_import_status' => 'imported',
                'imported_at' => now(),
                'last_response_summary_json' => $summary,
                'error_message' => null,
            ]);
        });
    }

    private function markFailed(FrontSaleImport $import, string $error, ?Response $response): void
    {
        $import->update([
            'order_import_status' => 'failed',
            'error_message' => $error,
            'last_response_summary_json' => $response ? $this->responseSummary($response) : ['error' => $error],
        ]);
    }

    private function requestSummary(FrontSaleImport $import): array
    {
        return [
            'endpoint' => 'POST /wp-json/wc/v3/orders',
            'method' => 'POST',
            'line_count' => count($import->woo_order_payload_json['line_items'] ?? []),
            'payment_method' => $import->woo_order_payload_json['payment_method'] ?? null,
            'set_paid' => $import->woo_order_payload_json['set_paid'] ?? null,
            'writes_woocommerce' => true,
            'writes_front' => false,
        ];
    }

    private function responseSummary(Response $response): array
    {
        $body = $response->json();

        return [
            'http_status' => $response->status(),
            'id' => is_array($body) ? ($body['id'] ?? null) : null,
            'status' => is_array($body) ? ($body['status'] ?? null) : null,
            'number' => is_array($body) ? ($body['number'] ?? null) : null,
        ];
    }

    private function audit(FrontSaleImport $import, User $user, array $metadata): void
    {
        AuditLog::query()->create([
            'organization_id' => $import->organization_id,
            'user_id' => $user->id,
            'action' => 'front_sale_import',
            'subject_type' => FrontSaleImport::class,
            'subject_id' => $import->id,
            'metadata_json' => array_merge([
                'front_sale_import_id' => $import->id,
                'front_sale_id' => $import->front_sale_id,
                'front_receipt_id' => $import->front_receipt_id,
            ], $metadata),
        ]);
    }
}
