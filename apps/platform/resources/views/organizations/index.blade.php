@extends('layouts.app')

@section('content')
    <section class="panel">
        <h1>Organizations</h1>
        <p class="muted">Each organization is one merchant/account. Start with Lilleprinsen in staging.</p>
        <p><a class="button" href="{{ route('organizations.create') }}">Add organization</a></p>
    </section>

    <section class="panel">
        <table>
            <thead>
            <tr>
                <th>Name</th>
                <th>Slug</th>
                <th>Environment</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($organizations as $organization)
                <tr>
                    <td>{{ $organization->name }}</td>
                    <td>{{ $organization->slug }}</td>
                    <td>{{ $organization->environment }}</td>
                    <td>{{ $organization->status }}</td>
                    <td><a class="button secondary" href="{{ route('organizations.edit', $organization) }}">Edit</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No organizations yet.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
