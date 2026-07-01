@extends('layouts.app')

@php
    $modeLabel = match($profile?->mode) {
        'limited_write_test' => 'Limited write test',
        'staging_batch' => 'Staging batch',
        'initial_full_sync' => 'Initial full sync planning',
        'incremental_sync' => 'Incremental sync planning',
        'production' => 'Production',
        default => 'Preview only',
    };
    $ready = (int) ($stats['ready'] ?? 0);
    $blocked = (int) ($stats['blocked'] ?? 0);
    $candidates = (int) ($stats['candidates'] ?? 0);
    $progress = $candidates > 0 ? min(100, (int) round(($ready / max($candidates, 1)) * 100)) : 0;
@endphp

@section('content')
    <section class="panel page-header">
        <span class="kicker">Preview only</span>
        <h1>Product Sync</h1>
        <p>Prepare a small staging batch from WooCommerce and send it to Front when ready. WooCommerce stays the master.</p>
        <div class="notice">Product sync can write selected products to Front. It does not write WooCommerce, orders, refunds, or gift cards.</div>
        <details class="technical-details">
            <summary>How matching works</summary>
            Stable matching uses the WooCommerce product or variation ID. SKU and EAN/GTIN are sent to Front as product fields and may change later without breaking the link.
        </details>
    </section>

    @unless ($organization)
        <section class="panel">
            <h2>No organization yet</h2>
            <p>Create an organization before preparing product sync.</p>
            <a class="button" href="{{ route('organizations.create') }}">Create organization</a>
        </section>
    @else
        <section class="status-board">
            <div class="status-card {{ in_array($profile?->mode, ['limited_write_test', 'staging_batch'], true) ? 'warning' : 'ready' }}">
                <span class="muted">Current mode</span>
                <strong>{{ $modeLabel }}</strong>
                <span>{{ $productionWritesEnabled ? 'Writes allowed by env' : 'Production writes disabled' }}</span>
            </div>
            <div class="status-card">
                <span class="muted">Sampled items</span>
                <strong>{{ $candidates }}</strong>
                <span>From latest Woo sample</span>
            </div>
            <div class="status-card ready">
                <span class="muted">Ready</span>
                <strong>{{ $ready }}</strong>
                <span>Can be selected</span>
            </div>
            <div class="status-card {{ $blocked > 0 ? 'blocked' : 'ready' }}">
                <span class="muted">Needs attention</span>
                <strong>{{ $blocked }}</strong>
                <span>Fix before syncing</span>
            </div>
            <div class="status-card {{ ($stats['failed'] ?? 0) > 0 ? 'blocked' : 'ready' }}">
                <span class="muted">Failed</span>
                <strong>{{ $stats['failed'] ?? 0 }}</strong>
                <span>Can be retried from run detail</span>
            </div>
            <div class="status-card">
                <span class="muted">Variations</span>
                <strong>{{ $stats['variations'] ?? 0 }}</strong>
                <span>Sellable size/color rows</span>
            </div>
        </section>

        <section class="panel">
            <h2>Readiness progress</h2>
            <div class="progress"><span style="width: {{ $progress }}%"></span></div>
            <p class="muted">{{ $ready }} ready of {{ $candidates }} candidate products. This is validation progress, not product sync progress.</p>
        </section>

        <section class="panel">
            <h2>Current picture</h2>
            <div class="summary-list">
                <div class="summary-item">
                    <span>Last product sample</span>
                    <strong>{{ $lastDiscovery ? $lastDiscovery->status.' on '.$lastDiscovery->checked_at : 'Not run yet' }}</strong>
                </div>
                <div class="summary-item">
                    <span>Last item selection</span>
                    <strong>{{ $latestPlan ? $latestPlan->selected_count.' item(s), '.$latestPlan->status : 'Not created yet' }}</strong>
                </div>
                <div class="summary-item">
                    <span>Last preview run</span>
                    <strong>{{ $latestRun ? $latestRun->status.' with '.$latestRun->total_ready.' ready' : 'Not created yet' }}</strong>
                </div>
                <div class="summary-item">
                    <span>WooCommerce connection</span>
                    <strong>{{ $connectionStatuses['woocommerce'] ?? 'Not connected' }}</strong>
                </div>
                <div class="summary-item">
                    <span>Front connection</span>
                    <strong>{{ $connectionStatuses['front'] ?? 'Not connected yet' }}</strong>
                </div>
            </div>
        </section>

        <section class="panel">
            <h2>Create staging batch</h2>
            <p class="muted">Select up to 100 products or variations. Use 1 item for a smoke test, 10 or 25 for API feedback, and 100 for the largest staging batch.</p>
            <details class="technical-details">
                <summary>Price behavior</summary>
                Regular price is sent as the Front product price. Sale price is not part of this staging product-write test.
            </details>
            @if ($wooBatchCandidates->isEmpty())
                <div class="warning">No WooCommerce discovery sample is available. Run Woo product discovery first.</div>
            @else
                <form method="post" action="{{ route('product-sync.staging-batch-run') }}">
                    @csrf
                    <div class="next-step" style="margin-bottom: 12px">
                        <strong>Tip:</strong> select one simple product first, then one variable parent, then a 25-item batch. Variable parents are written with discovered variations as Front sizes.
                    </div>
                    <div class="action-row" style="margin-bottom: 12px">
                        <button class="secondary" type="button" data-select-count="10">Select first 10</button>
                        <button class="secondary" type="button" data-select-count="25">Select first 25</button>
                        <button class="secondary" type="button" data-select-count="100">Select max 100</button>
                        <button class="secondary" type="button" data-clear-selection>Clear</button>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Select</th>
                                <th>Item</th>
                                <th>Name</th>
                                <th>SKU</th>
                                <th>GTIN/EAN</th>
                                <th>Price</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($wooBatchCandidates as $candidate)
                                @php($itemKey = $candidate['item_key'] ?? (($candidate['type'] ?? null) === 'variation' ? 'variation:'.($candidate['id'] ?? '') : 'product:'.($candidate['id'] ?? '')))
                                <tr>
                                    <td><input class="batch-candidate-checkbox" type="checkbox" name="woo_item_keys[]" value="{{ $itemKey }}"></td>
                                    <td>
                                        {{ $itemKey }}
                                        <div class="muted">{{ $candidate['type'] ?? 'product' }}</div>
                                    </td>
                                    <td>{{ $candidate['name'] ?? 'n/a' }}</td>
                                    <td>{{ $candidate['sku'] ?? 'n/a' }}</td>
                                    <td>{{ $candidate['gtin_candidate']['value'] ?? 'n/a' }}</td>
                                    <td>{{ $candidate['regular_price'] ?? $candidate['price'] ?? 'n/a' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p><button type="submit">Create staging batch run</button></p>
                </form>
                <script>
                    document.querySelectorAll('[data-select-count]').forEach((button) => {
                        button.addEventListener('click', () => {
                            const limit = Number(button.dataset.selectCount || 0);
                            const boxes = Array.from(document.querySelectorAll('.batch-candidate-checkbox'));
                            boxes.forEach((box, index) => {
                                box.checked = index < limit;
                            });
                        });
                    });
                    document.querySelector('[data-clear-selection]')?.addEventListener('click', () => {
                        document.querySelectorAll('.batch-candidate-checkbox').forEach((box) => {
                            box.checked = false;
                        });
                    });
                </script>
            @endif
        </section>

        <section class="panel">
            <h2>What to do next</h2>
            <p class="muted">Create a staging batch, open the run, then start or retry sync from the run detail page.</p>
            <div class="action-row">
                <a class="button" href="{{ route('woo-readiness.index') }}">Review Woo readiness</a>
                @if ($latestRun)
                    <a class="button secondary" href="{{ route('product-sync.runs.show', $latestRun) }}">Prepare Front dry-run</a>
                @endif
                <a class="button secondary" href="{{ route('testing-log.index') }}">Open Testing Log</a>
                <a class="button secondary" href="{{ route('product-sync.runs.index') }}">View preview runs</a>
            </div>
            @if ($latestRun)
                <p class="muted">Open the latest run to select up to 10 ready or warning items and preview the exact Front payload. No Front API calls are made.</p>
            @endif
            <details class="technical-details">
                <summary>Technical actions</summary>
                <div class="action-row" style="margin-top: 10px">
                    <a class="button secondary" href="{{ route('product-sync.profile') }}">Sync profile settings</a>
                    <button class="secondary" type="button" disabled>Retry failed items - coming later</button>
                    <button class="secondary" type="button" disabled>Start initial full sync - disabled until write implementation exists</button>
                </div>
            </details>
        </section>
    @endunless
@endsection
