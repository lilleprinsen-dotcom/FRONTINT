@extends('layouts.app')

@php
    $counts = $summary['counts'];
    $items = collect($summary['items']);
    $readyTotal = $counts['ready_sku_gtin'] + $counts['ready_sku_only'];
    $total = max(1, $counts['total']);
    $readyPercent = (int) round(($readyTotal / $total) * 100);
@endphp

@section('content')
    <section class="panel page-header">
        <span class="kicker">WooCommerce only</span>
        <h1>Woo Readiness</h1>
        <p>
            A simple product-data health check before Front is connected. It looks at the latest read-only WooCommerce sample and shows what can probably be used later.
        </p>
        <div class="notice">Read-only. No products, prices, stock, orders, WooCommerce data, or Front data are changed.</div>

        @if ($snapshot)
            <p class="muted">
                Latest WooCommerce discovery:
                {{ $snapshot->organization->name }}
                @if ($snapshot->connection)
                    | {{ $snapshot->connection->name }}
                @endif
                | checked {{ $snapshot->checked_at }}
            </p>
            <div class="action-row">
                <a class="button secondary" href="{{ route('connections.discovery', $snapshot->connection) }}">Open Woo sample</a>
                <a class="button secondary" href="{{ route('mapping.product-poc') }}">Select sample items</a>
            </div>
        @else
            <div class="warning">
                No WooCommerce product discovery sample found yet. Run read-only WooCommerce product discovery first.
            </div>
            <a class="button" href="{{ route('connections.index') }}">Go to connections</a>
        @endif
    </section>

    @if ($snapshot)
        <section class="panel">
            <h2>Overview</h2>
            <div class="progress" aria-label="Ready progress"><span style="width: {{ $readyPercent }}%"></span></div>
            <p class="muted">{{ $readyTotal }} of {{ $counts['total'] }} sampled items look ready or usable with SKU fallback.</p>

            <div class="metric-grid">
                <div class="metric">
                    <span class="muted">Ready with SKU + EAN</span>
                    <strong>{{ $counts['ready_sku_gtin'] }}</strong>
                    <span class="muted">Best candidates</span>
                </div>
                <div class="metric">
                    <span class="muted">Ready with SKU only</span>
                    <strong>{{ $counts['ready_sku_only'] }}</strong>
                    <span class="muted">Usable, barcode optional</span>
                </div>
                <div class="metric">
                    <span class="muted">Needs attention</span>
                    <strong>{{ $counts['needs_attention'] }}</strong>
                    <span class="muted">Review before syncing</span>
                </div>
                <div class="metric">
                    <span class="muted">Blocked</span>
                    <strong>{{ $counts['blocked'] }}</strong>
                    <span class="muted">Fix before sync</span>
                </div>
            </div>
        </section>

        <section class="grid">
            <div class="panel">
                <h2>Catalog shape</h2>
                <div class="summary-list">
                    <div class="summary-item"><span>Sampled items</span><strong>{{ $counts['total'] }}</strong></div>
                    <div class="summary-item"><span>Products</span><strong>{{ $counts['products'] }}</strong></div>
                    <div class="summary-item"><span>Variable parents</span><strong>{{ $counts['variable_parents'] }}</strong></div>
                    <div class="summary-item"><span>Sellable variations</span><strong>{{ $counts['sellable_variations'] }}</strong></div>
                </div>
            </div>

            <div class="panel">
                <h2>Fix first</h2>
                @foreach ($summary['fixes'] as $fix)
                    <p>
                        <strong>{{ $fix['label'] }}: {{ $fix['count'] }}</strong><br>
                        <span class="muted">{{ $fix['help'] }}</span>
                    </p>
                @endforeach
            </div>
        </section>

        <section class="panel">
            <h2>Product identifiers</h2>
            <div class="metric-grid">
                <div class="metric">
                    <span class="muted">Missing SKU</span>
                    <strong>{{ $counts['missing_sku'] }}</strong>
                    <span class="muted">Must be fixed</span>
                </div>
                <div class="metric">
                    <span class="muted">Missing EAN/GTIN</span>
                    <strong>{{ $counts['missing_gtin'] }}</strong>
                    <span class="muted">Warning if SKU exists</span>
                </div>
                <div class="metric">
                    <span class="muted">Duplicate SKUs</span>
                    <strong>{{ count($summary['duplicates']['skus']) }}</strong>
                    <span class="muted">Unsafe for matching</span>
                </div>
                <div class="metric">
                    <span class="muted">Duplicate EAN/GTIN</span>
                    <strong>{{ count($summary['duplicates']['gtins']) }}</strong>
                    <span class="muted">Check barcode data</span>
                </div>
            </div>

            @if ($summary['duplicates']['skus'] !== [] || $summary['duplicates']['gtins'] !== [])
                <div class="warning">
                    @if ($summary['duplicates']['skus'] !== [])
                        <p><strong>Duplicate SKUs:</strong> {{ implode(', ', $summary['duplicates']['skus']) }}</p>
                    @endif
                    @if ($summary['duplicates']['gtins'] !== [])
                        <p><strong>Duplicate EAN/GTIN:</strong> {{ implode(', ', $summary['duplicates']['gtins']) }}</p>
                    @endif
                </div>
            @endif
        </section>

        <section class="panel">
            <h2>Sample items</h2>
            <p class="muted">
                This table shows the latest sampled WooCommerce products and variations only. It is not the full 70,000-product catalog.
            </p>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Status</th>
                        <th>Item</th>
                        <th>Name</th>
                        <th>SKU</th>
                        <th>EAN/GTIN</th>
                        <th>Price</th>
                        <th>What to check</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>
                                <span class="badge {{ $item['status'] === 'ready' ? 'ready' : ($item['status'] === 'blocked' ? 'blocked' : 'warning-badge') }}">
                                    {{ $item['status'] === 'ready' ? 'Ready' : ($item['status'] === 'blocked' ? 'Blocked' : 'Needs attention') }}
                                </span>
                            </td>
                            <td>
                                {{ $item['item_key'] }}
                                @if ($item['item_type'] === 'variation')
                                    <div class="muted">Variation</div>
                                @elseif ($item['woo_type'] === 'variable')
                                    <div class="muted">Variable parent</div>
                                @endif
                            </td>
                            <td>{{ $item['name'] ?: 'n/a' }}</td>
                            <td>{{ $item['sku'] ?: 'n/a' }}</td>
                            <td>
                                {{ $item['gtin'] ?: 'n/a' }}
                                @if ($item['gtin_key'])
                                    <div class="muted">{{ $item['gtin_key'] }}</div>
                                @endif
                            </td>
                            <td>{{ $item['price'] ?: 'n/a' }}</td>
                            <td>
                                @foreach ($item['blocks'] as $block)
                                    <div class="danger">{{ $block }}</div>
                                @endforeach
                                @foreach ($item['warnings'] as $warning)
                                    <div class="warning">{{ $warning }}</div>
                                @endforeach
                                @if ($item['blocks'] === [] && $item['warnings'] === [])
                                    <span class="muted">Looks good in this sample.</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">No sampled WooCommerce items found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
