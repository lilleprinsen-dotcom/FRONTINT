@extends('layouts.app')

@section('content')
    <section class="panel page-header">
        <span class="kicker">Setup</span>
        <h1>Connections</h1>
        <p>Add WooCommerce now. Add Front when the account is ready. Connection tests are read-only and secrets are never shown again.</p>
        <div class="notice">Stored secrets are encrypted and never shown again. Live connection tests are read-only and controlled by the staging/local environment.</div>
        @unless ($connectionHttpTestsEnabled)
            <div class="warning">Safe mode is on. Normal WooCommerce REST and Front API tests will skip external HTTP calls. The Woo plugin adapter test is a separate signed read-only check against the installed WordPress plugin.</div>
        @endunless
    </section>

    @forelse ($organizations as $organization)
        <section class="panel">
            <div class="action-row" style="justify-content: space-between">
                <div>
                    <h2>{{ $organization->name }}</h2>
                    <p class="muted">Add WooCommerce and Front Systems connections for this organization.</p>
                </div>
                <a class="button" href="{{ route('connections.create', ['organization_id' => $organization->id]) }}">Add connection</a>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>System</th>
                        <th>Status</th>
                        <th>Last checked</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($organization->connections as $connection)
                        <tr>
                            <td>
                                <strong>{{ $connectionTypes[$connection->type] ?? $connection->type }}</strong>
                                <div class="muted">{{ $connection->name }}</div>
                                <div class="muted">{{ $connection->base_url ?: 'URL not set' }}</div>
                            </td>
                            <td>
                                @php($isReady = in_array($connection->status, ['success', 'connected', 'active'], true))
                                @php($hasProblem = in_array($connection->status, ['failed', 'error'], true) || $connection->last_error)
                                <span class="status-dot {{ $isReady ? 'ready' : ($hasProblem ? 'blocked' : 'warning') }}"></span>
                                <strong>{{ $isReady ? 'Ready' : ($hasProblem ? 'Needs attention' : 'Not checked yet') }}</strong>
                                @if ($connection->last_test_status)
                                    <div class="muted">Last test: {{ $connection->last_test_status }}</div>
                                @endif
                                @if ($connection->last_error)
                                    <div class="danger">{{ $connection->last_error }}</div>
                                @endif
                                <details class="technical-details">
                                    <summary>Technical details</summary>
                                    <div>Status value: {{ $connection->status }}</div>
                                    @if ($connection->type === 'woocommerce' && data_get($connection->last_test_metadata, 'plugin_adapter.plugin.version'))
                                        <div>
                                            Plugin adapter: v{{ data_get($connection->last_test_metadata, 'plugin_adapter.plugin.version') }},
                                            Woo {{ data_get($connection->last_test_metadata, 'plugin_adapter.woocommerce.version') ?: 'version unknown' }}
                                        </div>
                                    @endif
                                    <div>{{ $connection->credentials->count() }} credential field(s) configured</div>
                                </details>
                            </td>
                            <td>{{ $connection->last_checked_at?->diffForHumans() ?: 'Not checked yet' }}</td>
                            <td>
                                <div class="action-row">
                                    <a class="button secondary" href="{{ route('connections.edit', $connection) }}">Edit</a>
                                    <form class="inline-form" method="post" action="{{ route('connections.test', $connection) }}">
                                        @csrf
                                        <button type="submit">{{ $connection->type === 'woocommerce' ? 'Test WooCommerce' : 'Test connection' }}</button>
                                    </form>
                                    @if ($connection->type === 'woocommerce')
                                        <form class="inline-form" method="post" action="{{ route('connections.test-woocommerce-plugin', $connection) }}">
                                            @csrf
                                            <button class="secondary" type="submit">Test plugin</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">No connections yet. Add WooCommerce first, then Front Systems later.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <section class="panel">
            <h2>No organization yet</h2>
            <p>Create an organization before adding connections.</p>
            <a class="button" href="{{ route('organizations.create') }}">Create organization</a>
        </section>
    @endforelse
@endsection
