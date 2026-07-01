@extends('layouts.app')

@php
    $counts = $summary['counts'];
    $items = collect($summary['items']);
    $readyTotal = $counts['ready_sku_gtin'] + $counts['ready_sku_only'];
    $total = max(1, $counts['total']);
    $readyPercent = (int) round(($readyTotal / $total) * 100);
    $attentionPercent = (int) round(($counts['needs_attention'] / $total) * 100);
    $blockedPercent = max(0, 100 - $readyPercent - $attentionPercent);
@endphp

@section('content')
    <section class="panel page-header">
        <span class="kicker">WooCommerce only</span>
        <h1>WooCommerce readiness</h1>
        <p>A simple health check for product data. It answers one question: are the sampled products ready for a later Front sync?</p>
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
                <a class="button secondary" href="{{ route('testing-log.index') }}">Open Testing Log</a>
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
            <h2>Readiness score</h2>
            <div class="progress-segments" aria-label="Woo readiness progress">
                <span class="ready-part" style="width: {{ $readyPercent }}%"></span>
                <span class="warning-part" style="width: {{ $attentionPercent }}%"></span>
                <span class="blocked-part" style="width: {{ $blockedPercent }}%"></span>
            </div>
            <p class="muted">{{ $readyTotal }} of {{ $counts['total'] }} sampled items are usable now. Yellow means review. Red means fix first.</p>

            <div class="status-board">
                <div class="status-card ready">
                    <span class="muted">Best</span>
                    <strong>{{ $counts['ready_sku_gtin'] }}</strong>
                    <span>Ready with SKU + EAN</span>
                </div>
                <div class="status-card ready">
                    <span class="muted">Usable</span>
                    <strong>{{ $counts['ready_sku_only'] }}</strong>
                    <span>Ready with SKU only</span>
                </div>
                <div class="status-card warning">
                    <span class="muted">Review</span>
                    <strong>{{ $counts['needs_attention'] }}</strong>
                    <span>Needs attention</span>
                </div>
                <div class="status-card {{ $counts['blocked'] > 0 ? 'blocked' : 'ready' }}">
                    <span class="muted">Fix first</span>
                    <strong>{{ $counts['blocked'] }}</strong>
                    <span>Blocked</span>
                </div>
            </div>
        </section>

        <section class="panel">
            <h2>Main things to fix</h2>
            <p class="muted">Start here. A product should at minimum have a SKU and a price. EAN/GTIN is useful, but SKU-only products can still be handled.</p>
            <div class="status-board">
                <div class="status-card {{ $counts['missing_sku'] > 0 ? 'blocked' : 'ready' }}">
                    <span class="muted">Must fix</span>
                    <strong>{{ $counts['missing_sku'] }}</strong>
                    <span>Missing SKU</span>
                </div>
                <div class="status-card {{ $counts['missing_price'] > 0 ? 'blocked' : 'ready' }}">
                    <span class="muted">Must fix</span>
                    <strong>{{ $counts['missing_price'] }}</strong>
                    <span>Missing price</span>
                </div>
                <div class="status-card {{ count($summary['duplicates']['skus']) > 0 ? 'blocked' : 'ready' }}">
                    <span class="muted">Must check</span>
                    <strong>{{ count($summary['duplicates']['skus']) }}</strong>
                    <span>Duplicate SKUs</span>
                </div>
                <div class="status-card {{ count($summary['duplicates']['gtins']) > 0 ? 'warning' : 'ready' }}">
                    <span class="muted">Barcode check</span>
                    <strong>{{ count($summary['duplicates']['gtins']) }}</strong>
                    <span>Duplicate EAN/GTIN</span>
                </div>
            </div>

            @if ($summary['duplicates']['skus'] !== [] || $summary['duplicates']['gtins'] !== [])
                <details class="technical-details">
                    <summary>Show duplicate values</summary>
                    @if ($summary['duplicates']['skus'] !== [])
                        <p><strong>Duplicate SKUs:</strong> {{ implode(', ', $summary['duplicates']['skus']) }}</p>
                    @endif
                    @if ($summary['duplicates']['gtins'] !== [])
                        <p><strong>Duplicate EAN/GTIN:</strong> {{ implode(', ', $summary['duplicates']['gtins']) }}</p>
                    @endif
                </details>
            @endif
        </section>

        <section class="grid">
            <div class="panel">
                <h2>Product shape</h2>
                <div class="summary-list">
                    <div class="summary-item"><span>Sampled items</span><strong>{{ $counts['total'] }}</strong></div>
                    <div class="summary-item"><span>Products</span><strong>{{ $counts['products'] }}</strong></div>
                    <div class="summary-item"><span>Variable parents</span><strong>{{ $counts['variable_parents'] }}</strong></div>
                    <div class="summary-item"><span>Sellable variations</span><strong>{{ $counts['sellable_variations'] }}</strong></div>
                </div>
            </div>

            <div class="panel">
                <h2>Recommended next step</h2>
                @if ($counts['blocked'] > 0)
                    <div class="next-step">
                        <strong>Fix blocked products first.</strong>
                        <p class="muted">Missing SKU, missing price, or duplicates can create wrong Front products.</p>
                    </div>
                @else
                    <div class="next-step">
                        <strong>Create a staging batch.</strong>
                        <p class="muted">The sample looks usable. Go to Product Sync and select a small set.</p>
                    </div>
                @endif
                <div class="action-row" style="margin-top: 12px">
                    <a class="button" href="{{ route('product-sync.index') }}">Open Product Sync</a>
                    <a class="button secondary" href="{{ route('testing-log.index') }}">Open Testing Log</a>
                </div>
            </div>
        </section>

        <details class="panel">
            <summary><strong>Show detailed sampled items</strong></summary>
            <p class="muted">This table shows only the latest sampled WooCommerce products and variations. It is not the full 70,000-product catalog.</p>
            <div class="table-wrap">
                <table class="simple-table">
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
                                    <div>{{ $block }}</div>
                                @endforeach
                                @foreach ($item['warnings'] as $warning)
                                    <div>{{ $warning }}</div>
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
        </details>
    @endif
@endsection
