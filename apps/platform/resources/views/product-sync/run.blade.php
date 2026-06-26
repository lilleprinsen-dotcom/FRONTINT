@extends('layouts.app')

@php
    $total = max((int) $run->total_candidates, 1);
    $readyWidth = (int) round(($run->total_ready / $total) * 100);
@endphp

@section('content')
    <section class="panel">
        <h1>Preview Run #{{ $run->id }}</h1>
        <p><span class="badge">{{ $run->status }}</span> <span class="badge">{{ $run->mode }}</span></p>
        <div class="warning">Preview only. No product writes have been performed.</div>
        <div class="progress"><span style="width: {{ $readyWidth }}%"></span></div>
        <p class="muted">{{ $run->total_ready }} ready, {{ $run->total_blocked }} needs attention, {{ $run->total_failed }} failed.</p>
    </section>

    <section class="panel">
        <h2>Products</h2>
        <table>
            <thead>
            <tr>
                <th>Woo product</th>
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
            @forelse ($run->items as $item)
                <tr>
                    <td>{{ $item->woo_name ?: 'n/a' }}<div class="muted">{{ $item->woo_item_key }}</div></td>
                    <td>{{ $item->woo_sku ?: 'n/a' }}</td>
                    <td>{{ $item->detected_gtin ?: 'n/a' }}<div class="muted">{{ $item->detected_gtin_key ?: 'no key' }}</div></td>
                    <td>{{ $item->front_match_status ?: 'no match' }}</td>
                    <td><span class="badge {{ $item->validation_status === 'ready' ? 'ready' : ($item->validation_status === 'blocked' ? 'blocked' : 'warning-badge') }}">{{ $item->validation_status }}</span></td>
                    <td>{{ $item->sync_status }}</td>
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
                    <td colspan="8">No run items yet.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
