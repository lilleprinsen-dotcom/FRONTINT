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
    <section class="panel">
        <h1>Product Sync</h1>
        <p>This is where WooCommerce products are prepared for Front. WooCommerce remains the master.</p>
        <div class="notice">No products are written to Front yet. This page prepares and monitors sync readiness.</div>
        <p class="muted">The production goal is all relevant products and variations, processed later in small queued batches instead of one large run.</p>
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
                <span class="muted">Products discovered</span>
                <strong>{{ $candidates }}</strong>
                <span class="muted">From the latest preview run or mapping preview</span>
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
                <span class="muted">Failed products</span>
                <strong>{{ $stats['failed'] ?? 0 }}</strong>
                <span class="muted">No write sync exists yet</span>
            </div>
            <div class="metric">
                <span class="muted">Variations discovered</span>
                <strong>{{ $stats['variations'] ?? 0 }}</strong>
                <span class="muted">Variation-level status is supported</span>
            </div>
            <div class="metric">
                <span class="muted">Incremental updates pending</span>
                <strong>{{ $pendingIncrementalEvents }}</strong>
                <span class="muted">Webhook processing is planned later</span>
            </div>
            <div class="metric">
                <span class="muted">Last successful sync</span>
                <strong style="font-size:18px">{{ $stats['last_successful_sync'] ?: 'None yet' }}</strong>
            </div>
        </section>

        <section class="panel">
            <h2>Preparation Progress</h2>
            <div class="progress"><span style="width: {{ $progress }}%"></span></div>
            <p class="muted">{{ $ready }} ready of {{ $candidates }} candidate products. This is validation progress, not product sync progress.</p>
        </section>

        <section class="grid">
            <div class="panel">
                <h2>Last Discovery</h2>
                @if ($lastDiscovery)
                    <p><span class="badge">{{ $lastDiscovery->source_system }}</span> {{ $lastDiscovery->status }}</p>
                    <p class="muted">Last checked {{ $lastDiscovery->checked_at }}.</p>
                @else
                    <p>No product discovery yet.</p>
                @endif
            </div>
            <div class="panel">
                <h2>Last Mapping Preview</h2>
                @if ($latestPlan)
                    <p><span class="badge {{ $latestPlan->status === 'ready' ? 'ready' : 'blocked' }}">{{ $latestPlan->status }}</span></p>
                    <p class="muted">{{ $latestPlan->selected_count }} selected product(s).</p>
                @else
                    <p>No mapping PoC plan yet.</p>
                @endif
            </div>
            <div class="panel">
                <h2>Last Sync Run</h2>
                @if ($latestRun)
                    <p><span class="badge">{{ $latestRun->status }}</span> <span class="badge">{{ $latestRun->run_type }}</span></p>
                    <p class="muted">{{ $latestRun->total_ready }} ready, {{ $latestRun->total_blocked }} needs attention, {{ $latestRun->total_pending }} waiting.</p>
                @else
                    <p>No preview run yet.</p>
                @endif
            </div>
            <div class="panel">
                <h2>Queue Status</h2>
                <p><span class="badge warning-badge">Placeholder</span></p>
                <p class="muted">Queue workers and background catalog scanning will be added before real sync writes.</p>
            </div>
            <div class="panel">
                <h2>Connections</h2>
                <p>WooCommerce: <strong>{{ $connectionStatuses['woocommerce'] ?? 'Not connected' }}</strong></p>
                <p>Front: <strong>{{ $connectionStatuses['front'] ?? 'Not connected' }}</strong></p>
            </div>
        </section>

        <section class="panel">
            <h2>Actions</h2>
            <p class="muted">This page shows product sync readiness. Testing workflows such as discovery, mapping previews, and preview-run creation are kept in the Testing Lab.</p>
            @if ($latestRun)
                <a class="button secondary" href="{{ route('product-sync.runs.show', $latestRun) }}">View latest run</a>
            @endif
            <a class="button secondary" href="{{ route('product-sync.runs.index') }}">View sync runs</a>
            <a class="button secondary" href="{{ route('product-sync.profile') }}">Sync profile settings</a>
            <button class="secondary" type="button" disabled>Retry failed items - coming later</button>
            <button class="secondary" type="button" disabled>Start initial full sync - disabled until write implementation exists</button>
        </section>
    @endunless
@endsection
