@extends('layouts.app')

@php
    $credentialFields = [
        'woocommerce' => [
            'site_url' => 'WooCommerce site URL',
            'consumer_key' => 'WooCommerce consumer key',
            'consumer_secret' => 'WooCommerce consumer secret',
        ],
        'front_systems' => [
            'api_key' => 'Front Systems API key',
        ],
        'front' => [
            'api_key' => 'Front Systems API key',
        ],
        'webtoffee_adapter' => [
            'shared_secret' => 'Adapter shared secret',
        ],
        'dintero' => [
            'note' => 'Optional staging note',
        ],
        'stripe' => [
            'note' => 'Optional staging note',
        ],
    ];

    $selectedType = old('type', $connection->type ?: 'woocommerce');
@endphp

@section('content')
    <section class="panel">
        <h1>{{ $connection->exists ? 'Edit connection' : 'Add connection' }}</h1>
        <p class="muted">Credentials are encrypted at rest and never shown again after saving. Leave credential fields empty to keep existing values.</p>
        <p class="muted">WooCommerce and Front connection tests are read-only. They do not sync products, stock, orders, refunds, or gift cards.</p>

        <form method="post" action="{{ $connection->exists ? route('connections.update', $connection) : route('connections.store') }}">
            @csrf
            @if ($connection->exists)
                @method('put')
            @endif

            <label for="organization_id">Organization</label>
            <select id="organization_id" name="organization_id" required>
                @foreach ($organizations as $organization)
                    <option value="{{ $organization->id }}" @selected((int) old('organization_id', $connection->organization_id) === $organization->id)>
                        {{ $organization->name }}
                    </option>
                @endforeach
            </select>

            <label for="type">Connection type</label>
            <select id="type" name="type" required data-connection-type-select>
                @foreach ($connectionTypes as $value => $label)
                    <option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <label for="name">Display name</label>
            <input id="name" name="name" value="{{ old('name', $connection->name) }}" required>

            <label for="base_url">Base URL</label>
            <input
                id="base_url"
                name="base_url"
                type="url"
                value="{{ old('base_url', $connection->base_url) }}"
                placeholder="https://example.com"
                data-base-url-input
                data-front-default-url="{{ config('omnibridge.front_systems.default_base_url') }}"
            >
            <p class="muted">WooCommerce example: https://store.example.com. Front Systems REST API V2 example: https://frontsystemsapis.frontsystems.no/restapi/V2.</p>

            <h2>Credentials</h2>
            <p class="muted">Only fill the fields for the selected connection type. Existing saved credentials are shown as redacted hints on the dashboard.</p>

            @foreach ($credentialFields as $type => $fields)
                <div class="panel" data-credential-panel="{{ $type }}" @hidden($selectedType !== $type)>
                    <h3>{{ $connectionTypes[$type] ?? $type }}</h3>
                    @foreach ($fields as $field => $label)
                        <label for="credential_{{ $type }}_{{ $field }}">{{ $label }}</label>
                        <input id="credential_{{ $type }}_{{ $field }}" name="credentials[{{ $field }}]" value="" autocomplete="off">
                    @endforeach
                </div>
            @endforeach

            <p>
                <button type="submit">Save connection</button>
                <a class="button secondary" href="{{ route('dashboard') }}">Cancel</a>
            </p>
        </form>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const typeSelect = document.querySelector('[data-connection-type-select]');
            const panels = document.querySelectorAll('[data-credential-panel]');
            const baseUrlInput = document.querySelector('[data-base-url-input]');

            const updateCredentialPanels = () => {
                panels.forEach((panel) => {
                    panel.hidden = panel.dataset.credentialPanel !== typeSelect.value;
                });

                if (['front', 'front_systems'].includes(typeSelect.value) && baseUrlInput.value.trim() === '') {
                    baseUrlInput.value = baseUrlInput.dataset.frontDefaultUrl;
                }
            };

            typeSelect.addEventListener('change', updateCredentialPanels);
            updateCredentialPanels();
        });
    </script>
@endsection
