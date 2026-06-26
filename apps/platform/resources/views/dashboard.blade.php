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
        <h2>Live Read-Only Test Mode</h2>
        <p class="muted">
            Enable live HTTP only in local/staging by manually setting <code>OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=true</code>.
            Keep <code>OMNIBRIDGE_ALLOW_PRODUCTION_WRITES=false</code>.
        </p>
        <ul>
            <li>Connection tests use read-only endpoints only.</li>
            <li>WooCommerce connection test uses <code>GET /wp-json/wc/v3/system_status</code>.</li>
            <li>Front connection test uses <code>GET /api/Environment</code>.</li>
            <li>Front stores discovery uses <code>GET /api/Stores</code>.</li>
            <li>Product discovery fetches a maximum sample of 10 products.</li>
            <li>Front product discovery uses <code>POST /api/Product</code> because the Front OpenAPI spec documents it as the read-only product list endpoint. It must not be confused with <code>/api/products</code> CRUD.</li>
            <li>No sync is performed and no data is written.</li>
        </ul>
        <p><code>php artisan omnibridge:preflight-readonly</code></p>
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
            <h2>Front API Schema</h2>
            <p class="muted">REST API V2 OpenAPI is stored in vendor docs.</p>
            <code>docs/vendor/front-systems/front-api-endpoint-summary.md</code>
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

            <h3>Webhook URLs</h3>
            <table>
                <thead>
                <tr>
                    <th>Source</th>
                    <th>Status</th>
                    <th>URL</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($organization->webhookEndpoints as $endpoint)
                    <tr>
                        <td>{{ $endpoint->source_system }}</td>
                        <td>{{ $endpoint->status }}</td>
                        <td><code>{{ url("/webhooks/{$endpoint->source_system}/{$endpoint->path_token}") }}</code></td>
                    </tr>
                @endforeach
                </tbody>
            </table>

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

    <section class="panel">
        <h2>Recent Events</h2>
        <table>
            <thead>
            <tr>
                <th>Source</th>
                <th>Type</th>
                <th>Status</th>
                <th>Received</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($recentEvents as $event)
                <tr>
                    <td>{{ $event->source_system }}</td>
                    <td>{{ $event->event_type }}</td>
                    <td>{{ $event->status }}</td>
                    <td>{{ $event->received_at }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No events yet.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
