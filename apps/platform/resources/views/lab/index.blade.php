@extends('layouts.app')

@section('content')
    <section class="panel page-header">
        <span class="kicker">Advanced</span>
        <h1>Testing Lab</h1>
        <p>Use this page for staging checks, read-only discovery, mapping previews, and other setup experiments. Normal store-owner work should happen on Dashboard, Connections, Woo Readiness, and Product Sync.</p>
        <div class="warning">This page is intentionally separate from the normal merchant workflow. It is for setup, testing, and troubleshooting only.</div>

        @if ($productionWritesEnabled)
            <div class="danger">Production writes are enabled. Disable them before running lab workflows.</div>
        @else
            <div class="notice">Production writes are disabled. That is the expected safe state.</div>
        @endif

        @if ($connectionHttpTestsEnabled)
            <div class="warning">Live read-only HTTP tests are enabled. Real WooCommerce or Front systems may be contacted.</div>
        @else
            <div class="notice">Safe mode is on. Discovery and live checks will be skipped unless read-only HTTP tests are enabled locally or in staging.</div>
        @endif
    </section>

    <section class="grid">
        <div class="panel">
            <h2>Read-Only Discovery</h2>
            <p class="muted">Fetch small staging samples from WooCommerce and Front. No product, price, stock, or order writes occur.</p>
            @if ($latestDiscovery)
                <p><span class="badge">{{ $latestDiscovery->status }}</span> {{ $latestDiscovery->source_system }} {{ $latestDiscovery->discovery_type }}</p>
                <p class="muted">Last checked {{ $latestDiscovery->checked_at }}.</p>
            @else
                <p class="muted">No discovery snapshot yet.</p>
            @endif
            <a class="button secondary" href="{{ route('discovery.index') }}">Open discovery</a>
        </div>

        <div class="panel">
            <h2>Mapping Preview</h2>
            <p class="muted">Compare small WooCommerce and Front product samples before any mapping is saved.</p>
            @if ($latestPlan)
                <p><span class="badge {{ $latestPlan->status === 'ready' ? 'ready' : 'warning-badge' }}">{{ $latestPlan->status }}</span></p>
                <p class="muted">{{ $latestPlan->selected_count }} product(s), created {{ $latestPlan->created_at }}.</p>
            @else
                <p class="muted">No mapping preview plan yet.</p>
            @endif
            <a class="button secondary" href="{{ route('mapping.product-poc') }}">Open mapping preview</a>
        </div>

        <div class="panel">
            <h2>Preview Runs</h2>
            <p class="muted">Create local run/status rows from a mapping preview. This is still not product sync.</p>
            @if ($latestRun)
                <p><span class="badge">{{ $latestRun->status }}</span> Run #{{ $latestRun->id }}</p>
                <p class="muted">{{ $latestRun->total_ready }} ready, {{ $latestRun->total_blocked }} needs attention.</p>
                <a class="button secondary" href="{{ route('product-sync.runs.show', $latestRun) }}">Open latest run</a>
            @else
                <p class="muted">No preview run yet.</p>
            @endif
            @if ($latestPlan)
                <form class="inline-form" method="post" action="{{ route('product-sync.preview-run') }}">
                    @csrf
                    <button type="submit">Create preview run</button>
                </form>
            @else
                <button class="secondary" type="button" disabled>Create preview run</button>
            @endif
            <a class="button secondary" href="{{ route('product-sync.runs.index') }}">View runs</a>
        </div>
    </section>

    @foreach ($organizations as $organization)
        <section class="panel">
            <h2>{{ $organization->name }} Lab Actions</h2>
            <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Connection</th>
                    <th>Type</th>
                    <th>Last discovery</th>
                    <th>Lab actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($organization->connections as $connection)
                    @php($snapshot = $connection->latestDiscoverySnapshot)
                    <tr>
                        <td>{{ $connection->name }}</td>
                        <td>{{ $connection->type }}</td>
                        <td>
                            @if ($snapshot)
                                {{ $snapshot->status }} {{ $snapshot->discovery_type }}
                                <div class="muted">{{ $snapshot->checked_at }}</div>
                            @else
                                Not checked yet
                            @endif
                        </td>
                        <td>
                            <div class="action-row">
                                <a class="button secondary" href="{{ route('connections.discovery', $connection) }}">Open discovery</a>
                                @if (in_array($connection->type, ['front', 'front_systems'], true))
                                    <form class="inline-form" method="post" action="{{ route('connections.discover.stores', $connection) }}">
                                        @csrf
                                        <button class="secondary" type="submit">Discover stores</button>
                                    </form>
                                @endif
                                @if (in_array($connection->type, ['woocommerce', 'front', 'front_systems'], true))
                                    <form class="inline-form" method="post" action="{{ route('connections.discover.products', $connection) }}">
                                        @csrf
                                        <button class="secondary" type="submit">Discover products</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">Add connections before using lab discovery.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </section>
    @endforeach
@endsection
