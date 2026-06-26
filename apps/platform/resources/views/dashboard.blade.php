@extends('layouts.app')

@section('content')
    <h1>Dashboard</h1>

    <div class="panel">
        <h2>Environment</h2>
        <p>
            Mode: <strong>{{ $environment }}</strong><br>
            Production writes: <strong>{{ $productionWritesEnabled ? 'enabled' : 'disabled' }}</strong><br>
            Live HTTP connection tests: <strong>{{ $connectionHttpTestsEnabled ? 'enabled' : 'disabled' }}</strong>
        </p>
    </div>

    @if ($productionWritesEnabled)
        <div class="danger">Production writes are enabled. This should remain disabled until a production launch checklist has been completed.</div>
    @else
        <div class="warning">Production writes are disabled. This is the expected staging-safe mode.</div>
    @endif

    @unless ($connectionHttpTestsEnabled)
        <div class="warning">Live HTTP connection checks are disabled. Connection tests and discovery actions only verify stored settings.</div>
    @else
        <div class="warning">Live read-only HTTP tests are enabled. No writes are allowed, but real external systems may be contacted.</div>
    @endunless

    <section class="panel">
        <h2>Safe Product Setup</h2>
        <p class="muted">
            Keep safe mode on for everyday setup. Turn on live checks only in local or staging when you are testing real connections.
        </p>
        <ul>
            <li>WooCommerce stays the master.</li>
            <li>Front stays the store work surface.</li>
            <li>Large catalogs are prepared in small selected groups.</li>
            <li>Products show Ready or Needs attention before any future sync.</li>
            <li>No sync is performed and no data is written.</li>
        </ul>
    </section>

    <div class="grid">
        <section class="panel">
            <h2>Organizations</h2>
            <p>{{ $organizations->count() }} configured</p>
            <a class="button" href="{{ route('organizations.create') }}">Add organization</a>
        </section>
        <section class="panel">
            <h2>Failed Events</h2>
            <p>{{ $failedEventsCount }} failed</p>
        </section>
        <section class="panel">
            <h2>Product Mapping PoC</h2>
            <p class="muted">Preview a local 10-product WooCommerce to Front sync plan from stored discovery snapshots.</p>
            <a class="button secondary" href="{{ route('mapping.product-poc') }}">Open mapping PoC</a>
        </section>
        <section class="panel">
            <h2>Product Sync</h2>
            <p class="muted">Prepare selected WooCommerce products for Front with clear ready/needs attention status.</p>
            <a class="button secondary" href="{{ route('product-sync.index') }}">Open product sync</a>
        </section>
    </div>

    @forelse ($organizations as $organization)
        <section class="panel">
            <h2>{{ $organization->name }}</h2>
            <p class="muted">
                Slug: {{ $organization->slug }} |
                Environment: {{ $organization->environment }} |
                Status: {{ $organization->status }}
            </p>
            <p>
                <a class="button secondary" href="{{ route('organizations.edit', $organization) }}">Edit organization</a>
                <a class="button" href="{{ route('connections.create', ['organization_id' => $organization->id]) }}">Add connection</a>
            </p>

            <h3>Connections</h3>
            <table>
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Last checked</th>
                    <th>Last error</th>
                    <th>Last discovery</th>
                    <th>Base URL</th>
                    <th>Credentials</th>
                    <th>Front stores</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($organization->connections as $connection)
                    <tr>
                        <td>{{ $connection->name }}</td>
                        <td>{{ $connectionTypes[$connection->type] ?? $connection->type }}</td>
                        <td>
                            <strong>{{ $connection->status }}</strong>
                            @if (in_array($connection->type, ['woocommerce', 'front', 'front_systems'], true))
                                <div class="muted">Read-only API test</div>
                            @endif
                            @if ($connection->last_test_status)
                                <div class="muted">Test: {{ $connection->last_test_status }}</div>
                            @endif
                            @if ($connection->last_http_status)
                                <div class="muted">HTTP {{ $connection->last_http_status }}</div>
                            @endif
                            @if ($connection->last_response_time_ms !== null)
                                <div class="muted">{{ $connection->last_response_time_ms }} ms</div>
                            @endif
                        </td>
                        <td>{{ $connection->last_checked_at?->diffForHumans() ?: 'Not checked yet' }}</td>
                        <td>{{ $connection->last_error ?: 'None' }}</td>
                        <td>
                            @php($latestDiscovery = $connection->latestDiscoverySnapshot)
                            @if ($latestDiscovery)
                                <strong>{{ $latestDiscovery->status }}</strong>
                                <div class="muted">{{ $latestDiscovery->discovery_type }} checked {{ $latestDiscovery->checked_at?->diffForHumans() }}</div>
                                @if ($latestDiscovery->error_message)
                                    <div class="muted">{{ $latestDiscovery->error_message }}</div>
                                @endif
                            @else
                                <span class="muted">No discovery yet</span>
                            @endif
                        </td>
                        <td>{{ $connection->base_url ?: 'Not set' }}</td>
                        <td>
                            @forelse ($connection->credentials as $credential)
                                <div>{{ $credential->credential_type }} <span class="secret-hint">configured</span></div>
                            @empty
                                <span class="muted">No credentials stored</span>
                            @endforelse
                        </td>
                        <td>
                            @php($metadata = is_array($connection->last_test_metadata) ? $connection->last_test_metadata : [])
                            @php($frontStores = $metadata['front_stores'] ?? [])
                            @if (is_array($frontStores) && $frontStores !== [])
                                @foreach ($frontStores as $store)
                                    <div>
                                        <strong>{{ $store['store_name'] ?? 'Unnamed store' }}</strong>
                                        <div class="muted">
                                            Store ID: {{ $store['store_id'] ?? 'n/a' }},
                                            Stock ID: {{ $store['stock_id'] ?? 'n/a' }},
                                            Currency: {{ $store['currency'] ?? 'n/a' }},
                                            Time zone: {{ $store['time_zone'] ?? 'n/a' }}
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <span class="muted">Not available</span>
                            @endif
                        </td>
                        <td>
                            <a class="button secondary" href="{{ route('connections.edit', $connection) }}">Edit</a>
                            <a class="button secondary" href="{{ route('connections.discovery', $connection) }}">Discovery</a>
                            <form class="inline-form" method="post" action="{{ route('connections.test', $connection) }}">
                                @csrf
                                <button type="submit">Test</button>
                            </form>
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
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">No connections yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </section>
    @empty
        <section class="panel">
            <h2>No organization yet</h2>
            <p>Create the first organization before adding WooCommerce or Front connections.</p>
            <a class="button" href="{{ route('organizations.create') }}">Create organization</a>
        </section>
    @endforelse
@endsection
