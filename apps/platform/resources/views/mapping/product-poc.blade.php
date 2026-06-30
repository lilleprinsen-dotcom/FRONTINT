@extends('layouts.app')

@php
    $planRows = $latestPlan?->plan_json['rows'] ?? [];
@endphp

@section('content')
    <section class="panel">
        <h1>Mapping Preview Lab</h1>
        <div class="warning">Preview only. No products, prices, stock or orders are written to WooCommerce or Front.</div>

        @if ($productionWritesEnabled)
            <div class="danger">Production writes are enabled. Do not continue with mapping PoC work until <code>OMNIBRIDGE_ALLOW_PRODUCTION_WRITES=false</code>.</div>
        @else
            <div class="notice">Production writes are disabled. This page uses stored discovery snapshots only.</div>
        @endif

        <p class="muted">
            Run WooCommerce product discovery first. Front product discovery is optional for match preview and can wait until Front is ready.
            This page does not call external APIs, does not create final mappings,
            and does not use Front write endpoints such as <code>/api/products</code>, <code>/api/PricelistV2</code>, or <code>/api/Stock/adjust</code>.
        </p>
    </section>

    <section class="panel">
        <h2>Prerequisites</h2>
        <table>
            <tbody>
            <tr>
                <th>WooCommerce product discovery snapshot</th>
                <td>
                    @if ($wooSnapshot)
                        Present, checked {{ $wooSnapshot->checked_at }}. Products: {{ count($wooProducts) }}.
                    @else
                        Missing. Run WooCommerce product discovery first.
                    @endif
                </td>
            </tr>
            <tr>
                <th>Front product discovery snapshot</th>
                <td>
                    @if ($frontSnapshot)
                        Present, checked {{ $frontSnapshot->checked_at }}. Products: {{ count($frontProducts) }}.
                    @else
                        Missing. You can still create a Woo-only readiness plan; Front matching will show as missing.
                    @endif
                </td>
            </tr>
            <tr>
                <th>Production writes</th>
                <td>{{ $productionWritesEnabled ? 'Enabled - stop and disable before continuing.' : 'Disabled.' }}</td>
            </tr>
            <tr>
                <th>Live HTTP</th>
                <td>Can be off. This page only reads stored local snapshots.</td>
            </tr>
            </tbody>
        </table>
    </section>

    <form method="post" action="{{ route('mapping.product-poc.plan') }}">
        @csrf
        <section class="panel">
            <h2>Select Products and Variations</h2>
            <p class="muted">
                Select up to 10 WooCommerce products or variations from the latest read-only discovery sample.
                Variation rows are sellable candidates for variable products.
                Front product discovery is not required for Woo readiness, but it is needed before this page can detect existing Front matches.
                Detected GTIN/EAN values are candidates only and must be confirmed before any future write test.
            </p>

            <table>
                <thead>
                <tr>
                    <th>Select</th>
                    <th>Woo item</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>SKU</th>
                    <th>Detected GTIN/EAN</th>
                    <th>GTIN key</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Validation</th>
                    <th>Front match</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($previewRows as $row)
                    @php($product = $row['woo_product'])
                    @php($gtin = $row['gtin_candidate'] ?? [])
                    @php($payload = $row['proposed_front_payload'] ?? [])
                    <tr>
                        <td>
                            <input type="checkbox" name="woo_item_keys[]" value="{{ $product['item_key'] }}" @disabled(!$wooSnapshot || $productionWritesEnabled)>
                        </td>
                        <td>
                            {{ $product['item_key'] ?? 'n/a' }}
                            @if (($product['parent_product_id'] ?? null) !== null)
                                <div class="muted">Parent: {{ $product['parent_product_id'] }}</div>
                            @endif
                        </td>
                        <td>{{ $product['name'] ?? 'n/a' }}</td>
                        <td>{{ $product['type'] ?? 'n/a' }}</td>
                        <td>{{ $product['sku'] ?? 'n/a' }}</td>
                        <td>{{ $gtin['value'] ?? 'None' }}</td>
                        <td>
                            {{ $gtin['key'] ?? 'None' }}
                            <div class="muted">{{ $gtin['confidence'] ?? 'none' }}</div>
                            @if (($gtin['candidates'] ?? []) !== [] && count($gtin['candidates']) > 1)
                                <div class="warning">Multiple candidates found.</div>
                            @endif
                        </td>
                        <td>{{ $payload['price_candidate'] ?? 'n/a' }}</td>
                        <td>{{ $product['stock_status'] ?? 'n/a' }}</td>
                        <td>
                            <strong>{{ $row['status'] }}</strong>
                            @foreach ($row['blocks'] as $block)
                                <div class="danger">{{ $block }}</div>
                            @endforeach
                            @foreach ($row['warnings'] as $warning)
                                <div class="warning">{{ $warning }}</div>
                            @endforeach
                        </td>
                        <td>
                            {{ $row['front_match']['status'] ?? 'no_match' }}
                            @if (($row['front_match']['status'] ?? null) === 'front_sample_missing')
                                <div class="muted">Run Front product discovery later to check for existing matches.</div>
                            @endif
                            @if (in_array(($row['front_match']['status'] ?? 'no_match'), ['matched_existing_front_product', 'possible_duplicate'], true))
                                <div class="muted">
                                    {{ $row['front_match']['name'] ?? 'n/a' }} |
                                    GTIN: {{ $row['front_match']['gtin'] ?? 'n/a' }} |
                                    Identity: {{ $row['front_match']['identity'] ?? 'n/a' }} |
                                    External SKU: {{ $row['front_match']['external_sku'] ?? 'n/a' }}
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11">Run WooCommerce product discovery before selecting products or variations.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>

            <p>
                <button type="submit" @disabled(!$wooSnapshot || $productionWritesEnabled)>Generate 10-product sync plan</button>
            </p>
        </section>
    </form>

    <section class="panel">
        <h2>Generated Plan</h2>
        @if ($latestPlan)
            <p class="muted">
                Latest plan: <strong>{{ $latestPlan->status }}</strong>,
                {{ $latestPlan->selected_count }} item(s),
                created {{ $latestPlan->created_at }}.
                This is preview storage only, not sync history.
            </p>
            <table>
                <thead>
                <tr>
                    <th>Woo item</th>
                    <th>Proposed Front name</th>
                    <th>Number</th>
                    <th>Variant</th>
                    <th>GTIN</th>
                    <th>External SKU</th>
                    <th>Brand</th>
                    <th>Group/subgroup</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Warnings</th>
                    <th>NEEDS_CONFIRMATION</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($planRows as $row)
                    @php($payload = $row['proposed_front_payload'])
                    @php($size = $payload['productSizes'][0] ?? [])
                    <tr>
                        <td>
                            {{ $row['woo_product']['name'] ?? 'n/a' }}
                            <div class="muted">
                                {{ $row['woo_product']['item_key'] ?? 'n/a' }}
                                @if (($row['woo_product']['parent_product_id'] ?? null) !== null)
                                    / Parent: {{ $row['woo_product']['parent_product_id'] }}
                                @endif
                            </div>
                        </td>
                        <td>{{ $payload['name'] ?? 'n/a' }}</td>
                        <td>{{ $payload['number'] ?? 'n/a' }}</td>
                        <td>{{ $payload['variant'] ?? 'n/a' }}</td>
                        <td>{{ $size['gtin'] ?? 'n/a' }}</td>
                        <td>{{ $size['externalSKU'] ?? 'n/a' }}</td>
                        <td>{{ $payload['brand'] ?? 'n/a' }}</td>
                        <td>{{ $payload['groupName'] ?? 'n/a' }} / {{ $payload['subgroupName'] ?? 'n/a' }}</td>
                        <td>{{ $payload['price_candidate'] ?? 'n/a' }}</td>
                        <td>
                            <strong>{{ $row['status'] }}</strong>
                            @foreach ($row['blocks'] as $block)
                                <div class="danger">{{ $block }}</div>
                            @endforeach
                        </td>
                        <td>
                            @forelse ($row['warnings'] as $warning)
                                <div>{{ $warning }}</div>
                            @empty
                                None
                            @endforelse
                        </td>
                        <td>
                            @foreach ($row['needs_confirmation'] as $item)
                                <div>{{ $item }}</div>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p>No preview sync plan generated yet.</p>
        @endif
    </section>
@endsection
