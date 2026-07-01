@extends('layouts.app')

@php
    $stockDone = $imports->getCollection()->where('stock_status', 'adjusted')->count();
    $attention = $imports->getCollection()->whereIn('stock_status', ['blocked', 'failed'])->count();
    $waiting = $imports->getCollection()->whereIn('stock_status', ['pending', 'running'])->count();
@endphp

@section('content')
    <section class="panel page-header">
        <span class="kicker">Front to WooCommerce</span>
        <h1>Front Sales and Returns</h1>
        <p>Front POS sales reduce WooCommerce stock. Front returns increase WooCommerce stock. Woo orders are optional and only created when an admin clicks the manual button for a sale.</p>
        <div class="notice">Default mode is stock-only. This keeps the normal WooCommerce order list clean while keeping Woo stock correct.</div>
        @if ($productionWritesEnabled)
            <div class="danger">Production writes are enabled. Do not import sales until the production launch checklist is complete.</div>
        @endif
    </section>

    <section class="status-board">
        <div class="status-card ready">
            <span class="muted">Imported</span>
            <strong>{{ $stockDone }}</strong>
            <span>Stock adjusted in Woo</span>
        </div>
        <div class="status-card warning">
            <span class="muted">Waiting</span>
            <strong>{{ $waiting }}</strong>
            <span>Ready or running</span>
        </div>
        <div class="status-card {{ $attention > 0 ? 'blocked' : 'ready' }}">
            <span class="muted">Needs attention</span>
            <strong>{{ $attention }}</strong>
            <span>Usually missing product mapping</span>
        </div>
    </section>

    <section class="panel">
        <h2>How this works</h2>
        <div class="owner-flow">
            <div class="flow-step">
                <span class="step-number">1</span>
                <strong>Front transaction arrives</strong>
                <p class="muted">A Front sale or return webhook is captured as a stock transaction.</p>
            </div>
            <div class="flow-step">
                <span class="step-number">2</span>
                <strong>Products are matched</strong>
                <p class="muted">Each sale line must match a synced Woo product or variation.</p>
            </div>
            <div class="flow-step">
                <span class="step-number">3</span>
                <strong>Woo stock is adjusted</strong>
                <p class="muted">Sales reduce stock. Returns add stock back. A Woo order is optional and manual for sales only.</p>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="split-row">
            <div>
                <h2>Captured POS transactions</h2>
                <p class="muted">If a transaction is blocked, open it to see which product line needs a mapping.</p>
            </div>
            <a class="button secondary" href="{{ route('testing-log.index') }}">Open Testing Log</a>
        </div>
        <div class="table-wrap">
            <table class="simple-table">
                <thead>
                <tr>
                    <th>Stock</th>
                    <th>Front transaction</th>
                    <th>Type</th>
                    <th>Total</th>
                    <th>Lines</th>
                    <th>Optional order</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($imports as $import)
                    @php($unmatched = collect($import->line_items_json ?? [])->where('mapping_status', 'missing_product_mapping')->count())
                    <tr>
                        <td>
                            <span class="badge {{ $import->stock_status === 'adjusted' ? 'ready' : (in_array($import->stock_status, ['blocked', 'failed'], true) ? 'blocked' : 'warning-badge') }}">
                                {{ $import->stock_status === 'adjusted' ? 'Stock adjusted' : (in_array($import->stock_status, ['blocked', 'failed'], true) ? 'Needs attention' : ucfirst($import->stock_status)) }}
                            </span>
                            @if ($import->stock_error_message)
                                <div class="muted">{{ $import->stock_error_message }}</div>
                            @endif
                        </td>
                        <td>
                            <strong>{{ $import->front_receipt_id ?: $import->front_sale_id ?: 'Unknown sale' }}</strong>
                            <div class="muted">{{ $import->created_at->diffForHumans() }}</div>
                        </td>
                        <td>{{ $import->transaction_type === 'return' ? 'Return' : 'Sale' }}</td>
                        <td>{{ $import->currency }} {{ $import->total_amount ?: 'n/a' }}</td>
                        <td>
                            {{ count($import->line_items_json ?? []) }} line(s)
                            @if ($unmatched > 0)
                                <div class="muted">{{ $unmatched }} unmatched</div>
                            @endif
                        </td>
                        <td>{{ $import->orderMapping?->woo_order_id ? 'Woo order '.$import->orderMapping->woo_order_id : ucfirst(str_replace('_', ' ', $import->order_import_status ?? 'not_imported')) }}</td>
                        <td><a class="button secondary" href="{{ route('front-sales.show', $import) }}">Open</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">No Front sales or returns captured yet. Send a Front webhook to the Front webhook URL when Front is ready.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $imports->links() }}
    </section>
@endsection
