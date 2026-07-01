@extends('layouts.app')

@php
    $lines = collect($import->line_items_json ?? []);
    $matched = $lines->where('mapping_status', 'matched')->count();
    $unmatched = $lines->where('mapping_status', 'missing_product_mapping')->count();
    $isReturn = $import->transaction_type === 'return';
@endphp

@section('content')
    <section class="panel page-header">
        <span class="kicker">{{ $isReturn ? 'Front return' : 'Front sale' }}</span>
        <h1>{{ $import->front_receipt_id ?: $import->front_sale_id ?: ($isReturn ? 'Front return' : 'Front sale') }}</h1>
        <p>This page shows how a Front POS {{ $isReturn ? 'return adds stock back to' : 'sale reduces stock in' }} WooCommerce.</p>
        <div class="notice">Default behavior is stock-only. Woo orders are only created manually for sales, never automatically.</div>
        @if ($productionWritesEnabled)
            <div class="danger">Production writes are enabled. Do not import sales until the production launch checklist is complete.</div>
        @endif
    </section>

    <section class="status-board">
        <div class="status-card {{ $import->stock_status === 'adjusted' ? 'ready' : (in_array($import->stock_status, ['blocked', 'failed'], true) ? 'blocked' : 'warning') }}">
            <span class="muted">Woo stock</span>
            <strong>{{ ucfirst(str_replace('_', ' ', $import->stock_status)) }}</strong>
            <span>{{ $import->stock_error_message ?: 'No stock error saved' }}</span>
        </div>
        <div class="status-card ready">
            <span class="muted">Matched lines</span>
            <strong>{{ $matched }}</strong>
            <span>{{ $isReturn ? 'Ready for stock return' : 'Ready for stock/order action' }}</span>
        </div>
        <div class="status-card {{ $unmatched > 0 ? 'blocked' : 'ready' }}">
            <span class="muted">Unmatched lines</span>
            <strong>{{ $unmatched }}</strong>
            <span>{{ $unmatched > 0 ? 'Map products first' : 'All lines matched' }}</span>
        </div>
        <div class="status-card {{ $import->order_import_status === 'imported' ? 'ready' : 'warning' }}">
            <span class="muted">Optional Woo order</span>
            <strong>{{ $isReturn ? 'Not available' : ucfirst(str_replace('_', ' ', $import->order_import_status)) }}</strong>
            <span>{{ $isReturn ? 'Returns only adjust stock' : ($import->orderMapping?->woo_order_id ? 'Woo order '.$import->orderMapping->woo_order_id : 'Not created unless admin chooses it') }}</span>
        </div>
    </section>

    <section class="panel">
        <div class="split-row">
            <div>
                <h2>Stock action</h2>
                <p class="muted">{{ $isReturn ? 'This increases WooCommerce stock by the quantities returned in Front.' : 'This reduces WooCommerce stock by the quantities sold in Front.' }} It does not create a Woo order.</p>
            </div>
            @if (in_array($import->stock_status, ['pending', 'failed', 'blocked'], true))
                <form method="post" action="{{ route('front-sales.adjust-stock', $import) }}">
                    @csrf
                    <button type="submit">{{ $isReturn ? 'Add returned stock to Woo' : 'Adjust Woo stock' }}</button>
                </form>
            @else
                <button class="secondary" type="button" disabled>Stock already handled</button>
            @endif
        </div>
    </section>

    <section class="panel">
        @if ($isReturn)
            <div>
                <h2>Woo order</h2>
                <p class="muted">Returns are not imported as WooCommerce orders in this flow. They only add stock back after product lines are matched.</p>
            </div>
        @else
            <div class="split-row">
                <div>
                    <h2>Optional Woo order</h2>
                    <p class="muted">Use this only if you want the POS sale as a WooCommerce order. The order is marked so stock should not be reduced twice.</p>
                </div>
                @if (in_array($import->order_import_status, ['not_imported', 'failed', 'needs_retry', 'blocked'], true))
                    <form method="post" action="{{ route('front-sales.import', $import) }}">
                        @csrf
                        <button class="secondary" type="submit">Create Woo order manually</button>
                    </form>
                @else
                    <button class="secondary" type="button" disabled>Woo order already created</button>
                @endif
            </div>
        @endif
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
        @if ($isReturn)
            <p class="muted">Returns do not create WooCommerce orders in OmniBridge. They are stock transactions only.</p>
        @else
            <p class="muted">This is a safe summary, not the full API body.</p>
            <div class="summary-list">
                <div class="summary-item"><span>Status</span><strong>{{ $import->woo_order_payload_json['status'] ?? 'n/a' }}</strong></div>
                <div class="summary-item"><span>Paid</span><strong>{{ data_get($import->woo_order_payload_json, 'set_paid') ? 'Yes' : 'No' }}</strong></div>
                <div class="summary-item"><span>Payment method</span><strong>{{ $import->woo_order_payload_json['payment_method_title'] ?? 'n/a' }}</strong></div>
                <div class="summary-item"><span>Woo line count</span><strong>{{ count($import->woo_order_payload_json['line_items'] ?? []) }}</strong></div>
                <div class="summary-item"><span>Stock already adjusted marker</span><strong>Yes</strong></div>
            </div>
            <details class="technical-details">
                <summary>Technical summary</summary>
                <div>Import ID: {{ $import->id }}</div>
                <div>Idempotency key: {{ $import->idempotency_key }}</div>
                <div>Attempts: {{ $import->attempt_count }}</div>
                <div>Woo order ID: {{ $import->orderMapping?->woo_order_id ?: 'none' }}</div>
            </details>
        @endif
    </section>
@endsection
