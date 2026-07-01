<?php

namespace App\Services\FrontSystems;

use App\Models\AuditLog;
use App\Models\Connection;
use App\Models\FrontWebhookRegistration;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Collection;
use Throwable;

class FrontWebhookSetupService
{
    public function __construct(
        private readonly FrontSystemsWebhookClient $client,
    ) {
    }

    public function registerSelected(Connection $connection, ?User $user, array $webhookTypes): array
    {
        $connection->loadMissing('credentials', 'organization');
        $webhookTypes = $this->normalizeWebhookTypes($webhookTypes);
        $endpoint = $this->frontEndpoint($connection);

        if ($message = $this->blockedReason($connection, $endpoint, $webhookTypes)) {
            return [
                'status' => 'failed',
                'message' => $message,
                'results' => [],
            ];
        }

        try {
            $existingResponse = $this->client->webhooks($connection);
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'message' => 'Could not read existing Front webhooks before registering new callbacks.',
                'results' => [],
            ];
        }

        if (! $existingResponse->successful()) {
            return [
                'status' => 'failed',
                'message' => 'Front returned HTTP ' . $existingResponse->status() . ' while reading existing webhooks.',
                'results' => [],
            ];
        }

        $existingWebhooks = collect($this->client->safeWebhooks($existingResponse->json()));
        $results = [];

        foreach ($webhookTypes as $webhookType) {
            $results[] = $this->registerType($connection, $endpoint, $user, $webhookType, $existingWebhooks);
        }

        return [
            'status' => collect($results)->contains(fn (array $result): bool => $result['status'] === 'failed') ? 'failed' : 'success',
            'message' => 'Front webhook setup finished.',
            'results' => $results,
        ];
    }

    private function registerType(
        Connection $connection,
        WebhookEndpoint $endpoint,
        ?User $user,
        string $webhookType,
        Collection $existingWebhooks,
    ): array {
        $callbackUrl = url("/webhooks/front/{$endpoint->path_token}");
        $existing = $this->matchingExistingWebhook($existingWebhooks, $webhookType);
        $frontWebhookId = is_array($existing) ? (string) ($existing['id'] ?? '') : '';
        $payload = [
            'event' => $webhookType,
            'url' => $callbackUrl,
        ];
        $requestSummary = [
            'method' => $frontWebhookId !== '' ? 'PUT' : 'POST',
            'endpoint' => $frontWebhookId !== '' ? '/api/Webhooks/{webhookId}' : '/api/Webhooks',
            'event' => $webhookType,
            'callback_url' => $callbackUrl,
            'openapi_contract' => 'WebhookViewModel: event + url, optional id/storeId if Front requires it.',
        ];

        $registration = FrontWebhookRegistration::query()->updateOrCreate(
            [
                'organization_id' => $connection->organization_id,
                'connection_id' => $connection->id,
                'webhook_type' => $webhookType,
            ],
            [
                'webhook_endpoint_id' => $endpoint->id,
                'front_webhook_id' => $frontWebhookId !== '' ? $frontWebhookId : null,
                'callback_url' => $callbackUrl,
                'status' => 'registering',
                'request_summary_json' => $requestSummary,
                'response_summary_json' => null,
                'last_error' => null,
            ],
        );

        try {
            $response = $frontWebhookId !== ''
                ? $this->client->updateWebhook($connection, $frontWebhookId, $payload)
                : $this->client->createWebhook($connection, $payload);
        } catch (Throwable $exception) {
            return $this->markFailed($registration, $connection, $user, $requestSummary, $exception::class);
        }

        $safeResponse = $this->client->safeWebhook($response->json());
        $remoteId = $safeResponse['id'] ?? ($frontWebhookId !== '' ? $frontWebhookId : null);
        $responseSummary = [
            'http_status' => $response->status(),
            'front_webhook_id' => $remoteId,
            'event' => $safeResponse['event'] ?? $webhookType,
            'callback_url' => $safeResponse['url'] ?? $callbackUrl,
        ];

        $registration->update([
            'front_webhook_id' => $remoteId,
            'status' => $response->successful() ? 'registered' : 'failed',
            'response_summary_json' => $responseSummary,
            'last_error' => $response->successful() ? null : 'Front returned HTTP ' . $response->status(),
            'registered_at' => $response->successful() ? now() : $registration->registered_at,
        ]);

        $this->audit($connection, $user, $registration, $response->successful() ? 'success' : 'failed');

        return [
            'webhook_type' => $webhookType,
            'status' => $response->successful() ? 'registered' : 'failed',
            'front_webhook_id' => $remoteId,
            'http_status' => $response->status(),
            'message' => $response->successful() ? 'Registered with Front.' : 'Front returned HTTP ' . $response->status(),
        ];
    }

    private function normalizeWebhookTypes(array $webhookTypes): array
    {
        return collect($webhookTypes)
            ->filter(fn ($value): bool => is_scalar($value))
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->take(10)
            ->values()
            ->all();
    }

    private function blockedReason(Connection $connection, ?WebhookEndpoint $endpoint, array $webhookTypes): ?string
    {
        if (! in_array($connection->type, ['front', 'front_systems'], true)) {
            return 'Webhook registration is only available for Front Systems connections.';
        }

        if (! (bool) config('omnibridge.allow_connection_test_http')) {
            return 'Live HTTP tests are disabled. Set OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=true locally or in staging before registering Front webhooks.';
        }

        if ((bool) config('omnibridge.allow_production_writes')) {
            return 'Production writes are enabled. Keep OMNIBRIDGE_ALLOW_PRODUCTION_WRITES=false for this staging webhook setup flow.';
        }

        if (trim((string) $connection->base_url) === '') {
            return 'Missing Front base URL.';
        }

        if (! $this->client->hasApiKey($connection)) {
            return 'Missing Front API key.';
        }

        if (! $endpoint || $endpoint->status !== 'active') {
            return 'Missing active OmniBridge Front webhook endpoint.';
        }

        if ($webhookTypes === []) {
            return 'Select at least one Front webhook type to register.';
        }

        return null;
    }

    private function frontEndpoint(Connection $connection): ?WebhookEndpoint
    {
        return WebhookEndpoint::query()
            ->where('organization_id', $connection->organization_id)
            ->where('source_system', 'front')
            ->where('status', 'active')
            ->first();
    }

    private function matchingExistingWebhook(Collection $existingWebhooks, string $webhookType): ?array
    {
        return $existingWebhooks
            ->first(function (array $webhook) use ($webhookType): bool {
                return strtolower((string) ($webhook['event'] ?? '')) === strtolower($webhookType);
            });
    }

    private function markFailed(
        FrontWebhookRegistration $registration,
        Connection $connection,
        ?User $user,
        array $requestSummary,
        string $error,
    ): array {
        $registration->update([
            'status' => 'failed',
            'request_summary_json' => $requestSummary,
            'response_summary_json' => null,
            'last_error' => $error,
        ]);

        $this->audit($connection, $user, $registration, 'failed');

        return [
            'webhook_type' => $registration->webhook_type,
            'status' => 'failed',
            'front_webhook_id' => $registration->front_webhook_id,
            'http_status' => null,
            'message' => $error,
        ];
    }

    private function audit(
        Connection $connection,
        ?User $user,
        FrontWebhookRegistration $registration,
        string $status,
    ): void {
        AuditLog::query()->create([
            'organization_id' => $connection->organization_id,
            'user_id' => $user?->id,
            'action' => 'front_webhook_registration_' . $status,
            'subject_type' => FrontWebhookRegistration::class,
            'subject_id' => $registration->id,
            'metadata_json' => [
                'connection_id' => $connection->id,
                'webhook_registration_id' => $registration->id,
                'source_system' => 'front_systems',
                'endpoint_group' => $registration->request_summary_json['endpoint'] ?? 'POST /api/Webhooks',
                'webhook_type' => $registration->webhook_type,
                'status' => $status,
                'live_http_enabled' => (bool) config('omnibridge.allow_connection_test_http'),
                'production_writes_disabled' => ! (bool) config('omnibridge.allow_production_writes'),
                'callback_url' => $registration->callback_url,
            ],
            'created_at' => now(),
        ]);
    }
}
