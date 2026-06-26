@extends('layouts.app')

@php
    $modeLabel = match($profile?->mode) {
        'limited_write_test' => 'Limited write test',
        'production' => 'Production',
        default => 'Preview only',
    };
    $ready = (int) ($stats['ready'] ?? 0);
    $blocked = (int) ($stats['blocked'] ?? 0);
    $selected = (int) ($stats['selected'] ?? 0);
    $progress = $selected > 0 ? min(100, (int) round(($ready / max($selected, 1)) * 100)) : 0;
@endphp

@section('content')
    <section class="panel">
        <h1>Product Sync</h1>
        <p>This is where WooCommerce products are prepared and synced to Front. WooCommerce remains the master.</p>
        <div class="notice">Nothing is synced unless products pass validation and a sync run is started. The current foundation creates preview runs only.</div>
        <p class="muted">For large catalogs, only selected products are prepared. The portal never loads all WooCommerce products.</p>
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
                <span class="muted">Products selected for Front</span>
                <strong>{{ $selected }}</strong>
                <span class="muted">From the latest mapping PoC plan</span>
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
                <span class="muted">Last successful sync</span>
                <strong style="font-size:18px">{{ $stats['last_successful_sync'] ?: 'None yet' }}</strong>
            </div>
        </section>

        <section class="panel">
            <h2>Preparation Progress</h2>
            <div class="progress"><span style="width: {{ $progress }}%"></span></div>
            <p class="muted">{{ $ready }} ready of {{ $selected }} selected products. This is validation progress, not product sync progress.</p>
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
                    <p><span class="badge">{{ $latestRun->status }}</span> {{ $latestRun->created_at }}</p>
                    <p class="muted">{{ $latestRun->total_ready }} ready, {{ $latestRun->total_blocked }} needs attention.</p>
                @else
                    <p>No preview run yet.</p>
                @endif
            </div>
        </section>

        <section class="panel">
            <h2>Actions</h2>
            <p class="muted">Create a preview run from the latest mapping PoC plan. This stores local status rows only and performs no API writes.</p>
            <form class="inline-form" method="post" action="{{ route('product-sync.preview-run') }}">
                @csrf
                <button type="submit">Create preview run</button>
            </form>
            @if ($latestRun)
                <a class="button secondary" href="{{ route('product-sync.runs.show', $latestRun) }}">View latest run</a>
            @endif
            <a class="button secondary" href="{{ route('mapping.product-poc') }}">Go to mapping preview</a>
            <a class="button secondary" href="{{ route('product-sync.profile') }}">Sync profile settings</a>
            <button class="secondary" type="button" disabled>Start limited write test - coming next</button>
        </section>
    @endunless
@endsection
