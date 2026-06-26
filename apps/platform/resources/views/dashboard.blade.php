@extends('layouts.app')

@section('content')
    <h1>Dashboard</h1>

    @if ($productionWritesEnabled)
        <div class="danger">Production writes are enabled. Use only with explicit approval.</div>
    @else
        <div class="warning">Production writes are disabled. This is the expected staging-safe mode.</div>
    @endif

    @unless ($connectionHttpTestsEnabled)
        <div class="warning">Live HTTP connection checks are disabled. Connection tests only verify stored settings.</div>
    @endunless

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
                    <th>Base URL</th>
                    <th>Credentials</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($organization->connections as $connection)
                    <tr>
                        <td>{{ $connection->name }}</td>
                        <td>{{ $connectionTypes[$connection->type] ?? $connection->type }}</td>
                        <td>{{ $connection->status }}</td>
                        <td>{{ $connection->base_url ?: 'Not set' }}</td>
                        <td>
                            @forelse ($connection->credentials as $credential)
                                <div>{{ $credential->credential_type }} <span class="secret-hint">{{ $credential->redacted_hint }}</span></div>
                            @empty
                                <span class="muted">No credentials stored</span>
                            @endforelse
                        </td>
                        <td>
                            <a class="button secondary" href="{{ route('connections.edit', $connection) }}">Edit</a>
                            <form class="inline-form" method="post" action="{{ route('connections.test', $connection) }}">
                                @csrf
                                <button type="submit">Test</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No connections yet.</td>
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
