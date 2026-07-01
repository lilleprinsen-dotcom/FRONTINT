<?php

namespace App\Services\Sales;

use App\Models\Event;
use App\Models\FrontSaleImport;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class FrontSaleImportRecorder
{
    public function __construct(private readonly FrontSalePayloadMapper $mapper)
    {
    }

    public function recordFromEvent(Event $event): ?FrontSaleImport
    {
        if ($event->source_system !== 'front' || ! $this->mapper->looksLikeSale($event->payload_json ?? [], $event->event_type)) {
            return null;
        }

        $event->loadMissing('organization');

        return $this->record($event->organization, $event->payload_json ?? [], $event);
    }

    public function record(Organization $organization, array $payload, ?Event $event = null): FrontSaleImport
    {
        $data = $this->mapper->buildImportData($organization, $payload);
        $unmatched = collect($data['line_items_json'])->where('mapping_status', 'missing_product_mapping')->count();
        $lineCount = count($data['line_items_json']);

        return DB::transaction(function () use ($organization, $event, $data, $unmatched, $lineCount): FrontSaleImport {
            return FrontSaleImport::query()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'idempotency_key' => $data['idempotency_key'],
                ],
                [
                    'event_id' => $event?->id,
                    'status' => $lineCount === 0 || $unmatched > 0 ? 'blocked' : 'pending',
                    'front_sale_id' => $data['front_sale_id'],
                    'front_receipt_id' => $data['front_receipt_id'],
                    'sale_time' => $data['sale_time'],
                    'currency' => $data['currency'],
                    'total_amount' => $data['total_amount'],
                    'payload_summary_json' => $data['payload_summary_json'],
                    'line_items_json' => $data['line_items_json'],
                    'woo_order_payload_json' => $data['woo_order_payload_json'],
                    'error_message' => $lineCount === 0
                        ? 'Front sale payload did not contain sale lines.'
                        : ($unmatched > 0 ? "{$unmatched} sale line(s) could not be matched to synced Woo products." : null),
                ],
            );
        });
    }
}
