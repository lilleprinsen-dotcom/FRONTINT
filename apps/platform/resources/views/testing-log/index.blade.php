@extends('layouts.app')

@php
    $worked = $entries->filter(fn ($entry) => $entry['status'] === 'Worked')->count();
    $attention = $entries->filter(fn ($entry) => $entry['status'] === 'Needs attention')->count();
    $safe = $entries->filter(fn ($entry) => $entry['status'] === 'Skipped safely')->count();
@endphp

@section('content')
    <section class="panel page-header">
        <span class="kicker">Testing helper</span>
        <h1>Testing Log</h1>
        <p>This page collects recent tests and actions in one place. Copy the text box and send it to Codex when something fails or when you want a check of what happened.</p>
        <div class="notice">Secrets, API keys, full payloads, and full response bodies are not shown here.</div>
        @if ($connectionHttpTestsEnabled)
            <div class="warning">Live read-only tests are enabled. Real WooCommerce or Front staging systems may be contacted.</div>
        @endif
        @if ($productionWritesEnabled)
            <div class="danger">Production writes are enabled. Stop and review this before testing.</div>
        @endif
    </section>

    <section class="status-board">
        <div class="status-card ready">
            <span class="muted">Worked</span>
            <strong>{{ $worked }}</strong>
            <span>Recent successful checks</span>
        </div>
        <div class="status-card warning">
            <span class="muted">Skipped safely</span>
            <strong>{{ $safe }}</strong>
            <span>Safe-mode or intentionally skipped checks</span>
        </div>
        <div class="status-card {{ $attention > 0 ? 'blocked' : 'ready' }}">
            <span class="muted">Needs attention</span>
            <strong>{{ $attention }}</strong>
            <span>{{ $attention > 0 ? 'Send the log below for diagnosis' : 'No recent failures in this log' }}</span>
        </div>
    </section>

    <section class="panel">
        <div class="split-row">
            <div>
                <h2>Copy this for Codex</h2>
                <p class="muted">Use this when you want me to verify a test result or diagnose an error.</p>
            </div>
            <button class="secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('copy-log').value)">Copy log</button>
        </div>
        <textarea id="copy-log" class="copy-box" readonly>{{ $copyText }}</textarea>
    </section>

    <section class="panel">
        <h2>Recent activity</h2>
        <p class="muted">Plain-language summary of connection tests, discoveries, sync runs, webhook events, and portal actions.</p>
        <div class="table-wrap">
            <table class="simple-table">
                <thead>
                <tr>
                    <th>When</th>
                    <th>What</th>
                    <th>System</th>
                    <th>Status</th>
                    <th>Result</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td>{{ optional($entry['at'])->diffForHumans() ?: 'Unknown' }}</td>
                        <td>
                            <strong>{{ $entry['type'] }}</strong>
                            <div class="muted">{{ $entry['title'] }}</div>
                        </td>
                        <td>{{ $entry['system'] }}</td>
                        <td>
                            <span class="badge {{ $entry['status'] === 'Worked' ? 'ready' : ($entry['status'] === 'Needs attention' ? 'blocked' : 'warning-badge') }}">
                                {{ $entry['status'] }}
                            </span>
                        </td>
                        <td>
                            {{ $entry['summary'] }}
                            <details class="technical-details">
                                <summary>More detail</summary>
                                @foreach ($entry['details'] as $label => $value)
                                    <div>{{ $label }}: {{ $value ?: 'n/a' }}</div>
                                @endforeach
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">No tests or actions have been recorded yet. Run a connection test or product discovery first.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
