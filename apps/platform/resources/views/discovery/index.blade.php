@extends('layouts.app')

@section('content')
    <section class="panel page-header">
        <span class="kicker">Testing Lab</span>
        <h1>Read-Only Discovery Lab</h1>
        <p>Discovery reads small samples from connected systems so you can understand products and stores before syncing.</p>
        <div class="notice">Discovery is read-only and capped. It does not sync products, prices, stock, or orders.</div>
        <p class="muted">This is a setup and testing page. Normal users do not need to use it during daily operations.</p>
    </section>

    @foreach ($organizations as $organization)
        <section class="panel">
            <h2>{{ $organization->name }}</h2>
            <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Connection</th>
                    <th>Type</th>
                    <th>Last checked</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($organization->connections as $connection)
                    @php($snapshot = $connection->latestDiscoverySnapshot)
                    <tr>
                        <td>{{ $connection->name }}</td>
                        <td>{{ $connection->type }}</td>
                        <td>{{ $snapshot?->checked_at ?: 'Not checked yet' }}</td>
                        <td>{{ $snapshot?->status ?: 'Not selected' }}</td>
                        <td><a class="button secondary" href="{{ route('connections.discovery', $connection) }}">Open discovery</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">No connections yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </section>
    @endforeach
@endsection
