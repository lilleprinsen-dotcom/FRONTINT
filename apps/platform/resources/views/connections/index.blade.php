@extends('layouts.app')

@section('content')
    <section class="panel">
        <h1>Connections</h1>
        <p>Connect WooCommerce and Front Systems, then check whether each connection is ready.</p>
        <div class="notice">Stored secrets are encrypted and never shown again. Live connection tests are read-only and controlled by the staging/local environment.</div>
        @unless ($connectionHttpTestsEnabled)
            <div class="warning">Safe mode is on for normal WooCommerce REST and Front API tests. The Woo plugin adapter test is a separate signed read-only check against the installed WordPress plugin.</div>
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

            <table>
                <thead>
                <tr>
                    <th>Connection</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Last checked</th>
                    <th>Details</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($organization->connections as $connection)
                    <tr>
                        <td>
                            <strong>{{ $connection->name }}</strong>
                            <div class="muted">{{ $connection->base_url ?: 'Base URL not set' }}</div>
                        </td>
                        <td>{{ $connectionTypes[$connection->type] ?? $connection->type }}</td>
                        <td>
                            @php($isReady = in_array($connection->status, ['success', 'connected', 'active'], true))
                            @php($hasProblem = in_array($connection->status, ['failed', 'error'], true) || $connection->last_error)
                            <span class="status-dot {{ $isReady ? 'ready' : ($hasProblem ? 'blocked' : 'warning') }}"></span>
                            <strong>{{ $connection->status }}</strong>
                            @if ($connection->last_test_status)
                                <div class="muted">Last test: {{ $connection->last_test_status }}</div>
                            @endif
                        </td>
                        <td>{{ $connection->last_checked_at?->diffForHumans() ?: 'Not checked yet' }}</td>
                        <td>
                            @if ($connection->last_error)
                                <span class="badge blocked">Needs attention</span>
                                <div class="muted">{{ $connection->last_error }}</div>
                            @else
                                <span class="badge ready">No error saved</span>
                            @endif
                            @if ($connection->type === 'woocommerce' && data_get($connection->last_test_metadata, 'plugin_adapter.plugin.version'))
                                <div class="muted">
                                    Plugin adapter: v{{ data_get($connection->last_test_metadata, 'plugin_adapter.plugin.version') }},
                                    Woo {{ data_get($connection->last_test_metadata, 'plugin_adapter.woocommerce.version') ?: 'version unknown' }}
                                </div>
                            @endif
                            <div class="muted">{{ $connection->credentials->count() }} credential field(s) configured</div>
                        </td>
                        <td>
                            <div class="action-row">
                                <a class="button secondary" href="{{ route('connections.edit', $connection) }}">Edit</a>
                                <form class="inline-form" method="post" action="{{ route('connections.test', $connection) }}">
                                    @csrf
                                    <button type="submit">{{ $connection->type === 'woocommerce' ? 'Test Woo REST' : 'Test connection' }}</button>
                                </form>
                                @if ($connection->type === 'woocommerce')
                                    <form class="inline-form" method="post" action="{{ route('connections.test-woocommerce-plugin', $connection) }}">
                                        @csrf
                                        <button type="submit">Test Woo plugin</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No connections yet. Add WooCommerce first, then Front Systems.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </section>
    @empty
        <section class="panel">
            <h2>No organization yet</h2>
            <p>Create an organization before adding connections.</p>
            <a class="button" href="{{ route('organizations.create') }}">Create organization</a>
        </section>
    @endforelse
@endsection
