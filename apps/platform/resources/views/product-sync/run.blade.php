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
                    <td colspan="9">No run items match this view.</td>
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
