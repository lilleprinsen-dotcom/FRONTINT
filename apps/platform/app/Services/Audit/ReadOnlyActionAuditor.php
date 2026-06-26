<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\Connection;
use App\Models\User;

class ReadOnlyActionAuditor
{
    public function record(
        ?User $user,
        Connection $connection,
        string $actionType,
        string $endpointGroup,
        string $status,
        mixed $checkedAt = null,
    ): void {
        AuditLog::query()->create([
            'organization_id' => $connection->organization_id,
            'user_id' => $user?->id,
            'action' => $actionType,
            'subject_type' => Connection::class,
            'subject_id' => $connection->id,
            'metadata_json' => [
                'connection_id' => $connection->id,
                'source_system' => $connection->type,
                'endpoint_group' => $endpointGroup,
                'status' => $status,
                'checked_at' => $checkedAt?->toISOString() ?? now()->toISOString(),
                'live_http_enabled' => (bool) config('omnibridge.allow_connection_test_http'),
                'production_writes_enabled' => (bool) config('omnibridge.allow_production_writes'),
                'production_writes_disabled' => ! (bool) config('omnibridge.allow_production_writes'),
                'read_only' => true,
            ],
            'created_at' => now(),
        ]);
    }
}
