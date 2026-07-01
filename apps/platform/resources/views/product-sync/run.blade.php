@extends('layouts.app')

@php
    $total = max((int) $run->total_candidates, 1);
    $readyWidth = (int) round(($run->total_ready / $total) * 100);
@endphp

@section('content')
    <section class="panel page-header">
        <span class="kicker">Preview run</span>
        <h1>Sync Run #{{ $run->id }}</h1>
        <p>
            <span class="badge">{{ $run->run_type }}</span>
            <span class="badge">{{ $run->status }}</span>
            <span class="badge">{{ $run->mode }}</span>
        </p>
        <div class="warning">Preview only. No product writes have been performed.</div>
        <div class="progress"><span style="width: {{ $readyWidth }}%"></span></div>
        <p class="muted">
            {{ $run->total_candidates }} candidates,
            {{ $run->total_ready }} ready,
            {{ $run->total_blocked }} needs attention,
            {{ $run->total_pending }} waiting,
            {{ $run->total_failed }} failed,
            {{ $run->total_variations }} variations.
        </p>
        <p class="muted">
            Scope: {{ $run->scope ?: 'not set' }} |
            Started: {{ $run->started_at ?: 'not started' }} |
            Finished: {{ $run->finished_at ?: 'not finished' }}
        </p>
        @if ($run->checkpoint_json)
            <p class="muted">Checkpoint: {{ $run->checkpoint_json['next_action'] ?? 'manual review' }}</p>
        @endif
    </section>

    <section class="panel">
        <h2>Staging batch controls</h2>
        <p class="muted">
            This processes up to 100 ready or warning items in this run. It writes only products to Front.
            It does not write WooCommerce, price lists, stock, orders, refunds, or gift cards.
        </p>
        <div class="action-row">
            <form class="inline-form" method="post" action="{{ route('product-sync.runs.staging-batch-sync', $run) }}">
                @csrf
                <button type="submit">Run staging batch sync</button>
            </form>
            <form class="inline-form" method="post" action="{{ route('product-sync.runs.retry-failed', $run) }}">
                @csrf
                <button class="secondary" type="submit">Retry failed items</button>
            </form>
        </div>
    </section>

    <section class="panel">
        <h2>Next step: Front write dry-run</h2>
        <p class="muted">
            Select up to 10 ready or warning items to preview the exact Front product payload.
            This does not call Front and does not write anything.
        </p>
        <div class="warning">
            The limited write test button will call Front <code>POST /api/products</code> for the selected items only.
            It does not write WooCommerce, prices, stock, orders, or the full catalog.
        </div>
        @if ($run->profile?->mode !== 'limited_write_test')
            <div class="warning">Set the product sync profile mode to Limited write test before preparing a Front write dry-run.</div>
        @endif
        @if ($productionWritesEnabled)
            <div class="danger">Production writes are enabled. This dry-run milestone requires production writes to remain disabled.</div>
        @endif
        <form method="post" action="{{ route('product-sync.runs.front-dry-run.prepare', $run) }}">
            @csrf
            <div class="summary-list">
                @forelse ($eligibleDryRunItems as $dryRunItem)
                    <label class="summary-item" style="font-weight: 500">
                        <span>
                            <input type="checkbox" name="item_ids[]" value="{{ $dryRunItem->id }}">
                            <strong>{{ $dryRunItem->woo_name ?: $dryRunItem->woo_item_key }}</strong>
                            <span class="muted">{{ $dryRunItem->woo_item_key }} | {{ $dryRunItem->woo_sku ?: 'no SKU' }} | {{ $dryRunItem->validation_status }}</span>
                        </span>
                        <span class="badge {{ $dryRunItem->validation_status === 'ready' ? 'ready' : 'warning-badge' }}">{{ $dryRunItem->validation_status }}</span>
                    </label>
                @empty
                    <p>No ready or warning items are available for dry-run.</p>
                @endforelse
            </div>
            <p>
                <button type="submit" @disabled($eligibleDryRunItems->isEmpty())>Prepare Front dry-run</button>
                <button
                    class="secondary"
                    type="submit"
                    formaction="{{ route('product-sync.runs.limited-front-write-test', $run) }}"
                    @disabled($eligibleDryRunItems->isEmpty())
                >Run limited Front write test</button>
            </p>
        </form>
    </section>

    <section class="panel">
        <h2>Products</h2>
        <form method="get" action="{{ route('product-sync.runs.show', $run) }}">
            <div class="grid">
                <div>
                    <label for="sync_status">Sync status</label>
                    <select id="sync_status" name="sync_status">
                        <option value="">All</option>
                        @foreach (['not_started', 'queued', 'running', 'skipped', 'synced', 'failed', 'needs_retry'] as $status)
                            <option value="{{ $status }}" @selected(request('sync_status') === $status)>{{ str_replace('_', ' ', $status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="validation_status">Validation</label>
                    <select id="validation_status" name="validation_status">
                        <option value="">All</option>
                        @foreach (['ready', 'warning', 'blocked'] as $status)
                            <option value="{{ $status }}" @selected(request('validation_status') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="q">Search Woo ID, SKU, GTIN, or Front ID</label>
                    <input id="q" name="q" value="{{ request('q') }}">
                </div>
            </div>
            <button type="submit">Filter</button>
            <a class="button secondary" href="{{ route('product-sync.runs.show', $run) }}">Clear</a>
        </form>

        <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Woo product</th>
                <th>Type</th>
                <th>SKU</th>
                <th>GTIN/EAN</th>
                <th>Front match</th>
                <th>Validation</th>
                <th>Sync status</th>
                <th>Front result</th>
                <th>Needs attention</th>
                <th>Proposed Front fields</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>{{ $item->woo_name ?: 'n/a' }}<div class="muted">{{ $item->woo_item_key }}</div></td>
                    <td>{{ $item->woo_type ?: 'n/a' }}</td>
                    <td>{{ $item->woo_sku ?: 'n/a' }}</td>
                    <td>{{ $item->detected_gtin ?: 'n/a' }}<div class="muted">{{ $item->detected_gtin_key ?: 'no key' }}</div></td>
                    <td>{{ $item->front_match_status ?: 'no match' }}</td>
                    <td><span class="badge {{ $item->validation_status === 'ready' ? 'ready' : ($item->validation_status === 'blocked' ? 'blocked' : 'warning-badge') }}">{{ $item->validation_status }}</span></td>
                    <td>{{ $item->sync_status }}<div class="muted">Attempts: {{ $item->attempt_count }}</div></td>
                    <td>
                        <div>Product ID: {{ $item->front_product_id ?: 'n/a' }}</div>
                        <div>Ext ID: {{ $item->front_product_ext_id ?: 'n/a' }}</div>
                        <div>External SKU: {{ $item->front_external_sku ?: 'n/a' }}</div>
                        @if ($item->last_request_summary_json)
                            <div class="muted">{{ $item->last_request_summary_json['endpoint'] ?? 'n/a' }}</div>
                        @endif
                    </td>
                    <td>
                        @forelse (($item->validation_errors_json ?? []) as $error)
                            <div class="danger">{{ $error }}</div>
                        @empty
                            @foreach (($item->validation_warnings_json ?? []) as $warning)
                                <div class="warning">{{ $warning }}</div>
                            @endforeach
                        @endforelse
                    </td>
                    <td>
                        @php($payload = $item->proposed_front_payload_json ?? [])
                        @php($size = $payload['productSizes'][0] ?? [])
                        <div>Name: {{ $payload['name'] ?? 'n/a' }}</div>
                        <div>Number: {{ $payload['number'] ?? 'n/a' }}</div>
                        <div>External SKU: {{ $size['externalSKU'] ?? 'n/a' }}</div>
                        <div>Group: {{ $payload['groupName'] ?? 'n/a' }}</div>
                        <div>Price: {{ $payload['price_candidate'] ?? 'n/a' }}</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">No run items match this view.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
        </div>
        <div style="margin-top: 16px">
            {{ $items->links() }}
        </div>
    </section>
@endsection
