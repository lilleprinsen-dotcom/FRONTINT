@extends('layouts.app')

@section('content')
    <section class="panel page-header">
        <span class="kicker">Account setup</span>
        <h1>{{ $organization->exists ? 'Edit organization' : 'Create organization' }}</h1>
        <p>Use staging first. Production writes remain disabled unless explicitly enabled in environment configuration.</p>
    </section>

    <section class="panel">
        <form method="post" action="{{ $organization->exists ? route('organizations.update', $organization) : route('organizations.store') }}">
            @csrf
            @if ($organization->exists)
                @method('put')
            @endif

            <label for="name">Name</label>
            <input id="name" name="name" value="{{ old('name', $organization->name) }}" required>

            <label for="slug">Slug</label>
            <input id="slug" name="slug" value="{{ old('slug', $organization->slug) }}" required>

            <label for="environment">Environment</label>
            <select id="environment" name="environment" required>
                @foreach (['staging' => 'Staging', 'production' => 'Production'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('environment', $organization->environment) === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <label for="status">Status</label>
            <select id="status" name="status" required>
                @foreach (['active' => 'Active', 'paused' => 'Paused'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $organization->status) === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <p>
                <button type="submit">Save organization</button>
                <a class="button secondary" href="{{ route('dashboard') }}">Cancel</a>
            </p>
        </form>
    </section>
@endsection
