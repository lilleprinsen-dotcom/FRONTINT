@extends('layouts.app')

@php
    $allConnections = $organizations->flatMap(fn ($organization) => $organization->connections);
    $connectionCount = $organizations->sum(fn ($organization) => $organization->connections->count());
    $readyConnections = $organizations->sum(fn ($organization) => $organization->connections->filter(fn ($connection) => in_array($connection->status, ['success', 'connected', 'active'], true))->count());
    $connectionsNeedingAttention = $organizations->sum(fn ($organization) => $organization->connections->filter(fn ($connection) => $connection->last_error || in_array($connection->status, ['failed', 'error'], true))->count());
    $wooConnections = $allConnections->where('type', 'woocommerce')->count();
    $frontConnections = $allConnections->filter(fn ($connection) => in_array($connection->type, ['front', 'front_systems'], true))->count();
    $setupStepsDone = ($wooConnections > 0 ? 1 : 0) + ($readyConnections > 0 ? 1 : 0) + ($frontConnections > 0 ? 1 : 0);
    $setupPercent = (int) round(($setupStepsDone / 3) * 100);
@endphp

@section('content')
    <section class="panel page-header">
        <span class="kicker">Store owner overview</span>
        <h1>Store setup overview</h1>
        <p>Start with WooCommerce. Check the product data. Add Front when the account is ready. WooCommerce remains the master system.</p>
        <div class="notice">Production writes are {{ $productionWritesEnabled ? 'enabled' : 'disabled' }}. {{ $productionWritesEnabled ? 'Review the launch checklist before continuing.' : 'This is the expected safe mode.' }}</div>
        @if ($connectionHttpTestsEnabled)
            <div class="warning">Live read-only checks are enabled. Real external systems may be contacted from testing pages.</div>
        @endif
    </section>

    @if ($productionWritesEnabled)
        <div class="danger">Production writes are enabled. This should remain disabled until a production launch checklist has been completed.</div>
    @endif

    <section class="panel">
        <h2>Setup progress</h2>
        <div class="progress large"><span style="width: {{ $setupPercent }}%"></span></div>
        <p class="muted">{{ $setupStepsDone }} of 3 basic setup steps are in place.</p>
        <div class="status-board">
            <div class="status-card {{ $wooConnections > 0 ? 'ready' : 'warning' }}">
                <span class="muted">Step 1</span>
                <strong>WooCommerce</strong>
                <span>{{ $wooConnections > 0 ? 'Added' : 'Add this first' }}</span>
            </div>
            <div class="status-card {{ $readyConnections > 0 ? 'ready' : 'warning' }}">
                <span class="muted">Step 2</span>
                <strong>Test connection</strong>
                <span>{{ $readyConnections > 0 ? 'At least one test worked' : 'Run a safe test' }}</span>
            </div>
            <div class="status-card {{ $frontConnections > 0 ? 'ready' : 'warning' }}">
                <span class="muted">Step 3</span>
                <strong>Front Systems</strong>
                <span>{{ $frontConnections > 0 ? 'Added' : 'Can be added later' }}</span>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="split-row">
            <div>
                <h2>What to do next</h2>
                <p class="muted">Use these pages in order. Testing Log is for sending results back when something needs review.</p>
            </div>
            <a class="button secondary" href="{{ route('testing-log.index') }}">Open Testing Log</a>
        </div>
        <div class="owner-flow">
            <div class="flow-step">
                <span class="step-number">1</span>
                <strong>Connect WooCommerce</strong>
                <p class="muted">Add the store URL and credentials, then run the read-only checks.</p>
                <a class="button" href="{{ route('connections.index') }}">Open connections</a>
            </div>
            <div class="flow-step">
                <span class="step-number">2</span>
                <strong>Review product readiness</strong>
                <p class="muted">See which sampled products and variations look ready before Front is connected.</p>
                <a class="button secondary" href="{{ route('woo-readiness.index') }}">Review Woo readiness</a>
            </div>
            <div class="flow-step">
                <span class="step-number">3</span>
                <strong>Prepare sync plan</strong>
                <p class="muted">Preview what will be synced later. Product writes are still disabled.</p>
                <a class="button secondary" href="{{ route('product-sync.index') }}">Open product sync</a>
            </div>
        </div>
    </section>

    @forelse ($organizations as $organization)
        <section class="panel">
            <div class="split-row">
                <div>
                    <h2>{{ $organization->name }}</h2>
                    <p class="muted">{{ ucfirst($organization->environment) }} environment. {{ $organization->connections->count() }} connection(s) configured.</p>
                </div>
                <span class="badge {{ $organization->status === 'active' ? 'ready' : 'warning-badge' }}">{{ $organization->status }}</span>
            </div>
            <div class="summary-list">
                <div class="summary-item">
                    <span><span class="status-dot {{ $wooConnections > 0 ? 'ready' : 'warning' }}"></span>WooCommerce connection</span>
                    <a href="{{ route('connections.index') }}">{{ $wooConnections > 0 ? 'Review' : 'Add' }}</a>
                </div>
                <div class="summary-item">
                    <span><span class="status-dot warning"></span>Product readiness</span>
                    <a href="{{ route('woo-readiness.index') }}">Review products</a>
                </div>
                <div class="summary-item">
                    <span><span class="status-dot {{ $frontConnections > 0 ? 'ready' : 'warning' }}"></span>Front connection</span>
                    <a href="{{ route('connections.index') }}">{{ $frontConnections > 0 ? 'Review' : 'Add later' }}</a>
                </div>
                <div class="summary-item">
                    <span><span class="status-dot {{ ($connectionsNeedingAttention + $failedEventsCount) > 0 ? 'blocked' : 'ready' }}"></span>Latest test results</span>
                    <a href="{{ route('testing-log.index') }}">Open log</a>
                </div>
            </div>
        </section>
    @empty
        <section class="panel">
            <h2>Start Setup</h2>
            <p>Create the first organization before adding WooCommerce or Front connections.</p>
            <a class="button" href="{{ route('organizations.create') }}">Create organization</a>
        </section>
    @endforelse
@endsection
