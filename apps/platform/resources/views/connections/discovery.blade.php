@extends('layouts.app')

@php
    $storeRows = $latestStores?->sample_json['stores'] ?? [];
    $productRows = $latestProducts?->sample_json['products'] ?? [];
    $variationRows = $latestProducts?->sample_json['variations'] ?? [];
    $readiness = $latestProducts?->sample_json['readiness'] ?? [];
    $readinessRows = $readiness['rows'] ?? [];
    $readinessSummary = $readiness['summary'] ?? [];
@endphp

@section('content')
    <section class="panel">
        <h1>Discovery: {{ $connection->name }}</h1>
        <p class="muted">
            {{ $connection->organization->name }} |
            {{ $connection->type }} |
            {{ $connection->base_url ?: 'No base URL' }}
        </p>
        <p class="muted">Discovery is read-only and limited to small samples. It does not sync products, stock, prices, orders, refunds, gift cards, or omnichannel data.</p>
        <p class="muted">Only the latest 5 snapshots per connection and discovery type are kept. Discovery snapshots are not long-term product storage.</p>

        @if (in_array($connection->type, ['front', 'front_systems'], true))
            <div class="warning">
                Front product discovery uses <code>POST /api/Product</code> as the read-only product listing/search endpoint documented in the Front OpenAPI spec.
                It is capped at 10 products and must not be confused with <code>/api/products</code>, which is the product CRUD endpoint.
            </div>
        @endif

        @unless ($connectionHttpTestsEnabled)
            <div class="warning">Safe mode is on. Discovery actions will be skipped until <code>OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=true</code>.</div>
        @endunless

        <p>
            <a class="button secondary" href="{{ route('dashboard') }}">Back to dashboard</a>
            @if (in_array($connection->type, ['front', 'front_systems'], true))
                <form class="inline-form" method="post" action="{{ route('connections.discover.stores', $connection) }}">
                    @csrf
                    <button class="secondary" type="submit">Discover stores</button>
                </form>
            @endif
            @if (in_array($connection->type, ['woocommerce', 'front', 'front_systems'], true))
                <form class="inline-form" method="post" action="{{ route('connections.discover.products', $connection) }}">
                    @csrf
                    <button type="submit">Discover products</button>
                </form>
            @endif
        </p>
    </section>

    <section class="panel">
        <h2>Latest Status</h2>
        <table>
            <thead>
            <tr>
                <th>Type</th>
                <th>Status</th>
                <th>Checked</th>
                <th>Summary</th>
                <th>Error</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($snapshots as $snapshot)
                <tr>
                    <td>{{ $snapshot->discovery_type }}</td>
                    <td>{{ $snapshot->status }}</td>
                    <td>{{ $snapshot->checked_at }}</td>
                    <td>
                        @foreach (($snapshot->summary_json ?? []) as $key => $value)
                            <div>{{ $key }}: {{ is_scalar($value) ? $value : json_encode($value) }}</div>
                        @endforeach
                    </td>
                    <td>{{ $snapshot->error_message ?: 'None' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No discovery snapshots yet.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>

    @if (in_array($connection->type, ['front', 'front_systems'], true))
        <section class="panel">
            <h2>Front Stores</h2>
            <table>
                <thead>
                <tr>
                    <th>Store ID</th>
                    <th>Store no</th>
                    <th>Store name</th>
                    <th>Stock ID</th>
                    <th>External stock ID</th>
                    <th>Currency</th>
                    <th>Time zone</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($storeRows as $store)
                    <tr>
                        <td>{{ $store['store_id'] ?? 'n/a' }}</td>
                        <td>{{ $store['store_no'] ?? 'n/a' }}</td>
                        <td>{{ $store['store_name'] ?? 'n/a' }}</td>
                        <td>{{ $store['stock_id'] ?? 'n/a' }}</td>
                        <td>{{ $store['external_stock_id'] ?? 'n/a' }}</td>
                        <td>{{ $store['currency'] ?? 'n/a' }}</td>
                        <td>{{ $store['time_zone'] ?? 'n/a' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">No Front store discovery sample yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </section>
    @endif

    <section class="panel">
        <h2>Product Sample</h2>

        @if ($connection->type === 'woocommerce')
            <p class="muted">
                Detected GTIN/EAN values are candidates only and must be confirmed before final mapping.
                Lilleprinsen-relevant fields include <code>Zettle_barcode</code>, <code>iZettle_barcode</code>, <code>_Zettle_barcode</code>, and <code>_iZettle_barcode</code>.
            </p>
            <p class="muted">
                Variable product variations are fetched read-only from <code>GET /wp-json/wc/v3/products/{productId}/variations</code>.
                This sample is capped and is not a catalog scan.
            </p>
            <table>
                <thead>
                <tr>
                    <th>Woo ID</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>SKU</th>
                    <th>Stock</th>
                    <th>Regular price</th>
                    <th>Sale price</th>
                    <th>Candidate GTIN/EAN</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($productRows as $product)
                    <tr>
                        <td>{{ $product['id'] ?? 'n/a' }}</td>
                        <td>{{ $product['name'] ?? 'n/a' }}</td>
                        <td>{{ $product['type'] ?? 'n/a' }}</td>
                        <td>{{ $product['sku'] ?? 'n/a' }}</td>
                        <td>{{ $product['stock_status'] ?? 'n/a' }} / {{ $product['stock_quantity'] ?? 'n/a' }}</td>
                        <td>{{ $product['regular_price'] ?? 'n/a' }}</td>
                        <td>{{ $product['sale_price'] ?? 'n/a' }}</td>
                        <td>
                            @php($candidate = $product['gtin_candidate'] ?? [])
                            {{ $candidate['value'] ?? 'None' }}
                            <div class="muted">{{ $candidate['key'] ?? 'no key' }} / {{ $candidate['confidence'] ?? 'none' }}</div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">No WooCommerce product discovery sample yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        @else
            <p class="muted">
                Front product samples come from <code>POST /api/Product</code>, the read-only listing/search endpoint in the Front OpenAPI spec.
                Discovery keeps <code>pageSize</code> at 10 or lower and never calls <code>/api/products</code> CRUD.
            </p>
            <table>
                <thead>
                <tr>
                    <th>Front product ID</th>
                    <th>Name</th>
                    <th>Brand</th>
                    <th>Group/subgroup</th>
                    <th>Size label</th>
                    <th>GTIN</th>
                    <th>Identity</th>
                    <th>External SKU</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($productRows as $product)
                    @php($sizes = $product['productSizes'] ?? [[]])
                    @foreach ($sizes === [] ? [[]] : $sizes as $size)
                        <tr>
                            <td>{{ $product['productid'] ?? 'n/a' }}</td>
                            <td>{{ $product['name'] ?? 'n/a' }}</td>
                            <td>{{ $product['brand'] ?? 'n/a' }}</td>
                            <td>{{ $product['groupName'] ?? 'n/a' }} / {{ $product['subgroupName'] ?? 'n/a' }}</td>
                            <td>{{ $size['label'] ?? 'n/a' }}</td>
                            <td>{{ $size['gtin'] ?? 'n/a' }}</td>
                            <td>{{ $size['identity'] ?? 'n/a' }}</td>
                            <td>{{ $size['externalSKU'] ?? 'n/a' }}</td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="8">No Front product discovery sample yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        @endif
    </section>

    @if ($connection->type === 'woocommerce')
        <section class="panel">
            <h2>Variation Sample</h2>
            <p class="muted">Read-only variation sample for variable products in the latest WooCommerce product sample. This does not write or sync anything.</p>
            <table>
                <thead>
                <tr>
                    <th>Parent Woo ID</th>
                    <th>Variation ID</th>
                    <th>Name</th>
                    <th>SKU</th>
                    <th>Attributes</th>
                    <th>Stock</th>
                    <th>Regular price</th>
                    <th>Candidate GTIN/EAN</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($variationRows as $variation)
                    <tr>
                        <td>{{ $variation['parent_id'] ?? 'n/a' }}</td>
                        <td>{{ $variation['id'] ?? 'n/a' }}</td>
                        <td>{{ $variation['name'] ?? 'n/a' }}</td>
                        <td>{{ $variation['sku'] ?? 'n/a' }}</td>
                        <td>{{ implode(' / ', $variation['attributes'] ?? []) ?: 'n/a' }}</td>
                        <td>{{ $variation['stock_status'] ?? 'n/a' }} / {{ $variation['stock_quantity'] ?? 'n/a' }}</td>
                        <td>{{ $variation['regular_price'] ?? 'n/a' }}</td>
                        <td>
                            @php($candidate = $variation['gtin_candidate'] ?? [])
                            {{ $candidate['value'] ?? 'None' }}
                            <div class="muted">{{ $candidate['key'] ?? 'no key' }} / {{ $candidate['confidence'] ?? 'none' }}</div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">No WooCommerce variation discovery sample yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </section>

        <section class="panel">
            <h2>Woo Readiness Report</h2>
            <p class="muted">Preview only. This report checks sampled WooCommerce products and variations for fields needed before a future Woo to Front sync.</p>
            <div class="action-row">
                <span class="badge ready">Ready: {{ $readinessSummary['ready'] ?? 0 }}</span>
                <span class="badge warning">Needs attention: {{ $readinessSummary['needs_attention'] ?? 0 }}</span>
                <span class="badge blocked">Blocked: {{ $readinessSummary['blocked'] ?? 0 }}</span>
            </div>
            <table>
                <thead>
                <tr>
                    <th>Item</th>
                    <th>Name</th>
                    <th>SKU</th>
                    <th>GTIN/EAN</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Reason</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($readinessRows as $row)
                    <tr>
                        <td>
                            {{ $row['item_type'] ?? 'item' }}
                            <div class="muted">
                                Product: {{ $row['woo_product_id'] ?? 'n/a' }}
                                @if (($row['woo_variation_id'] ?? null) !== null)
                                    / Variation: {{ $row['woo_variation_id'] }}
                                @endif
                            </div>
                        </td>
                        <td>{{ $row['name'] ?? 'n/a' }}</td>
                        <td>{{ $row['sku'] ?? 'n/a' }}</td>
                        <td>
                            {{ $row['gtin'] ?? 'n/a' }}
                            <div class="muted">{{ $row['gtin_key'] ?? 'no key' }}</div>
                        </td>
                        <td>{{ $row['price'] ?? 'n/a' }}</td>
                        <td><span class="badge {{ $row['status'] === 'ready' ? 'ready' : ($row['status'] === 'blocked' ? 'blocked' : 'warning') }}">{{ str_replace('_', ' ', $row['status'] ?? 'unknown') }}</span></td>
                        <td>
                            @foreach (($row['errors'] ?? []) as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                            @foreach (($row['warnings'] ?? []) as $warning)
                                <div class="muted">{{ $warning }}</div>
                            @endforeach
                            @if (($row['errors'] ?? []) === [] && ($row['warnings'] ?? []) === [])
                                None
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">Run WooCommerce product discovery to generate a readiness report.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </section>
    @endif

    <section class="panel">
        <h2>Mapping Preview</h2>
        <p class="muted">Preview only. These rows are not saved to final product mappings.</p>
        <table>
            <thead>
            <tr>
                <th>Woo product</th>
                <th>Woo GTIN/EAN</th>
                <th>Woo SKU</th>
                <th>Best Front match</th>
                <th>Method</th>
                <th>Confidence</th>
                <th>Warning</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($mappingPreview as $row)
                <tr>
                    <td>{{ $row['woo_product']['name'] ?? 'n/a' }} ({{ $row['woo_product']['id'] ?? 'n/a' }})</td>
                    <td>{{ $row['woo_gtin'] ?? 'n/a' }}</td>
                    <td>{{ $row['woo_sku'] ?? 'n/a' }}</td>
                    <td>
                        @if ($row['front_match'])
                            {{ $row['front_match']['name'] ?? 'n/a' }}
                            <div class="muted">
                                GTIN: {{ $row['front_match']['gtin'] ?? 'n/a' }},
                                Identity: {{ $row['front_match']['identity'] ?? 'n/a' }},
                                External SKU: {{ $row['front_match']['external_sku'] ?? 'n/a' }}
                            </div>
                        @else
                            No match
                        @endif
                    </td>
                    <td>{{ $row['match_method'] }}</td>
                    <td>{{ $row['confidence'] }}</td>
                    <td>{{ $row['warning'] ?: 'None' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Run both WooCommerce and Front product discovery for the same organization to see a mapping preview.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
