@extends('layouts.app')

@section('content')
    <section class="panel">
        <h1>Discovery</h1>
        <p>Discovery reads small samples from connected systems so you can understand products and stores before syncing.</p>
        <div class="notice">Discovery is read-only and capped. It does not sync products, prices, stock, or orders.</div>
    </section>

    @foreach ($organizations as $organization)
        <section class="panel">
            <h2>{{ $organization->name }}</h2>
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
        </section>
    @endforeach
@endsection
