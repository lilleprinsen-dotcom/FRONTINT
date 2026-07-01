@extends('layouts.app')

@php
    $lines = collect($import->line_items_json ?? []);
    $matched = $lines->where('mapping_status', 'matched')->count();
    $unmatched = $lines->where('mapping_status', 'missing_product_mapping')->count();
@endphp

@section('content')
    <section class="panel page-header">
        <span class="kicker">Front sale import</span>
        <h1>{{ $import->front_receipt_id ?: $import->front_sale_id ?: 'Front sale' }}</h1>
        <p>This page shows exactly how a Front sale will become a paid WooCommerce order.</p>
        <div class="notice">No Front data is changed. WooCommerce is only written when you press the import button.</div>
        @if ($productionWritesEnabled)
            <div class="danger">Production writes are enabled. Do not import sales until the production launch checklist is complete.</div>
        @endif
    </section>

    <section class="status-board">
        <div class="status-card {{ $import->status === 'imported' ? 'ready' : (in_array($import->status, ['blocked', 'failed'], true) ? 'blocked' : 'warning') }}">
            <span class="muted">Status</span>
            <strong>{{ ucfirst(str_replace('_', ' ', $import->status)) }}</strong>
            <span>{{ $import->error_message ?: 'No error saved' }}</span>
        </div>
        <div class="status-card ready">
            <span class="muted">Matched lines</span>
            <strong>{{ $matched }}</strong>
            <span>Ready for Woo order</span>
        </div>
        <div class="status-card {{ $unmatched > 0 ? 'blocked' : 'ready' }}">
            <span class="muted">Unmatched lines</span>
            <strong>{{ $unmatched }}</strong>
            <span>{{ $unmatched > 0 ? 'Map products first' : 'All lines matched' }}</span>
        </div>
    </section>

    <section class="panel">
        <div class="split-row">
            <div>
                <h2>Import action</h2>
                <p class="muted">Creates one paid WooCommerce order with payment method “Paid in Front POS”. The idempotency key prevents duplicate imports.</p>
            </div>
            @if (in_array($import->status, ['pending', 'failed', 'needs_retry'], true))
                <form method="post" action="{{ route('front-sales.import', $import) }}">
                    @csrf
                    <button type="submit">Import to WooCommerce</button>
                </form>
            @else
                <button class="secondary" type="button" disabled>Import unavailable</button>
            @endif
        </div>
    </section>

    <section class="panel">
        <h2>Sale lines</h2>
        <div class="table-wrap">
            <table class="simple-table">
                <thead>
                <tr>
                    <th>Status</th>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Identifiers</th>
                    <th>Woo target</th>
                    <th>Total</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($lines as $line)
                    <tr>
                        <td>
                            <span class="badge {{ ($line['mapping_status'] ?? null) === 'matched' ? 'ready' : 'blocked' }}">
                                {{ ($line['mapping_status'] ?? null) === 'matched' ? 'Matched' : 'Missing mapping' }}
                            </span>
                        </td>
                        <td>{{ $line['name'] ?: 'n/a' }}</td>
                        <td>{{ $line['quantity'] }}</td>
                        <td>
                            <div>SKU: {{ $line['sku'] ?: $line['external_sku'] ?: 'n/a' }}</div>
                            <div>GTIN: {{ $line['gtin'] ?: 'n/a' }}</div>
                            <div>Front ID: {{ $line['front_product_id'] ?: $line['identity'] ?: 'n/a' }}</div>
                        </td>
                        <td>{{ $line['woo_item_key'] ?: 'Not matched' }}</td>
                        <td>{{ $line['total'] ?? 'n/a' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No sale lines were found in this Front payload.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2>WooCommerce order preview</h2>
        <p class="muted">This is a safe summary, not the full API body.</p>
        <div class="summary-list">
            <div class="summary-item"><span>Status</span><strong>{{ $import->woo_order_payload_json['status'] ?? 'n/a' }}</strong></div>
            <div class="summary-item"><span>Paid</span><strong>{{ data_get($import->woo_order_payload_json, 'set_paid') ? 'Yes' : 'No' }}</strong></div>
            <div class="summary-item"><span>Payment method</span><strong>{{ $import->woo_order_payload_json['payment_method_title'] ?? 'n/a' }}</strong></div>
            <div class="summary-item"><span>Woo line count</span><strong>{{ count($import->woo_order_payload_json['line_items'] ?? []) }}</strong></div>
        </div>
        <details class="technical-details">
            <summary>Technical summary</summary>
            <div>Import ID: {{ $import->id }}</div>
            <div>Idempotency key: {{ $import->idempotency_key }}</div>
            <div>Attempts: {{ $import->attempt_count }}</div>
            <div>Woo order ID: {{ $import->orderMapping?->woo_order_id ?: 'none' }}</div>
        </details>
    </section>
@endsection
