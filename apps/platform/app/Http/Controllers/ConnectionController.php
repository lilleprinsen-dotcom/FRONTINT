<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConnectionRequest;
use App\Models\Connection;
use App\Models\Organization;
use App\Services\Credentials\CredentialVault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConnectionController extends Controller
{
    public function index(Request $request): View
    {
        return view('connections.index', [
            'organizations' => $request->user()
                ->organizations()
                ->with(['connections.credentials'])
                ->orderBy('name')
                ->get(),
            'connectionTypes' => config('omnibridge.connection_types'),
            'connectionHttpTestsEnabled' => (bool) config('omnibridge.allow_connection_test_http'),
        ]);
    }

    public function create(Request $request): View
    {
        $type = $request->query('type', 'woocommerce');

        return view('connections.form', [
            'connection' => new Connection([
                'organization_id' => $request->integer('organization_id'),
                'type' => $type,
                'base_url' => $this->defaultBaseUrl((string) $type),
                'status' => 'pending',
            ]),
            'organizations' => $this->organizations($request),
            'connectionTypes' => config('omnibridge.connection_types'),
        ]);
    }

    public function store(ConnectionRequest $request, CredentialVault $vault): RedirectResponse
    {
        $validated = $request->validated();
        $this->authorizeOrganization($request, (int) $validated['organization_id']);

        $connection = Connection::query()->create([
            'organization_id' => $validated['organization_id'],
            'type' => $validated['type'],
            'name' => $validated['name'],
            'base_url' => $this->resolvedBaseUrl($validated['type'], $validated['base_url'] ?? null, $request->input('credentials', [])),
            'status' => 'pending',
        ]);

        $this->storeCredentials($connection, $request->input('credentials', []), $vault);

        return redirect()
            ->route('connections.index')
            ->with('status', 'Connection saved.');
    }

    public function edit(Request $request, Connection $connection): View
    {
        $this->authorizeConnection($request, $connection);
        $connection->load('credentials');

        return view('connections.form', [
            'connection' => $connection,
            'organizations' => $this->organizations($request),
            'connectionTypes' => config('omnibridge.connection_types'),
        ]);
    }

    public function update(ConnectionRequest $request, Connection $connection, CredentialVault $vault): RedirectResponse
    {
        $validated = $request->validated();

        $this->authorizeConnection($request, $connection);
        $this->authorizeOrganization($request, (int) $validated['organization_id']);

        $connection->update([
            'organization_id' => $validated['organization_id'],
            'type' => $validated['type'],
            'name' => $validated['name'],
            'base_url' => $this->resolvedBaseUrl($validated['type'], $validated['base_url'] ?? null, $request->input('credentials', [])),
        ]);
        $this->storeCredentials($connection, $request->input('credentials', []), $vault);

        return redirect()
            ->route('connections.index')
            ->with('status', 'Connection updated.');
    }

    private function storeCredentials(Connection $connection, array $credentials, CredentialVault $vault): void
    {
        $allowedCredentialTypes = $this->credentialTypesForConnection($connection->type);

        foreach ($credentials as $credentialType => $value) {
            if (! in_array($credentialType, $allowedCredentialTypes, true)) {
                continue;
            }

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $vault->store($connection, (string) $credentialType, ['value' => trim($value)]);
        }
    }

    private function credentialTypesForConnection(string $connectionType): array
    {
        return match ($connectionType) {
            'woocommerce' => ['consumer_key', 'consumer_secret'],
            'front', 'front_systems' => ['api_key'],
            'webtoffee_adapter' => ['shared_secret'],
            'dintero', 'stripe' => ['note'],
            default => [],
        };
    }

    private function resolvedBaseUrl(string $connectionType, ?string $baseUrl, array $credentials): ?string
    {
        $baseUrl = is_string($baseUrl) && trim($baseUrl) !== '' ? trim($baseUrl) : null;

        if ($baseUrl) {
            return $baseUrl;
        }

        return $this->defaultBaseUrl($connectionType);
    }

    private function defaultBaseUrl(string $connectionType): ?string
    {
        if (in_array($connectionType, ['front', 'front_systems'], true)) {
            return (string) config('omnibridge.front_systems.default_base_url');
        }

        return null;
    }

    private function organizations(Request $request)
    {
        return $request->user()
            ->organizations()
            ->orderBy('name')
            ->get();
    }

    private function authorizeConnection(Request $request, Connection $connection): void
    {
        abort_unless(
            $request->user()->organizations()->whereKey($connection->organization_id)->exists(),
            403,
        );
    }

    private function authorizeOrganization(Request $request, int $organizationId): void
    {
        abort_unless(
            $request->user()->organizations()->whereKey($organizationId)->exists(),
            403,
        );
    }
}
