@extends('layouts.app')

@php
    $modeLabel = match($profile?->mode) {
        'limited_write_test' => 'Limited write test',
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
        <p>This page shows whether products are prepared for a future WooCommerce to Front sync. Nothing is sent to Front yet.</p>
        <div class="notice">No products are written to Front yet. This page prepares and monitors sync readiness.</div>
        <p class="muted">Later, the full catalog will be handled in small queued batches. For now this page is a status overview.</p>
    </section>

    @unless ($organization)
        <section class="panel">
            <h2>No organization yet</h2>
            <p>Create an organization before preparing product sync.</p>
            <a class="button" href="{{ route('organizations.create') }}">Create organization</a>
        </section>
    @else
        <section class="metric-grid">
            <div class="metric">
                <span class="muted">Current mode</span>
                <strong>{{ $modeLabel }}</strong>
                <span class="badge {{ $profile?->mode === 'preview_only' ? 'ready' : 'warning-badge' }}">{{ $productionWritesEnabled ? 'Writes allowed by env' : 'Writes disabled' }}</span>
            </div>
            <div class="metric">
                <span class="muted">Sampled items</span>
                <strong>{{ $candidates }}</strong>
                <span class="muted">From latest preview data</span>
            </div>
            <div class="metric">
                <span class="muted">Ready products</span>
                <strong>{{ $ready }}</strong>
                <span class="badge ready">Ready</span>
            </div>
            <div class="metric">
                <span class="muted">Needs attention</span>
                <strong>{{ $blocked }}</strong>
                <span class="badge blocked">Blocked</span>
            </div>
            <div class="metric">
                <span class="muted">Failed</span>
                <strong>{{ $stats['failed'] ?? 0 }}</strong>
                <span class="muted">No write sync exists yet</span>
            </div>
            <div class="metric">
                <span class="muted">Variations</span>
                <strong>{{ $stats['variations'] ?? 0 }}</strong>
                <span class="muted">Variation rows are first-class candidates</span>
            </div>
            <div class="metric">
                <span class="muted">Waiting updates</span>
                <strong>{{ $pendingIncrementalEvents }}</strong>
                <span class="muted">Incremental sync comes later</span>
            </div>
            <div class="metric">
                <span class="muted">Last successful sync</span>
                <strong style="font-size:18px">{{ $stats['last_successful_sync'] ?: 'None yet' }}</strong>
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
            <h2>What to do next</h2>
            <p class="muted">For now, use Woo Readiness to clean up product data. The Testing Lab keeps preview experiments separate from daily owner pages.</p>
            <div class="action-row">
                <a class="button" href="{{ route('woo-readiness.index') }}">Review Woo readiness</a>
                @if ($latestRun)
                    <a class="button secondary" href="{{ route('product-sync.runs.show', $latestRun) }}">Prepare Front dry-run</a>
                @endif
                <a class="button secondary" href="{{ route('lab.index') }}">Open Testing Lab</a>
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
