@extends('layouts.app')

@section('content')
    <section class="panel page-header">
        <span class="kicker">Limited write test preparation</span>
        <h1>Front Product Dry-Run</h1>
        <p>
            This page shows exactly what would be prepared for a future Front product write.
            It uses local preview-run data only.
        </p>
        <div class="notice">No Front API calls are made. No WooCommerce or Front data is changed.</div>
    </section>

    <section class="panel">
        <h2>Safety gates</h2>
        <div class="summary-list">
            <div class="summary-item"><span>Profile mode</span><strong>{{ $dryRun['summary']['profile_mode'] ?: 'not set' }}</strong></div>
            <div class="summary-item"><span>Production writes</span><strong>{{ $productionWritesEnabled ? 'Enabled - blocked' : 'Disabled' }}</strong></div>
            <div class="summary-item"><span>Front connection</span><strong>{{ $dryRun['summary']['front_connection_name'] ?: 'Missing' }}</strong></div>
            <div class="summary-item"><span>Selected items</span><strong>{{ $dryRun['summary']['selected_count'] }} / {{ $dryRun['summary']['max_items'] }}</strong></div>
            <div class="summary-item"><span>External API calls</span><strong>None</strong></div>
            <div class="summary-item"><span>Writes performed</span><strong>None</strong></div>
        </div>

        @if ($dryRun['status'] === 'blocked')
            <div class="danger">
                <strong>Dry-run is blocked.</strong>
                <ul>
                    @foreach ($dryRun['gate_errors'] as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @else
            <div class="notice">Dry-run is ready for review. This still does not perform a write.</div>
        @endif

        <p><a class="button secondary" href="{{ route('product-sync.runs.show', $run) }}">Back to run</a></p>
    </section>

    <section class="panel">
        <h2>Payload preview</h2>
        <p class="muted">Sale price is shown as a future PriceListV2 candidate and is not mixed into the base product payload.</p>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Woo item</th>
                    <th>Decision</th>
                    <th>Name</th>
                    <th>Number</th>
                    <th>Variant</th>
                    <th>Size label</th>
                    <th>GTIN/EAN</th>
                    <th>External SKU</th>
                    <th>Brand</th>
                    <th>Group/subgroup</th>
                    <th>Image</th>
                    <th>Prices</th>
                    <th>Warnings</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($dryRun['rows'] as $row)
                    @php($payload = $row['front_product_payload'])
                    @php($size = $payload['productSizes'][0] ?? [])
                    <tr>
                        <td>
                            <strong>{{ $row['woo_name'] ?: 'n/a' }}</strong>
                            <div class="muted">{{ $row['woo_item_key'] }}</div>
                            <span class="badge {{ $row['validation_status'] === 'ready' ? 'ready' : 'warning-badge' }}">{{ $row['validation_status'] }}</span>
                        </td>
                        <td>
                            {{ str_replace('_', ' ', $row['write_decision']) }}
                            <div class="muted">{{ $row['future_endpoint_note'] }}</div>
                        </td>
                        <td>{{ $payload['name'] ?: 'n/a' }}</td>
                        <td>{{ $payload['number'] ?: 'n/a' }}</td>
                        <td>{{ $payload['variant'] ?: 'n/a' }}</td>
                        <td>{{ $size['label'] ?: 'n/a' }}</td>
                        <td>{{ $size['gtin'] ?: 'n/a' }}</td>
                        <td>{{ $size['externalSKU'] ?: 'n/a' }}</td>
                        <td>{{ $payload['brand'] ?: 'n/a' }}</td>
                        <td>{{ $payload['groupName'] ?: 'n/a' }} / {{ $payload['subgroupName'] ?: 'n/a' }}</td>
                        <td>
                            @if (($payload['image_candidate']['src'] ?? null) !== null)
                                <a href="{{ $payload['image_candidate']['src'] }}" target="_blank" rel="noreferrer">image</a>
                            @else
                                n/a
                            @endif
                        </td>
                        <td>
                            <div>Regular: {{ $row['price_candidates']['regular_price'] ?: 'n/a' }}</div>
                            <div>Sale: {{ $row['price_candidates']['sale_price'] ?: 'n/a' }}</div>
                            <div class="muted">{{ $row['price_candidates']['sale_price_destination'] }}</div>
                        </td>
                        <td>
                            @forelse ($row['warnings'] as $warning)
                                <div>{{ $warning }}</div>
                            @empty
                                None
                            @endforelse
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="13">No items selected.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
