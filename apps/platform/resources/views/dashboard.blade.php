@extends('layouts.app')

@php
    $allConnections = $organizations->flatMap(fn ($organization) => $organization->connections);
    $connectionCount = $organizations->sum(fn ($organization) => $organization->connections->count());
    $readyConnections = $organizations->sum(fn ($organization) => $organization->connections->filter(fn ($connection) => in_array($connection->status, ['success', 'connected', 'active'], true))->count());
    $connectionsNeedingAttention = $organizations->sum(fn ($organization) => $organization->connections->filter(fn ($connection) => $connection->last_error || in_array($connection->status, ['failed', 'error'], true))->count());
    $wooConnections = $allConnections->where('type', 'woocommerce')->count();
    $frontConnections = $allConnections->filter(fn ($connection) => in_array($connection->type, ['front', 'front_systems'], true))->count();
@endphp

@section('content')
    <section class="panel">
        <h1>Dashboard</h1>
        <p>OmniBridge keeps WooCommerce as the master and prepares Front Systems for store operations.</p>
        <div class="notice">Production writes are {{ $productionWritesEnabled ? 'enabled' : 'disabled' }}. {{ $productionWritesEnabled ? 'Review the launch checklist before continuing.' : 'This is the expected safe mode.' }}</div>
        @if ($connectionHttpTestsEnabled)
            <div class="warning">Live read-only checks are enabled. Real external systems may be contacted from testing pages.</div>
        @endif
    </section>

    @if ($productionWritesEnabled)
        <div class="danger">Production writes are enabled. This should remain disabled until a production launch checklist has been completed.</div>
    @endif

    <section class="metric-grid">
        <div class="metric">
            <span class="muted">Organizations</span>
            <strong>{{ $organizations->count() }}</strong>
            <span class="muted">Configured accounts</span>
        </div>
        <div class="metric">
            <span class="muted">Connections</span>
            <strong>{{ $readyConnections }} / {{ $connectionCount }}</strong>
            <span class="muted">Ready connections</span>
        </div>
        <div class="metric">
            <span class="muted">Needs attention</span>
            <strong>{{ $connectionsNeedingAttention + $failedEventsCount }}</strong>
            <span class="muted">Connection issues and failed events</span>
        </div>
        <div class="metric">
            <span class="muted">Woo readiness</span>
            <strong>Review</strong>
            <span class="muted">Check product data before Front setup</span>
        </div>
    </section>

    <section class="grid">
        <div class="panel">
            <h2>Setup Progress</h2>
            <p><span class="status-dot {{ $organizations->isNotEmpty() ? 'ready' : 'warning' }}"></span>Organization created</p>
            <p><span class="status-dot {{ $wooConnections > 0 ? 'ready' : 'warning' }}"></span>WooCommerce connection added</p>
            <p><span class="status-dot {{ $frontConnections > 0 ? 'ready' : 'warning' }}"></span>Front Systems connection added</p>
            <p><span class="status-dot warning"></span>Product sync writes not enabled</p>
        </div>

        <div class="panel">
            <h2>Next Steps</h2>
            <p class="muted">Normal setup should stay simple: connect WooCommerce, review product readiness, then prepare product sync.</p>
            <div class="action-row">
                <a class="button" href="{{ route('connections.index') }}">Manage connections</a>
                <a class="button secondary" href="{{ route('woo-readiness.index') }}">Review Woo readiness</a>
            </div>
        </div>

    </section>

    @forelse ($organizations as $organization)
        <section class="panel">
            <h2>{{ $organization->name }}</h2>
            <p class="muted">Environment: {{ $organization->environment }} | Status: {{ $organization->status }}</p>
            <table>
                <thead>
                <tr>
                    <th>Area</th>
                    <th>Status</th>
                    <th>What to do</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>Connections</td>
                    <td>{{ $organization->connections->count() }} configured</td>
                    <td><a href="{{ route('connections.index') }}">Review connections</a></td>
                </tr>
                <tr>
                    <td>Woo readiness</td>
                    <td>Read-only</td>
                    <td><a href="{{ route('woo-readiness.index') }}">Review products</a></td>
                </tr>
                <tr>
                    <td>Technical setup</td>
                    <td>{{ $organization->webhookEndpoints->count() }} webhook endpoint(s)</td>
                    <td><a href="{{ route('advanced.index') }}">Open Advanced</a></td>
                </tr>
                </tbody>
            </table>
        </section>
    @empty
        <section class="panel">
            <h2>Start Setup</h2>
            <p>Create the first organization before adding WooCommerce or Front connections.</p>
            <a class="button" href="{{ route('organizations.create') }}">Create organization</a>
        </section>
    @endforelse
@endsection
