@extends('layouts.app')

@section('content')
    <section class="panel">
        <h1>Sync Runs</h1>
        <p>Each run tracks a planned group of WooCommerce products or variations. No products are written to Front in the current phase.</p>
        <div class="notice">Large catalog runs must be processed in batches later. This page shows run summaries and links to paginated item views.</div>
    </section>

    <section class="panel">
        <h2>Recent Runs</h2>
        <table>
            <thead>
            <tr>
                <th>Run</th>
                <th>Organization</th>
                <th>Type</th>
                <th>Status</th>
                <th>Scope</th>
                <th>Progress</th>
                <th>Created</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($runs as $run)
                @php($total = max((int) $run->total_candidates, 1))
                @php($width = (int) round(($run->total_ready / $total) * 100))
                <tr>
                    <td>#{{ $run->id }}</td>
                    <td>{{ $run->organization?->name ?: 'n/a' }}</td>
                    <td>{{ str_replace('_', ' ', $run->run_type) }}</td>
                    <td><span class="badge">{{ $run->status }}</span></td>
                    <td>{{ str_replace('_', ' ', $run->scope ?: 'not set') }}</td>
                    <td>
                        <div class="progress"><span style="width: {{ $width }}%"></span></div>
                        <div class="muted">{{ $run->total_ready }} ready, {{ $run->total_blocked }} needs attention, {{ $run->total_variations }} variations</div>
                    </td>
                    <td>{{ $run->created_at }}</td>
                    <td><a class="button secondary" href="{{ route('product-sync.runs.show', $run) }}">Open</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">No sync runs yet.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
        <div style="margin-top: 16px">
            {{ $runs->links() }}
        </div>
    </section>
@endsection
