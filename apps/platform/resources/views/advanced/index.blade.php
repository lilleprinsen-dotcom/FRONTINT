@extends('layouts.app')

@section('content')
    <section class="panel">
        <h1>Advanced</h1>
        <div class="warning">Technical settings. Only change these if you know what they do.</div>
        <p class="muted">This area contains webhooks, API settings, raw logs, and sync profile technical details.</p>
    </section>

    <section class="grid">
        <div class="panel">
            <h2>Testing Lab</h2>
            <p class="muted">Read-only discovery, mapping preview, and preview-run experiments live here instead of the normal owner workflow.</p>
            <p><a class="button secondary" href="{{ route('lab.index') }}">Open Testing Lab</a></p>
        </div>
        <div class="panel">
            <h2>Safety Flags</h2>
            <p>Production writes: <strong>{{ $productionWritesEnabled ? 'enabled' : 'disabled' }}</strong></p>
            <p>Live HTTP tests: <strong>{{ $connectionHttpTestsEnabled ? 'enabled' : 'disabled' }}</strong></p>
        </div>
        <div class="panel">
            <h2>API Settings</h2>
            <p class="muted">Front OpenAPI spec is stored locally. No generated client is active yet.</p>
            <code>docs/vendor/front-systems/openapi/frontsystems.openapi.json</code>
        </div>
        <div class="panel">
            <h2>Sync Profiles</h2>
            <p><a class="button secondary" href="{{ route('product-sync.profile') }}">Open sync profile settings</a></p>
        </div>
        <div class="panel">
            <h2>Developer Tools</h2>
            <p class="muted">Raw logs, event payload summaries, API schema notes, and queue internals belong here as they are added.</p>
        </div>
        <div class="panel">
            <h2>Raw Logs</h2>
            <p class="muted">Use application logs only for troubleshooting. Secrets and full response bodies must remain redacted.</p>
        </div>
    </section>

    @foreach ($organizations as $organization)
        <section class="panel">
            <h2>{{ $organization->name }} Webhooks</h2>
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
        </section>
    @endforeach

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
