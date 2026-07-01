@extends('layouts.app')

@php
    $worked = $imports->getCollection()->where('status', 'imported')->count();
    $attention = $imports->getCollection()->whereIn('status', ['blocked', 'failed'])->count();
    $waiting = $imports->getCollection()->whereIn('status', ['pending', 'running'])->count();
@endphp

@section('content')
    <section class="panel page-header">
        <span class="kicker">Front to WooCommerce</span>
        <h1>Front Sales</h1>
        <p>Import paid Front POS sales into WooCommerce so the customer history and Woo stock stay updated.</p>
        <div class="notice">This staging flow only writes WooCommerce orders when you start an import. It does not write Front, refunds, gift cards, or omnichannel records.</div>
        @if ($productionWritesEnabled)
            <div class="danger">Production writes are enabled. Do not import sales until the production launch checklist is complete.</div>
        @endif
    </section>

    <section class="status-board">
        <div class="status-card ready">
            <span class="muted">Imported</span>
            <strong>{{ $worked }}</strong>
            <span>Woo orders created</span>
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
                <strong>Front sale arrives</strong>
                <p class="muted">A Front webhook or event is captured as a staged sale import.</p>
            </div>
            <div class="flow-step">
                <span class="step-number">2</span>
                <strong>Products are matched</strong>
                <p class="muted">Each sale line must match a synced Woo product or variation.</p>
            </div>
            <div class="flow-step">
                <span class="step-number">3</span>
                <strong>Woo order is created</strong>
                <p class="muted">You start the import. WooCommerce creates a paid Front POS order.</p>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="split-row">
            <div>
                <h2>Sale imports</h2>
                <p class="muted">If a sale is blocked, open it to see which product line needs a mapping.</p>
            </div>
            <a class="button secondary" href="{{ route('testing-log.index') }}">Open Testing Log</a>
        </div>
        <div class="table-wrap">
            <table class="simple-table">
                <thead>
                <tr>
                    <th>Status</th>
                    <th>Front sale</th>
                    <th>Total</th>
                    <th>Lines</th>
                    <th>Woo order</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($imports as $import)
                    @php($unmatched = collect($import->line_items_json ?? [])->where('mapping_status', 'missing_product_mapping')->count())
                    <tr>
                        <td>
                            <span class="badge {{ $import->status === 'imported' ? 'ready' : (in_array($import->status, ['blocked', 'failed'], true) ? 'blocked' : 'warning-badge') }}">
                                {{ $import->status === 'imported' ? 'Imported' : (in_array($import->status, ['blocked', 'failed'], true) ? 'Needs attention' : ucfirst($import->status)) }}
                            </span>
                            @if ($import->error_message)
                                <div class="muted">{{ $import->error_message }}</div>
                            @endif
                        </td>
                        <td>
                            <strong>{{ $import->front_receipt_id ?: $import->front_sale_id ?: 'Unknown sale' }}</strong>
                            <div class="muted">{{ $import->created_at->diffForHumans() }}</div>
                        </td>
                        <td>{{ $import->currency }} {{ $import->total_amount ?: 'n/a' }}</td>
                        <td>
                            {{ count($import->line_items_json ?? []) }} line(s)
                            @if ($unmatched > 0)
                                <div class="muted">{{ $unmatched }} unmatched</div>
                            @endif
                        </td>
                        <td>{{ $import->orderMapping?->woo_order_id ?: 'Not created yet' }}</td>
                        <td><a class="button secondary" href="{{ route('front-sales.show', $import) }}">Open</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No Front sales captured yet. Send a Front sale webhook to the Front webhook URL when Front is ready.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $imports->links() }}
    </section>
@endsection
